<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\GatewayResource;
use App\Models\Gateway;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #45 — the gateways list with the quick-access buttons Leandro asked for:
 * Enable, Disable, Edit on every row. Enable/disable writes the same `enabled` flag
 * core's own edit form toggles; Edit opens that form, where credentials live.
 */
class PaymentGateways extends Page
{
    protected string $view = 'adminops::pages.payment-gateways';

    protected static ?string $slug = 'payment-gateways';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public ?int $confirming = null;

    public bool $confirmEnable = false;

    public static function canAccess(): bool
    {
        return GatewayResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Payment Gateways';
    }

    public function confirm(int $id, bool $enable): void
    {
        $this->confirming = $id;
        $this->confirmEnable = $enable;
    }

    public function run(): void
    {
        [$id, $enable] = [$this->confirming, $this->confirmEnable];
        $this->reset(['confirming', 'confirmEnable']);

        $gateway = Gateway::find($id);

        if (!$gateway || !GatewayResource::canEdit($gateway)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $gateway->update(['enabled' => $enable]);

        Notification::make()
            ->title($enable ? 'Gateway enabled' : 'Gateway disabled')
            ->body($enable
                ? $gateway->name . ' is offered at checkout again.'
                : $gateway->name . ' is no longer offered at checkout. Existing payments are untouched.')
            ->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'gateways' => Gateway::orderBy('name')->get(),
            'canEdit' => fn (Gateway $gateway) => GatewayResource::canEdit($gateway)
                ? GatewayResource::getUrl('edit', ['record' => $gateway])
                : null,
        ];
    }
}
