<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Models\Currency;

/** Formats an amount in a store currency, the way the rest of Paymenter does. */
class Money
{
    /**
     * Currencies keyed by code, loaded once per request.
     *
     * @var array<string, Currency>|null
     */
    private static ?array $currencies = null;

    /** Render an amount in the given currency code. */
    public static function format(float|int|null $amount, ?string $code): string
    {
        $amount ??= 0;
        $currency = static::currency($code);

        if (!$currency) {
            return number_format((float) $amount, 2, '.', ',');
        }

        $number = match ($currency->format) {
            '1.000,00' => number_format((float) $amount, 2, ',', '.'),
            '1 000,00' => number_format((float) $amount, 2, ',', ' '),
            '1 000.00' => number_format((float) $amount, 2, '.', ' '),
            default => number_format((float) $amount, 2, '.', ','),
        };

        return $currency->prefix . $number . $currency->suffix;
    }

    /**
     * Render a set of per-currency totals as one string.
     *
     * @param  array<string, float>  $totals  amount keyed by currency code
     */
    public static function formatTotals(array $totals, ?string $zeroCurrency = null): string
    {
        $totals = array_filter($totals, fn ($amount) => round((float) $amount, 2) != 0.0);

        if (empty($totals)) {
            return static::format(0, $zeroCurrency ?: config('settings.default_currency'));
        }

        $parts = [];

        foreach ($totals as $code => $amount) {
            $parts[] = static::format($amount, $code);
        }

        return implode(' · ', $parts);
    }

    private static function currency(?string $code): ?Currency
    {
        if (!$code) {
            return null;
        }

        static::$currencies ??= Currency::all()->keyBy('code')->all();

        return static::$currencies[$code] ?? null;
    }
}
