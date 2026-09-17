<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Livewire\Invoices\Show;
use App\Models\Property;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\EditInvoice;

use function Livewire\on;

/**
 * The reference lets staff set a Payment Method on an invoice, so the client arrives at a
 * gateway already chosen rather than picking from scratch (Leandro, issue #53).
 *
 * An invoice row here has no gateway column — the client picks one at payment time and the
 * transaction records what they used — so the admin's choice is kept as a property on the
 * invoice and applied here, to the client's own selection. It is a default, not a
 * restriction: the client may still choose any other method on the page.
 */
class PreselectInvoiceGateway
{
    public static function register(): void
    {
        // Same reasoning as PrefillInvoiceClient: a listener, not a ComponentHook, because
        // the hook registry is wired before extensions boot.
        on('mount', function ($component, $params, $key, $parent, $attributes = null): void {
            if (!$component instanceof Show || $component->selectedMethod !== null) {
                return;
            }

            $gatewayId = Property::where('model_type', $component->invoice->getMorphClass())
                ->where('model_id', $component->invoice->id)
                ->where('key', EditInvoice::METHOD_KEY)
                ->value('value');

            if (!$gatewayId) {
                return;
            }

            // Only offer it if that gateway is actually available for this invoice —
            // a gateway since disabled, or one that declines this total or currency via
            // canUseGateway(), must not be pre-selected into a dead payment button.
            foreach ($component->gateways() as $gateway) {
                if ((int) $gateway->id === (int) $gatewayId) {
                    $component->selectedMethod = 'gateway-' . (int) $gatewayId;

                    return;
                }
            }
        });
    }
}
