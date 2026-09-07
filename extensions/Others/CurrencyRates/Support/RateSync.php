<?php

namespace Paymenter\Extensions\Others\CurrencyRates\Support;

use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps secondary-currency prices in step with a published exchange rate.
 *
 * Paymenter has no exchange-rate column — every price is stored per currency — so a second
 * currency means a second price row per plan. Maintaining those by hand goes stale; this
 * recomputes them from the base currency on a schedule.
 *
 * Two rules keep it safe:
 *
 *  - **A hand-edited price is never overwritten.** Each row this module writes is recorded
 *    in `currency_rate_prices`. If the stored price no longer matches what was last written,
 *    someone changed it deliberately and the sync skips it from then on.
 *  - **A currency is never registered without prices.** Offering a currency with no prices
 *    makes every product unbuyable for anyone who selects it, so the currency row is created
 *    in the same transaction as its first prices, never before.
 */
class RateSync
{
    public function __construct(
        private string $providerUrl,
        private string $base,
        private array $targets,
        private float $markupPercent = 0.0,
        private string $rounding = 'none',
    ) {}

    /** Currency presentation defaults, so a created currency renders sensibly. */
    private const PRESENTATION = [
        'BRL' => ['name' => 'Brazilian Real', 'prefix' => 'R$', 'format' => '1.000,00'],
        'EUR' => ['name' => 'Euro', 'prefix' => '€', 'format' => '1.000,00'],
        'GBP' => ['name' => 'Pound Sterling', 'prefix' => '£', 'format' => '1,000.00'],
        'USD' => ['name' => 'US Dollar', 'prefix' => '$', 'format' => '1,000.00'],
    ];

    /**
     * @param  array<string,float>|null  $overrideRates  Use these rates instead of asking the
     *                                                   provider — the Currencies screen's
     *                                                   "Update Product Prices", which rewrites
     *                                                   prices from the Base Conv. Rate an admin
     *                                                   set by hand. Already-effective values, so
     *                                                   the FX buffer is not applied a second time.
     * @return array{rates: array<string,float>, updated: int, unchanged: int, skipped: int, created: array<string>}
     */
    public function run(bool $dryRun = false, ?array $overrideRates = null): array
    {
        $rates = $overrideRates ?? $this->fetchRates();
        $summary = ['rates' => [], 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'created' => []];

        foreach ($this->targets as $code) {
            $code = strtoupper(trim($code));

            if ($code === '' || $code === $this->base) {
                continue;
            }

            if (!isset($rates[$code])) {
                Log::channel('stack')->warning('[CurrencyRates] no rate available', ['currency' => $code]);
                continue;
            }

            $rate = $overrideRates === null
                ? (float) $rates[$code] * (1 + $this->markupPercent / 100)
                : (float) $rates[$code];
            $summary['rates'][$code] = round($rate, 6);

            $result = $this->syncCurrency($code, $rate, $dryRun);

            if (!$dryRun) {
                $this->recordRate($code, $rate);
            }

            $summary['updated'] += $result['updated'];
            $summary['unchanged'] += $result['unchanged'];
            $summary['skipped'] += $result['skipped'];

            if ($result['currencyCreated']) {
                $summary['created'][] = $code;
            }
        }

        if (!$dryRun) {
            // The base converts to itself at 1, which is what the reference's grid shows
            // against it. Written here rather than left to the migration so it stays true
            // after someone changes which currency is the base.
            $this->recordRate($this->base, 1.0);
        }

        return $summary;
    }

