<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ApiResource;
use App\Models\ApiKey;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #50 — WHMCS's Manage API Credentials: the intro, the green Generate button, and
 * the Identifier / Description / Admin User / Last Access grid. Rows are Paymenter's
 * real API keys. The identifier is shown truncated on purpose — unlike WHMCS's separate
 * identifier/secret pair, Paymenter's token IS the secret, and a list page must not
 * print secrets. WHMCS's API Roles tab has no Paymenter equivalent (permissions sit on
 * each key), and the page says so instead of drawing an empty tab.
 */
class ApiCredentials extends Page
{
    protected string $view = 'adminops::pages.api-credentials';

    protected static ?string $slug = 'api-credentials';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public ?int $confirming = null;

    public static function canAccess(): bool
    {
        return ApiResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Manage API Credentials';
    }

    /** The reference's own intro, verbatim. */
    public function getSubheading(): ?string
    {
        return 'API Credentials enable more effective and secure management of administrative '
            . 'access provided to external applications and devices.';
    }

    public function runDelete(): void
    {
        $id = $this->confirming;
        $this->reset('confirming');

        $key = ApiKey::find($id);

        if (!$key || !ApiResource::canDelete($key)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $key->delete();
        Notification::make()->title('API credential revoked')
            ->body('Anything still using it stops authenticating immediately.')->success()->send();
    }

    protected function getViewData(): array
    {
        $keys = ApiKey::orderBy('id')->get();
        // Core's ApiKey model carries user_id but no relation; resolved in one query here.
        $users = \App\Models\User::whereIn('id', $keys->pluck('user_id')->filter())->get()->keyBy('id');

        // Core's ApiResource is a single manage screen — no create/edit routes — so both
        // the Generate button and Edit land there, where those actions live.
        $manage = null;
        try {
            $manage = ApiResource::getUrl('index');
        } catch (\Throwable $e) {
        }

        return [
            'keys' => $keys->map(fn (ApiKey $key) => [
                'row' => $key,
                'user' => $users[$key->user_id] ?? null,
                'edit' => ApiResource::canEdit($key) ? $manage : null,
            ]),
            'newUrl' => ApiResource::canCreate() ? $manage : null,
        ];
    }
}
