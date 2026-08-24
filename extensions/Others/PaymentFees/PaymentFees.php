<?php

namespace Paymenter\Extensions\Others\PaymentFees;

use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Gateway;
use App\Models\Invoice;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\PaymentFees\Support\FeeCalculator;

/**
 * Per-gateway payment fees — fixed, percentage or both — scoped by country, currency,
 * product, customer type and invoice amount. Calculated server-side in Support\FeeCalculator,
 * managed in the admin panel. The single core integration point is in docs/CORE-TOUCHPOINTS.md.
 *
 * @link docs/modules/payment-fees.md
 */
class PaymentFees extends Extension
{
    /** Invoice-item description prefix used to find/replace the fee line. */
    public const FEE_ITEM_PREFIX = '__payment_fee__';

    public function getConfig($values = [])
    {
        try {
            $url = \Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource::getUrl();

            return [[
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Manage fee rules under <a class="text-primary-600" href="' . $url . '">Payment Fee Rules</a>.'),
            ]];
        } catch (\Throwable $e) {
            return [[
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Enable this extension, then manage rules under "Payment Fee Rules".'),
            ]];
        }
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/PaymentFees/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/PaymentFees/database/migrations');
    }

    // ── Public API used at the payment integration point ────────────────────────

    /** The fee that would be charged for paying $invoice with $gateway (0 if none). */
    public static function feeFor(Gateway $gateway, Invoice $invoice): float
    {
        return FeeCalculator::calculate($gateway, $invoice);
    }

    /**
     * Apply the gateway fee as a line item, idempotently: any prior fee line is removed
     * first, so switching gateways never stacks fees. Returns the amount applied.
     */
    public static function applyFee(Gateway $gateway, Invoice $invoice): float
    {
        $invoice->items()
            ->where('description', 'like', self::FEE_ITEM_PREFIX . '%')
            ->delete();

        // The cached items/total still hold the line just deleted; without this refresh a
        // percentage fee compounds on every gateway switch in the payment modal.
        $invoice->refresh();
        $invoice->load('items');

        $fee = FeeCalculator::calculate($gateway, $invoice);
        if ($fee <= 0) {
            $invoice->refresh();

            return 0.0;
        }

        $rule = FeeCalculator::matchingRule($gateway, $invoice);
        $label = self::FEE_ITEM_PREFIX . ($rule?->name ?? 'Payment fee') . ' (' . $gateway->name . ')';

        $invoice->items()->create([
            'description' => $label,
            'price' => $fee,
            'quantity' => 1,
        ]);

        $invoice->refresh();

        return $fee;
    }

    /** Strip the internal prefix for display. */
    public static function displayLabel(string $description): string
    {
        return trim(str_replace(self::FEE_ITEM_PREFIX, '', $description));
    }
}
