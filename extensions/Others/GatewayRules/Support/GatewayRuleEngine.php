<?php

namespace Paymenter\Extensions\Others\GatewayRules\Support;

use App\Models\Gateway;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\GatewayRules\Models\GatewayRule;

/**
 * Server-side gateway-availability decision.
 *
 * Given a gateway and the checkout context ($total, $currency, $type, $items),
 * returns whether the gateway may be offered. First active rule (ascending
 * priority) whose scope matches decides via its mode (allow|deny). No matching
 * rule → available (true).
 */
class GatewayRuleEngine
{
    /**
     * @param  mixed  $items  invoice items / cart items (each may expose product/category)
     */
    public static function allows(Gateway $gateway, $total, $currency, $type = null, $items = []): bool
    {
        $context = self::context($total, $currency, $items);

        $rules = GatewayRule::where('active', true)
            ->where(function ($q) use ($gateway) {
                $q->whereNull('gateway')->orWhere('gateway', $gateway->extension);
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if (self::ruleMatches($rule, $context)) {
                return $rule->mode === 'allow';
            }
        }

        return true; // default: available
    }

    private static function context($total, $currency, $items): array
    {
        $user = Auth::user();
        $props = $user ? $user->properties->pluck('value', 'key') : collect();
        $customerType = ($props['company_name'] ?? $props['cnpj'] ?? null) ? 'business' : 'individual';

        $productIds = [];
        $categoryIds = [];
        foreach ($items ?? [] as $item) {
            // Items can be invoice items (reference->product) or cart items (product).
            $product = $item->product
                ?? (isset($item->reference) && $item->reference instanceof Service ? $item->reference->product : null);
            if ($product) {
                $productIds[] = (int) $product->id;
                if ($product->category_id) {
                    $categoryIds[] = (int) $product->category_id;
                }
            }
        }

        return [
            'country' => $props['country'] ?? null,
            'currency_code' => $currency,
            'customer_type' => $customerType,
            'product_ids' => array_values(array_unique($productIds)),
            'category_ids' => array_values(array_unique($categoryIds)),
            'total' => (float) $total,
        ];
    }

    private static function ruleMatches(GatewayRule $rule, array $ctx): bool
    {
        if ($rule->country && ($ctx['country'] === null || strcasecmp(trim($rule->country), trim((string) $ctx['country'])) !== 0)) {
            return false;
        }
        if ($rule->currency_code && strtoupper($rule->currency_code) !== strtoupper((string) $ctx['currency_code'])) {
            return false;
        }
        if ($rule->customer_type && $rule->customer_type !== $ctx['customer_type']) {
            return false;
        }
        if ($rule->product_id && !in_array((int) $rule->product_id, $ctx['product_ids'], true)) {
            return false;
        }
        if ($rule->category_id && !in_array((int) $rule->category_id, $ctx['category_ids'], true)) {
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
}
