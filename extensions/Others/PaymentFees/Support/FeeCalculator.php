<?php

namespace Paymenter\Extensions\Others\PaymentFees\Support;

use App\Models\Gateway;
use App\Models\Invoice;
use Paymenter\Extensions\Others\PaymentFees\Models\PaymentFeeRule;

/**
 * Server-side payment-method fee calculation.
 *
 * Everything here runs on the backend — the fee is never trusted from the client.
 * Given a gateway and an invoice, it finds the first matching active rule (by
 * ascending priority) and returns the fee amount for that invoice.
 */
class FeeCalculator
{
    /**
     * Compute the fee (in the invoice currency) for paying $invoice with $gateway.
     * Returns 0.0 when no rule matches.
     */
    public static function calculate(Gateway $gateway, Invoice $invoice): float
    {
        $rule = self::matchingRule($gateway, $invoice);
        if (!$rule) {
            return 0.0;
        }

        return self::feeForRule($rule, (float) $invoice->total);
    }

    /**
     * The rule that would apply, or null. Exposed so the UI can show which rule
     * and label is being charged.
     */
    public static function matchingRule(Gateway $gateway, Invoice $invoice): ?PaymentFeeRule
    {
        $context = self::context($gateway, $invoice);

        $rules = PaymentFeeRule::where('active', true)
            ->where(function ($q) use ($gateway) {
                $q->whereNull('gateway')->orWhere('gateway', $gateway->extension);
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if (self::ruleMatches($rule, $context)) {
                return $rule;
            }
        }

        return null;
    }

    /** Fee amount for a specific rule against a base total. */
    public static function feeForRule(PaymentFeeRule $rule, float $total): float
    {
        $fee = 0.0;

        if (in_array($rule->fee_type, ['fixed', 'both'], true)) {
            $fee += (float) $rule->fixed_amount;
        }
        if (in_array($rule->fee_type, ['percent', 'both'], true)) {
            $fee += $total * ((float) $rule->percent_amount / 100);
        }

        return round(max(0.0, $fee), 2);
    }

    // ── internals ───────────────────────────────────────────────────────────

    /** Build the matching context from the invoice + its customer + items. */
    private static function context(Gateway $gateway, Invoice $invoice): array
    {
        $user = $invoice->user;
        $props = $user ? $user->properties->pluck('value', 'key') : collect();

        // Customer type: 'business' if a company/CNPJ is present, else 'individual'.
        $customerType = ($props['company_name'] ?? $props['cnpj'] ?? null) ? 'business' : 'individual';

        $productIds = $invoice->items
            ->map(fn ($item) => optional(optional($item->reference)->product)->id ?? $item->product_id ?? null)
            ->filter()
            ->values()
            ->all();

        return [
            'country' => $props['country'] ?? null,
            'currency_code' => $invoice->currency_code,
            'product_ids' => $productIds,
            'customer_type' => $customerType,
            'total' => (float) $invoice->total,
        ];
    }

    private static function ruleMatches(PaymentFeeRule $rule, array $ctx): bool
    {
        if ($rule->country && !self::sameCountry($rule->country, $ctx['country'])) {
            return false;
        }
        if ($rule->currency_code && strtoupper($rule->currency_code) !== strtoupper((string) $ctx['currency_code'])) {
            return false;
        }
        if ($rule->customer_type && $rule->customer_type !== $ctx['customer_type']) {
            return false;
        }
        if ($rule->product_id && !in_array((int) $rule->product_id, array_map('intval', $ctx['product_ids']), true)) {
            return false;
        }
        if ($rule->min_amount !== null && $ctx['total'] < (float) $rule->min_amount) {
            return false;
        }
        if ($rule->max_amount !== null && $ctx['total'] > (float) $rule->max_amount) {
            return false;
        }

        return true;
    }

    /** Country may be stored as ISO-2 ("BR") or name ("Brazil"); compare loosely. */
    private static function sameCountry(string $ruleValue, ?string $ctxValue): bool
    {
        if ($ctxValue === null) {
            return false;
        }

        return strcasecmp(trim($ruleValue), trim($ctxValue)) === 0;
    }
}
