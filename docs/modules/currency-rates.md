# Currency Rates

Keeps prices in additional currencies in step with a published exchange rate.

Paymenter stores a price per currency and has no rate column, so selling in a second
currency means a second price row on every plan. Maintained by hand those go stale, and a
currency whose prices are missing makes every product unbuyable for whoever selects it.

## What it does

Every hour it fetches the rate, recomputes each target-currency price from the base
currency, and writes only what changed.

## Two rules that make it safe

**A hand-edited price is never overwritten.** Every row this module writes is recorded in
`currency_rate_prices`. If the stored price no longer matches what was last written, someone
changed it on purpose and the sync skips it permanently. Verified: a price edited to
R$399.00 survived a sync, reported as `skipped 1`.

**A currency is never registered without prices.** The currency row is created in the same
transaction as its first prices — never before. Registering a currency ahead of its prices
is what made the store unbuyable in an earlier iteration.

## Settings — Admin → Extensions → Currency Rates

| Setting | Meaning |
|---|---|
| Base currency | the currency prices are authored in (`USD`) |
| Target currencies | comma separated, e.g. `BRL` |
| FX buffer (%) | added on top of the market rate to absorb movement between syncs |
| Rounding | exact, whole units, `.99`, or `.90` |
| Rate provider | base currency is appended; the default needs no API key |

## Rate source

`https://open.er-api.com/v6/latest/USD` — free, no key, returns `{rates: {...}}`. Any
provider with that shape works; the fetcher also accepts `data.rates`.

If the provider is unreachable the run logs an error and stops. Prices stay at their last
synced value, which is the safe outcome — never zero, never missing.

## Schedule

Registered against Laravel's scheduler, which already runs every minute on the server, so
no extra cron entry is needed:

    Schedule::call(sync)->hourly()->withoutOverlapping()->onOneServer()

Rates move slowly; hourly is about not going stale, not about being live to the minute.

## Running it by hand

```php
$ext = App\Helpers\ExtensionHelper::getExtension('other', 'CurrencyRates',
    App\Models\Extension::where('extension', 'CurrencyRates')->first()->settings);

$ext->sync(true);   // dry run — reports what would change
$ext->sync();       // apply
```

Returns `['rates' => [...], 'updated' => n, 'unchanged' => n, 'skipped' => n, 'created' => [...]]`.
`skipped` is hand-edited prices left alone; `unchanged` is prices already correct.
