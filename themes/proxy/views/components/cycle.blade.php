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

    // The catalogue sells its recurring cycles by the hour, so a month is 720 hours rather
    // than a period of one month, and the "period of 1" branch below never matched it. The
    // customer was shown "Every 720 services.billing_cycles.hour" — the raw key, because
    // core's list has no 'hour'. These are the counts EditProduct::CYCLES defines.
    $hourAdverbs = [24 => 'day', 168 => 'week', 720 => 'month'];

    if ($plan) {
        if ($plan->type === 'one-time' || $plan->type === 'free') {
            $label = __('theme.cycle.one_time');
        } elseif ($plan->billing_unit === 'hour' && isset($hourAdverbs[(int) $plan->billing_period])) {
            $label = __('theme.cycle.' . $hourAdverbs[(int) $plan->billing_period]);
        } elseif ((int) $plan->billing_period === 1 && $plan->billing_unit) {
            // 'theme.cycle.day' → "Daily". __() returns the key itself when it is
            // missing, so compare against the key to detect an unmapped unit.
            $key = 'theme.cycle.' . $plan->billing_unit;
            $label = __($key) === $key ? null : __($key);
        }

        // Anything else ("every 3 months") has no adverb — spell it out. The unit words are
        // the theme's own, not core's: core's list has no 'hour', and a missing key renders
        // as the key itself, which is what the customer was being shown.
        if ($label === null && $plan->billing_unit) {
            $unitKey = 'theme.cycle.units.' . $plan->billing_unit;
            $unit = __($unitKey) === $unitKey ? $plan->billing_unit : trans_choice($unitKey, (int) $plan->billing_period);

            $label = __('theme.cycle.every', [
                'period' => $plan->billing_period,
                'unit' => $unit,
            ]);
        }
    }
@endphp

@if ($label)
    <span {{ $attributes }}>{{ $label }}</span>
@endif
