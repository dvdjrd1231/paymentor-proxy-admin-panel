<?php

namespace Paymenter\Extensions\Others\CurrencyRates;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\CurrencyRates\Support\RateSync;

/**
 * Keeps secondary-currency prices in step with a published exchange rate.
 *
 * Paymenter stores a price per currency with no rate column, so selling in a second
 * currency means a second price row on every plan. Left to hand-editing those drift, and a
 * currency whose prices are missing makes products unbuyable for whoever selects it.
 *
 * This recomputes them from the base currency on a schedule, and refuses to touch a price
 * that has been edited by hand — see Support\RateSync for both rules.
 *
 * Rates come from open.er-api.com, which needs no API key. The scheduler runs the sync
 * hourly; exchange rates move slowly, so this is about not going stale rather than being
 * live to the minute.
 *
 * @link docs/modules/currency-rates.md
 */
#[ExtensionMeta(
    name: 'Currency Rates',
    description: 'Maintains prices in additional currencies from a published exchange rate.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class CurrencyRates extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString(
                    'Recomputes prices in the target currencies from the base currency, hourly. '
                    . 'A price you edit by hand is never overwritten, and a currency is only created '
                    . 'once it has prices.'
                ),
            ],
            [
                'name' => 'base_currency',
                'label' => 'Base currency',
                'type' => 'text',
                'description' => 'The currency your prices are authored in.',
                'default' => 'USD',
                'required' => true,
            ],
            [
                'name' => 'target_currencies',
                'label' => 'Target currencies',
                'type' => 'text',
                'description' => 'Comma separated, e.g. <code>BRL</code>. Each one is created with its prices on the next sync.',
                'default' => 'BRL',
                'required' => false,
            ],
            [
                'name' => 'markup_percent',
                'label' => 'FX buffer (%)',
                'type' => 'text',
                'description' => 'Added on top of the market rate to absorb movement between syncs. '
                    . '0 tracks the rate exactly.',
                'validation' => 'numeric',
                'default' => '0',
                'required' => false,
            ],
            [
                'name' => 'rounding',
                'label' => 'Rounding',
                'type' => 'select',
                'options' => [
                    'none' => 'Exact conversion (2 decimals)',
                    'whole' => 'Round up to a whole unit',
                    'ends_99' => 'Round up, ending .99',
                    'ends_90' => 'Round up, ending .90',
                ],
                'default' => 'none',
                'required' => false,
            ],
            [
                'name' => 'provider_url',
                'label' => 'Rate provider',
                'type' => 'text',
                'description' => 'Base currency is appended to this URL. Default needs no API key.',
                'default' => 'https://open.er-api.com/v6/latest',
                'required' => false,
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/CurrencyRates/database/migrations');
    }

    public function uninstalled()
    {
        // Prices already written stay — removing them would make the currency unbuyable,
        // which is the failure this module exists to avoid.
        ExtensionHelper::rollbackMigrations('extensions/Others/CurrencyRates/database/migrations');
    }

    public function boot()
    {
        // Registered against the scheduler that already runs every minute on the server, so
        // no extra cron entry is needed.
        app()->booted(function () {
            app(Schedule::class)
                ->call(fn () => $this->sync())
                ->hourly()
                ->name('currency-rates-sync')
                ->withoutOverlapping()
                ->onOneServer();
        });
    }

    /** Run one synchronisation. Safe to call from the scheduler or by hand. */
    public function sync(bool $dryRun = false): array
    {
        $targets = array_filter(array_map('trim', explode(',', (string) $this->config('target_currencies'))));

        if ($targets === []) {
            return ['rates' => [], 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'created' => []];
        }

        $sync = new RateSync(
            providerUrl: (string) ($this->config('provider_url') ?: 'https://open.er-api.com/v6/latest'),
            base: strtoupper((string) ($this->config('base_currency') ?: 'USD')),
            targets: $targets,
            markupPercent: (float) ($this->config('markup_percent') ?? 0),
            rounding: (string) ($this->config('rounding') ?: 'none'),
        );

        try {
            $result = $sync->run($dryRun);

            Log::channel('stack')->info('[CurrencyRates] sync complete', $result);

            return $result;
        } catch (\Throwable $e) {
            // A rate provider being down must not break the scheduler run; prices simply
            // stay at their last synced value, which is the safe outcome.
            Log::channel('stack')->error('[CurrencyRates] sync failed', ['error' => $e->getMessage()]);

            return ['rates' => [], 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'created' => [], 'error' => $e->getMessage()];
        }
    }
}
