<?php

namespace Paymenter\Extensions\Others\ProvisioningOps;

use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\ProvisioningOps\Models\ProvisioningOperation;

/**
 * Provisioning operations log — makes failed provisioning **visible to the admin and
 * retryable**, and stops a failed provision from leaving an order silently "active".
 *
 * Why this exists
 * ---------------
 * Paymenter activates a service and dispatches provisioning as a queued job
 * (`App\Services\Service\RenewServiceService`): the status is set to `active` and saved
 * immediately, *before* the queue worker runs `CreateJob`. If the panel API is down, the
 * job fails on the worker and nothing points back at the service — the customer has an
 * "active" proxy service that was never provisioned.
 *
 * Server extensions call `ProvisioningOps::failed()` from their error path. That:
 *   1. records the failure (one row per service+extension+action, attempts counted),
 *   2. reverts a service that a failed *create* had already marked active back to
 *      `pending`, so it is never silently active, and
 *   3. surfaces it in the admin with a one-click **Retry**.
 *
 * All entry points are null-safe and table-existence guarded, so a server extension can
 * call them unconditionally even when this module is disabled.
 *
 * @link docs/modules/provisioning-ops.md
 */
class ProvisioningOps extends Extension
{
    private const LOG_CHANNEL = 'stack';

    public function getConfig($values = [])
    {
        try {
            $url = Admin\Resources\ProvisioningOperationResource::getUrl();

            return [[
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Failed provisioning actions are listed under <a class="text-primary-600" href="' . $url . '">Provisioning</a>, each with a Retry button.'),
            ]];
        } catch (\Throwable $e) {
            return [[
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Enable this extension, then failed provisioning actions appear under "Provisioning".'),
            ]];
        }
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/ProvisioningOps/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/ProvisioningOps/database/migrations');
    }

    // ── Recorder API (called by server extensions) ───────────────────────────

    /**
     * Record a failed provisioning operation and protect the service's status.
     *
     * @param  string  $action  create | suspend | unsuspend | terminate | upgrade | callback
     */
    public static function failed(Service $service, string $extension, string $action, \Throwable $e, array $context = []): void
    {
        if (!self::available()) {
            return;
        }

        try {
            $row = ProvisioningOperation::firstOrNew([
                'service_id' => $service->id,
                'extension' => $extension,
                'action' => $action,
            ]);

            $row->status = ProvisioningOperation::STATUS_FAILED;
            $row->attempts = ($row->exists ? (int) $row->attempts : 0) + 1;
            $row->error = \Str::limit($e->getMessage(), 2000);
            $row->context = $context;
            $row->resolved_at = null;
            $row->last_attempt_at = now();
            $row->save();

            self::protectServiceStatus($service, $action);
            self::alertAdmins($service, $extension, $e);
        } catch (\Throwable $inner) {
            // Never let bookkeeping mask the original provisioning error.
            Log::channel(self::LOG_CHANNEL)->error('[ProvisioningOps] could not record failure', [
                'service' => $service->id,
                'action' => $action,
                'error' => $inner->getMessage(),
            ]);
        }
    }

    /**
     * Mark an operation as succeeded, clearing any earlier failure for it.
     */
    public static function succeeded(Service $service, string $extension, string $action, array $context = []): void
    {
        if (!self::available()) {
            return;
        }

        try {
            $row = ProvisioningOperation::where([
                'service_id' => $service->id,
                'extension' => $extension,
                'action' => $action,
            ])->first();

            // Only track successes that close a known failure — we are a failure log,
            // not an audit trail (core already audits every extension action).
            if (!$row) {
                return;
            }

            $row->status = ProvisioningOperation::STATUS_SUCCEEDED;
            $row->error = null;
            $row->context = $context ?: $row->context;
            $row->resolved_at = now();
            $row->last_attempt_at = now();
            $row->save();
        } catch (\Throwable $inner) {
            Log::channel(self::LOG_CHANNEL)->error('[ProvisioningOps] could not record success', [
                'service' => $service->id,
                'action' => $action,
                'error' => $inner->getMessage(),
            ]);
        }
    }

    /**
     * Push the failure to the notification channels (scope §11 "critical failures"), so a
     * panel outage reaches someone instead of sitting unnoticed in the admin list.
     *
     * Optional by design: the Notifications extension may not be installed, and a
     * notification problem must never mask the provisioning problem.
     */
    private static function alertAdmins(Service $service, string $extension, \Throwable $e): void
    {
        $notifications = '\\Paymenter\\Extensions\\Others\\Notifications\\Notifications';

        if (!class_exists($notifications) || !method_exists($notifications, 'provisioningFailed')) {
            return;
        }

        try {
            $notifications::provisioningFailed($extension, (int) $service->id, $e->getMessage());
        } catch (\Throwable $inner) {
            Log::channel(self::LOG_CHANNEL)->warning('[ProvisioningOps] could not send failure alert', [
                'service' => $service->id,
                'error' => $inner->getMessage(),
            ]);
        }
    }

    /**
     * A service whose *creation* failed must not stay "active" — otherwise the customer
     * is billed for, and sees, a service the panel never provisioned.
     */
    private static function protectServiceStatus(Service $service, string $action): void
    {
        if ($action !== 'create' || $service->status !== Service::STATUS_ACTIVE) {
            return;
        }

        $service->status = Service::STATUS_PENDING;
        $service->save();

        Log::channel(self::LOG_CHANNEL)->warning('[ProvisioningOps] service reverted to pending after failed create', [
            'service' => $service->id,
        ]);
    }

    /**
     * True when this module is installed (its table exists). Guards every entry point so
     * server extensions can call the recorder without checking first.
     */
    public static function available(): bool
    {
        try {
            return Schema::hasTable('provisioning_operations');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
