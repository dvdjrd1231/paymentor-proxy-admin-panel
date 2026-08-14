<?php

namespace Paymenter\Extensions\Others\PaymentFees;

use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Gateway;
use App\Models\Invoice;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\PaymentFees\Support\FeeCalculator;

/**
 * Payment Method Fees (spec item 6).
 *
 * Adds an additional fee based on the selected payment gateway — fixed, percentage,
 * or fixed+percentage — scoped by country, currency, product, customer type and
 * invoice-amount range. All calculation is server-side (Support\FeeCalculator);
 * rules are managed in the admin panel (Admin\Resources\PaymentFeeRuleResource,
 * auto-discovered when the extension is enabled).
 *
 * Applying the fee to an invoice is a one-line call (see feeFor()/applyFee()); the
 * single integration point in core is documented in docs/CORE-TOUCHPOINTS.md.
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
     * Idempotently apply the gateway fee to the invoice as a line item.
     *
     * Any previously-applied payment fee is removed first, so switching gateways
     * never stacks fees. Returns the fee amount applied (0 if none / removed).
     */
    public static function applyFee(Gateway $gateway, Invoice $invoice): float
    {
        // Remove any prior fee line (idempotent).
        $invoice->items()
            ->where('description', 'like', self::FEE_ITEM_PREFIX . '%')
            ->delete();

        // The invoice (and its cached items/total) still holds the fee line we just
        // deleted. Without this refresh a percentage fee is calculated on a total that
        // already includes the previous fee, so switching gateways in the payment modal
        // compounds the fee on every switch.
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