    /**
     * Keep the rate that was actually used in `currencies.base_conv_rate`, so the
     * Currencies screen shows a real number instead of a dash and prices can later be
     * rewritten from it. Skipped silently when the column is absent — AdminOps owns the
     * migration that adds it, and this module has to keep working without it.
     */
    private function recordRate(string $code, float $rate): void
    {
        try {
            if (!Schema::hasColumn('currencies', 'base_conv_rate')) {
                return;
            }

            $currency = Currency::where('code', $code)->first();
            $rate = round($rate, 8);

            if (!$currency || (float) $currency->base_conv_rate === $rate) {
                // Nothing moved. Worth checking, because saving flushes the whole cache
                // (core's Currency does that on every save) and the rate usually holds
                // between hourly passes.
                return;
            }

            // Through the model, not DB::table: core caches currency reads and only
            // invalidates on a model save, so a direct UPDATE writes the row and then
            // hands every reader the old value — which is exactly what the first live
            // test showed, the grid still reading 5.42 after a sync stored 5.22451.
            // `base_conv_rate` is ours and not in core's $fillable, hence forceFill.
            $currency->forceFill(['base_conv_rate' => $rate])->save();
        } catch (\Throwable $e) {
            Log::channel('stack')->warning('[CurrencyRates] could not record the rate', [
                'currency' => $code, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string,float> */
    private function fetchRates(): array
    {
        $url = rtrim($this->providerUrl, '/') . '/' . $this->base;
        $response = Http::timeout(20)->retry(2, 300, throw: false)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Exchange rate provider returned HTTP ' . $response->status());
        }

        // open.er-api.com answers {result: success, rates: {...}}; be tolerant of a
        // provider that nests them differently rather than failing on a shape change.
        $rates = $response->json('rates') ?? $response->json('data.rates') ?? [];

        if (!is_array($rates) || $rates === []) {
            throw new \RuntimeException('Exchange rate provider returned no rates.');
        }

        return $rates;
    }

    /** @return array{updated: int, unchanged: int, skipped: int, currencyCreated: bool} */
    private function syncCurrency(string $code, float $rate, bool $dryRun): array
    {
        $basePrices = DB::table('prices')->where('currency_code', $this->base)->get();
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;
        $currencyCreated = false;

        foreach ($basePrices as $basePrice) {
            $target = $this->round((float) $basePrice->price * $rate);

            $tracked = DB::table('currency_rate_prices')
                ->where('plan_id', $basePrice->plan_id)->where('currency_code', $code)->first();

            $existing = DB::table('prices')
                ->where('plan_id', $basePrice->plan_id)->where('currency_code', $code)->first();

            // Someone edited this price by hand — leave it alone permanently.
            if ($existing && $tracked && (float) $existing->price !== (float) $tracked->auto_price) {
                $skipped++;
                continue;
            }

            // A price that exists with no tracking row was not created here either.
            if ($existing && !$tracked) {
                $skipped++;
                continue;
            }

            // The rate moves in small steps and this runs hourly, so most passes compute
            // the value already stored. Writing it again would churn the table and make the
            // log read as though something changed.
            if ($existing && (float) $existing->price === $target) {
                $unchanged++;
                continue;
            }

            if ($dryRun) {
                $updated++;
                continue;
            }

            DB::transaction(function () use ($code, $basePrice, $target, $rate, $existing, &$currencyCreated) {
                // Create the currency only now that there is a price to go with it.
                if (!Currency::where('code', $code)->exists()) {
                    $p = self::PRESENTATION[$code] ?? ['name' => $code, 'prefix' => '', 'format' => '1,000.00'];
                    Currency::create([
                        'code' => $code, 'name' => $p['name'],
                        'prefix' => $p['prefix'], 'suffix' => '', 'format' => $p['format'],
                    ]);
                    $currencyCreated = true;
                }

                if ($existing) {
                    DB::table('prices')->where('id', $existing->id)->update(['price' => $target]);
                } else {
                    DB::table('prices')->insert([
                        'plan_id' => $basePrice->plan_id,
                        'currency_code' => $code,
                        'price' => $target,
                        'setup_fee' => 0,
                    ]);
                }

                DB::table('currency_rate_prices')->updateOrInsert(
                    ['plan_id' => $basePrice->plan_id, 'currency_code' => $code],
                    [
                        'auto_price' => $target,
                        'rate_used' => $rate,
                        'synced_at' => now(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            });

            $updated++;
        }

        return ['updated' => $updated, 'unchanged' => $unchanged, 'skipped' => $skipped, 'currencyCreated' => $currencyCreated];
    }

    /** Rounding styles a shop actually uses, rather than raw conversion output. */
    private function round(float $value): float
    {
        return match ($this->rounding) {
            'whole' => (float) ceil($value),
            'ends_99' => max(0.99, ceil($value) - 0.01),
            'ends_90' => max(0.90, ceil($value) - 0.10),
            default => round($value, 2),
        };
    }
}
