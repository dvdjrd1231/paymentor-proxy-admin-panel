<?php

namespace Paymenter\Extensions\Others\Notifications;

use App\Classes\Extension\Extension;
use App\Events\Notification\Created as NotificationCreated;
use App\Events\ServiceCancellation\Created as CancellationCreated;
use App\Events\Ticket\Created as TicketCreated;
use App\Helpers\ExtensionHelper;
use App\Models\Notification as NotificationModel;
use App\Models\ServiceCancellation;
use App\Models\Ticket;
use App\Models\User;
use App\Events\Invoice\Paid as InvoicePaid;
use App\Events\InvoiceTransaction\Created as TransactionCreated;
use App\Events\Service\Updated as ServiceUpdated;
use App\Events\TicketMessage\Created as TicketMessageCreated;
use App\Events\User\Created as UserCreated;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Others\Notifications\Jobs\SendTelegramMessage;

/**
 * Centralised notification system — Telegram delivery layer.
 *
 * Paymenter core already delivers **email** and **in-app** notifications for every
 * supported event (payments, tickets, provisioning, suspension, cancellation, …)
 * through App\Helpers\NotificationHelper + admin-editable NotificationTemplates,
 * with per-user preferences. This extension adds the missing **Telegram** channel
 * and admin/critical alerting, WITHOUT modifying core:
 *
 *  - Customer Telegram: every in-app notification core creates is mirrored to the
 *    customer's Telegram (if they saved their chat id), via the Notification\Created
 *    event. This automatically covers all events core notifies on.
 *  - Admin Telegram: key operational + critical events are pushed to a configured
 *    admin chat (new tickets, cancellations, and — via notifyAdmins()/notifyCritical()
 *    helpers other modules can call — provisioning failures, webhook errors, etc.).
 *  - All delivery is queued with automatic retry (see SendTelegramMessage).
 *
 * Credentials are encrypted extension settings; nothing is hard-coded.
 *
 * @link docs/modules/notifications.md
 */
