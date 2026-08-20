{{--
    Billing cycle as an adverb — "Monthly", "Weekly", "One Time" — which is how the
    reference portal labels a price, rather than Paymenter's noun form ("month").

    Usage: <x-cycle :plan="$plan" />

    $plan may be null (a product with no available plan in the visitor's currency), in
    which case nothing is rendered rather than a bare "Every  ".
--}}
@props(['plan' => null])

@php
    $label = null;

    if ($plan) {
        if ($plan->type === 'one-time' || $plan->type === 'free') {
            $label = __('theme.cycle.one_time');
        } elseif ((int) $plan->billing_period === 1 && $plan->billing_unit) {
            // 'theme.cycle.day' → "Daily". __() returns the key itself when it is
            // missing, so compare against the key to detect an unmapped unit.
            $key = 'theme.cycle.' . $plan->billing_unit;
            $label = __($key) === $key ? null : __($key);
        }

        // Anything else ("every 3 months") has no adverb — spell it out.
        if ($label === null && $plan->billing_unit) {
            $label = __('theme.cycle.every', [
                'period' => $plan->billing_period,
                'unit' => trans_choice(__('services.billing_cycles.' . $plan->billing_unit), $plan->billing_period),
            ]);
        }
    }
@endphp

@if ($label)
    <span {{ $attributes }}>{{ $label }}</span>
@endif
