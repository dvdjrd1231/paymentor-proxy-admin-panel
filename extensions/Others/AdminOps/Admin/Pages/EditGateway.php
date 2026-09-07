<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\GatewayResource;
use App\Helpers\ExtensionHelper;
use App\Models\Gateway;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's payment gateway configuration screen (Leandro, 2026-09-07: the page "is working
 * as correctly but page design and styles format should be the WHMCS page standard
 * format").
 *
 * The gateway's own name and module over its module settings, label-left with the hint
 * beside each, closed by Save Changes / Cancel Changes / Deactivate — the shape every
 * other Setup screen here already uses. Core's own edit route redirects in.
 *
 * ## Stored values are shown
 *
 * Core masks encrypted settings and makes you retype them. Leandro asked for the stored
 * value (2026-09-07), which is also what the reference does — its gateway screen shows
 * the API keys it holds — so an encrypted setting renders with its real value.
 *
 * Two guards stay, because they cost nothing and this page prints live payment
 * credentials: it is behind the same permission that edits gateways at all, and every
 * field is marked no-autofill so a password manager never captures or overwrites a key.
 *
 * ## Saving
 *
 * Deliberately the same writes core's own EditGateway performs — the same settings rows
 * with the same `type` and `encrypted` flags, then the extension's `updated` hook — so a
 * gateway configured here behaves identically to one configured through core.
 */
class EditGateway extends Page
{
    protected string $view = 'adminops::pages.edit-gateway';

    protected static ?string $slug = 'gateway';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public Gateway $gateway;

    public string $name = '';

    /** @var array<string, mixed> The module's own settings, keyed by config name. */
    public array $settings = [];

    public bool $confirmingDelete = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record}';
    }

    public static function canAccess(): bool
    {
        return GatewayResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Payment Gateway';
    }

    public function getSubheading(): ?string
    {
        return $this->gateway->name;
    }

    public function mount(int|string $record): void
    {
        abort_unless(static::canAccess(), 403);

        $this->gateway = Gateway::with('settings')->findOrFail($record);
        $this->name = (string) $this->gateway->name;

        $stored = $this->gateway->settings->keyBy('key');

        foreach ($this->config() as $option) {
            $this->settings[$option['name']] = $stored[$option['name']]->value
                ?? ($option['default'] ?? null);
        }
    }

    /**
     * The module's configuration fields.
     *
     * A gateway whose extension has been removed from disk returns none rather than
     * throwing, so the screen still opens and can be renamed or deactivated.
     *
     * @return array<int, array<string, mixed>>
     */
    public function config(): array
    {
        try {
            return ExtensionHelper::getConfig('gateway', $this->gateway->extension) ?: [];
        } catch (\Throwable $exception) {
            return [];
        }
    }

    public function save(): void
    {
        abort_unless(GatewayResource::canEdit($this->gateway), 403);

        $this->validate(
            ['name' => 'required|string|max:255'],
            attributes: ['name' => 'gateway name'],
        );

        $this->gateway->update(['name' => $this->name]);

        // Core's own write, field for field: same rows, same type and encrypted flags.
        foreach ($this->config() as $option) {
            $value = $this->settings[$option['name']] ?? null;

            $this->gateway->settings()->updateOrCreate(
                [
                    'key' => $option['name'],
                    'settingable_id' => $this->gateway->id,
                    'settingable_type' => $this->gateway->getMorphClass(),
                ],
                [
                    'type' => $option['database_type'] ?? 'string',
                    'value' => is_array($value) ? json_encode($value) : $value,
                    'encrypted' => $option['encrypted'] ?? false,
                ],
            );
        }

        // The extension may react to its own settings changing — Stripe re-registers its
        // webhook here, for one — and may alter the record while doing so.
        ExtensionHelper::call($this->gateway, 'updated', [$this->gateway], mayFail: true);
        $this->gateway = $this->gateway->refresh()->load('settings');

        Notification::make()->title('Gateway settings saved')->success()->send();
    }

    /** The reference's Deactivate: core's own delete, with the extension told first. */
    public function delete()
    {
        abort_unless(GatewayResource::canDelete($this->gateway), 403);

        ExtensionHelper::call($this->gateway, 'disabled', [$this->gateway], mayFail: true);
        $this->gateway->delete();

        Notification::make()->title('Gateway deactivated')
            ->body('Its settings are kept, so activating it again restores them.')->success()->send();

        return redirect()->to(PaymentGateways::getUrl());
    }
}