class Notifications extends Extension
{
    /** User property where a customer stores their Telegram chat id. */
    public const USER_CHAT_KEY = 'telegram_chat_id';

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'bot_token',
                'label' => 'Telegram Bot Token',
                'type' => 'text',
                'description' => 'Token from @BotFather. Stored encrypted. Used for all Telegram delivery.',
                'required' => false,
                'encrypted' => true,
            ],
            [
                'name' => 'admin_chat_id',
                'label' => 'Admin Chat ID',
                'type' => 'text',
                'description' => 'Telegram chat/group id that receives admin & critical alerts (new tickets, cancellations, failures). Leave empty to disable admin alerts.',
                'required' => false,
            ],
            [
                'name' => 'notify_customers',
                'label' => 'Send customer notifications to Telegram',
                'type' => 'checkbox',
                'description' => 'Mirror each customer in-app notification to their Telegram (if they saved a chat id in their profile).',
                'required' => false,
            ],
            [
                'name' => 'notify_admin_tickets',
                'label' => 'Alert admins on new tickets',
                'type' => 'checkbox',
                'required' => false,
            ],
            [
                'name' => 'notify_admin_cancellations',
                'label' => 'Alert admins on service cancellations',
                'type' => 'checkbox',
                'required' => false,
            ],
            [
                'name' => 'notify_admin_payments',
                'label' => 'Alert admins on payments',
                'description' => 'Fires when an invoice is marked paid, whichever gateway settled it.',
                'type' => 'checkbox',
                'default' => true,
                'required' => false,
            ],
            [
                'name' => 'notify_admin_webhooks',
                'label' => 'Alert admins on gateway webhooks',
                'description' => 'Succeeded and failed gateway transactions (CoinPayments IPNs, Binance Pay, Cryptomus). '
                    . 'Failures are sent as critical alerts. Pending states are ignored to avoid noise.',
                'type' => 'checkbox',
                'default' => true,
                'required' => false,
            ],
            [
                'name' => 'notify_admin_provisioning',
                'label' => 'Alert admins on provisioning',
                'description' => 'A service being provisioned, and — as a critical alert — any provisioning failure.',
                'type' => 'checkbox',
                'default' => true,
                'required' => false,
            ],
            [
                'name' => 'notify_admin_suspensions',
                'label' => 'Alert admins on service suspensions',
                'type' => 'checkbox',
                'default' => true,
                'required' => false,
            ],
            [
                'name' => 'notify_admin_changes',
                'label' => 'Alert admins on administrative changes',
                'description' => 'New customer registrations.',
                'type' => 'checkbox',
                'required' => false,
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/Notifications/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/Notifications/database/migrations');
    }

    public function boot()
    {
        // ── Customer channel: mirror every in-app notification to Telegram ──────
        Event::listen(NotificationCreated::class, function (NotificationCreated $event) {
            if (!$this->config('notify_customers')) {
                return;
            }
            $this->mirrorToCustomer($event->notification);
        });

        // ── Admin channel: new support tickets ──────────────────────────────────
        Event::listen(TicketCreated::class, function (TicketCreated $event) {
            if (!$this->config('notify_admin_tickets')) {
                return;
            }
            $this->notifyAdmins($this->formatTicket($event->ticket));
        });

        // ── Admin channel: service cancellations ────────────────────────────────
        Event::listen(CancellationCreated::class, function (CancellationCreated $event) {
            if (!$this->config('notify_admin_cancellations')) {
                return;
            }
            $this->notifyAdmins($this->formatCancellation($event->cancellation));
        });

        // ── Payments ────────────────────────────────────────────────────────────
        Event::listen(InvoicePaid::class, function (InvoicePaid $event) {
            if (!$this->config('notify_admin_payments')) {
                return;
            }
            $invoice = $event->invoice;
            $this->notifyAdmins(sprintf(
                "\u{1F4B0} <b>Invoice paid</b>\nInvoice #%s\nCustomer: %s\nAmount: %s",
                $invoice->number ?? $invoice->id,
                e((string) ($invoice->user?->email ?? 'unknown')),
                e((string) $invoice->formattedTotal),
            ));
        });

        // ── Webhooks / gateway activity ─────────────────────────────────────────
        // Every gateway webhook lands as an InvoiceTransaction, so one listener covers
        // CoinPayments IPNs, Binance Pay callbacks and Cryptomus alike.
        Event::listen(TransactionCreated::class, function (TransactionCreated $event) {
            if (!$this->config('notify_admin_webhooks')) {
                return;
            }
            $transaction = $event->invoiceTransaction;
            $status = $transaction->status instanceof \BackedEnum
                ? $transaction->status->value
                : (string) $transaction->status;

            // Only the states an admin must act on — routine "processing" rows are noise.
            if (!in_array(strtolower($status), ['failed', 'succeeded'], true)) {
                return;
            }

            $failed = strtolower($status) === 'failed';
            $message = sprintf(
                "%s <b>Gateway %s</b>\nInvoice #%s\nGateway: %s\nRef: %s",
                $failed ? "\u{26A0}" : "\u{2705}",
                e($status),
                $transaction->invoice_id,
                e((string) ($transaction->gateway?->name ?? 'credits')),
                e((string) $transaction->transaction_id),
            );

            $failed ? $this->notifyCritical($message) : $this->notifyAdmins($message);
        });

        // ── Provisioning and suspension ─────────────────────────────────────────
        Event::listen(ServiceUpdated::class, function (ServiceUpdated $event) {
            $service = $event->service;

            if (!$service->isDirty('status')) {
                return;
            }

            if ($service->status === Service::STATUS_SUSPENDED && $this->config('notify_admin_suspensions')) {
                $this->notifyAdmins(sprintf(
                    "\u{23F8} <b>Service suspended</b>\nService #%s (%s)\nCustomer: %s",
                    $service->id,
                    e((string) ($service->product?->name ?? '')),
                    e((string) ($service->user?->email ?? '')),
                ));
            }

            if ($service->status === Service::STATUS_ACTIVE && $this->config('notify_admin_provisioning')) {
                $this->notifyAdmins(sprintf(
                    "\u{1F680} <b>Service provisioned</b>\nService #%s (%s)\nCustomer: %s",
                    $service->id,
                    e((string) ($service->product?->name ?? '')),
                    e((string) ($service->user?->email ?? '')),
                ));
            }
        });

        // ── Ticket replies ──────────────────────────────────────────────────────
        Event::listen(TicketMessageCreated::class, function (TicketMessageCreated $event) {
            if (!$this->config('notify_admin_tickets')) {
                return;
            }
            $message = $event->ticketMessage;

            // Staff replies are written by staff — only surface customer ones.
            if ($message->user_id !== $message->ticket?->user_id) {
                return;
            }

            $this->notifyAdmins(sprintf(
                "\u{1F4AC} <b>Ticket reply</b>\n#%s %s\nFrom: %s",
                $message->ticket?->id,
                e((string) ($message->ticket?->subject ?? '')),
                e((string) ($message->user?->email ?? '')),
            ));
        });

        // ── Administrative changes ──────────────────────────────────────────────
        Event::listen(UserCreated::class, function (UserCreated $event) {
            if (!$this->config('notify_admin_changes')) {
                return;
            }
            $this->notifyAdmins(sprintf(
                "\u{1F464} <b>New customer</b>\n%s",
                e((string) $event->user->email),
            ));
        });
    }

    /**
     * Report a provisioning failure as a critical alert.
     *
     * Called by Others/ProvisioningOps so a panel outage reaches an admin immediately,
     * rather than waiting to be spotted in the admin list. Safe to call when this
     * extension is disabled or misconfigured — it never throws.
     */
    public static function provisioningFailed(string $extension, int $serviceId, string $error): void
    {
        try {
            $instance = new self;

            if (!$instance->config('notify_admin_provisioning')) {
                return;
            }

            $instance->notifyCritical(sprintf(
                "<b>Provisioning failed</b>\n%s — service #%d\n%s",
                e($extension),
                $serviceId,
                e(\Str::limit($error, 300)),
            ));
        } catch (\Throwable $e) {
            // A notification failure must never mask the provisioning failure.
        }
    }

    // ── Public helpers other modules can call for critical/admin alerts ─────────

    /**
     * Send a message to the configured admin chat. Safe no-op if unconfigured.
     * Other modules (gateways, ProxyPanel) can call this for failures/webhooks.
     */
    public function notifyAdmins(string $message): void
    {
        $token = (string) $this->config('bot_token');
        $chat = (string) $this->config('admin_chat_id');
        if ($token === '' || $chat === '') {
            return;
        }

        // A notification must never break the operation that triggered it. The job
        // deliberately throws so the queue worker retries it — but on a `sync` queue
        // that exception would propagate straight into the caller, so a Telegram
        // outage could fail a provisioning run or a payment. Contain it here.
        try {
            SendTelegramMessage::dispatch($token, $chat, $message);
        } catch (\Throwable $e) {
            Log::channel('stack')->warning('[Notifications] Telegram dispatch failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Convenience for critical failures (prefixed + always attempted). */
    public function notifyCritical(string $message): void
    {
        $this->notifyAdmins("\u{1F6A8} <b>Critical</b>\n" . $message);
        Log::channel('stack')->critical('[Notifications] ' . strip_tags($message));
    }

    // ── Internal ────────────────────────────────────────────────────────────────

    private function mirrorToCustomer(NotificationModel $notification): void
    {
        $token = (string) $this->config('bot_token');
        if ($token === '' || !$notification->user_id) {
            return;
        }

        $chatId = optional(
            User::find($notification->user_id)
        )?->properties()->where('key', self::USER_CHAT_KEY)->value('value');

        if (!$chatId) {
            return;
        }

        $text = '<b>' . e($notification->title) . '</b>';
        if ($notification->body) {
            $text .= "\n" . e(strip_tags($notification->body));
        }
        if ($notification->url) {
            $text .= "\n" . e($notification->url);
        }

        // Same containment as notifyAdmins(): never let a delivery problem escape into
        // whatever created the notification.
        try {
            SendTelegramMessage::dispatch($token, (string) $chatId, $text);
        } catch (\Throwable $e) {
            Log::channel('stack')->warning('[Notifications] Telegram dispatch failed', [
                'user' => $notification->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatTicket(Ticket $ticket): string
    {
        return "\u{1F3AB} <b>New ticket</b> #{$ticket->id}\n"
            . e($ticket->subject)
            . ($ticket->user ? "\nFrom: " . e($ticket->user->email) : '');
    }

    private function formatCancellation(ServiceCancellation $cancellation): string
    {
        $service = $cancellation->service;

        return "\u{26D4} <b>Service cancellation requested</b>\n"
            . 'Service: ' . e($service->label ?? ('#' . $service->id))
            . ($service->user ? "\nCustomer: " . e($service->user->email) : '');
    }
}
