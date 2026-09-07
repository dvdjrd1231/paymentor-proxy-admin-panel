<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\CurrencyResource;
use App\Models\Currency;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #46 — WHMCS's Currencies screen: the intro, the navy grid, the update buttons,
 * and the Add Additional Currency inline form.
 *
 * Base Conv. Rate is a real stored column since 2026-09-07 (Leandro: "these pages don't
 * have 'Base Conv, Rate' Field. it is basic foundation to update these pages") — see the
 * `add_base_conv_rate_to_currencies` migration. That also splits WHMCS's two buttons into
 * the two different operations they name, which until now were one:
 *
 *  - **Update Exchange Rates** asks the provider for today's rates and stores them.
 *  - **Update Product Prices** rewrites secondary-currency prices from the rates already
 *    stored — including one an admin typed in here by hand.
 */
class CurrenciesList extends Page
{
    protected string $view = 'adminops::pages.currencies-list';

    protected static ?string $slug = 'currencies-list';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const FORMATS = ['1.000,00', '1,000.00', '1 000,00', '1 000.00'];

    public bool $adding = false;

    public string $newCode = '';

    public string $newName = '';

    public string $newPrefix = '';

    public string $newSuffix = '';

    public string $newFormat = '1,000.00';

    public string $newRate = '';

    public static function canAccess(): bool
    {
        return CurrencyResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Currencies';
    }

    /** The reference's own intro, verbatim. */
    public function getSubheading(): ?string
    {
        return 'You can sell in different currencies concurrently by setting them up below. '
            . 'Customers who visit your site can then choose to shop in their local currency.';
    }

    public function toggleAdding(): void
    {
        $this->adding = !$this->adding;
    }

    public function addCurrency(): void
    {
        if (!CurrencyResource::canCreate()) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $this->validate([
            'newCode' => 'required|string|size:3|unique:currencies,code',
            'newName' => 'nullable|string|max:64',
            'newPrefix' => 'nullable|string|max:8',
            'newSuffix' => 'nullable|string|max:8',
            'newFormat' => 'in:' . implode(',', self::FORMATS),
            'newRate' => 'nullable|numeric|gt:0',
        ], attributes: ['newCode' => 'currency code', 'newRate' => 'base conversion rate']);

        $currency = Currency::create([
            'code' => strtoupper($this->newCode),
            'name' => $this->newName ?: strtoupper($this->newCode),
            'prefix' => $this->newPrefix,
            'suffix' => $this->newSuffix ?: strtoupper($this->newCode),
            'format' => $this->newFormat,
        ]);

        // `base_conv_rate` is ours, so core's Currency model does not list it as fillable
        // and create() would drop it silently — the same trap the service `label` sprang.
        if ($this->newRate !== '') {
            $currency->forceFill(['base_conv_rate' => (float) $this->newRate])->save();
        }

        $this->reset(['adding', 'newCode', 'newName', 'newPrefix', 'newSuffix', 'newRate']);
        Notification::make()->title('Currency added')
            ->body('Give products a price in it, or let Currency Rates fill prices on its next sync.')
            ->success()->send();
    }

    /**
     * WHMCS's two update buttons.
     *
     * @param  bool  $fromStoredRates  false — Update Exchange Rates: ask the provider for
     *                                 today's rates. true — Update Product Prices: rewrite
     *                                 prices from the Base Conv. Rate already stored, which
     *                                 is what makes a hand-typed rate take effect.
     */
    public function updateRates(bool $fromStoredRates = false): void
    {
        $enabled = \App\Models\Extension::where('extension', 'CurrencyRates')->where('enabled', true)->exists();

        if (!$enabled || !class_exists(\Paymenter\Extensions\Others\CurrencyRates\CurrencyRates::class)) {
            Notification::make()->title('Currency Rates is not enabled')
                ->body('Enable the Currency Rates extension to sync exchange rates and product prices.')
                ->warning()->send();

            return;
        }

        try {
            $result = \App\Helpers\ExtensionHelper::getExtension('other', 'CurrencyRates',
                \App\Models\Extension::where('extension', 'CurrencyRates')->first()->settings ?? [])
                ->sync(false, $fromStoredRates);

            if ($fromStoredRates && ($result['rates'] ?? []) === []) {
                Notification::make()->title('No stored rates to price from')
                    ->body('Give each additional currency a Base Conv. Rate, or use Update Exchange Rates to fetch them.')
                    ->warning()->send();

                return;
            }

            Notification::make()
                ->title($fromStoredRates ? 'Product prices updated' : 'Exchange rates updated')
                ->body(collect($result)->map(fn ($v, $k) => "$k: " . (is_scalar($v) ? $v : json_encode($v)))->implode(' · ') ?: 'Sync completed.')
                ->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Sync failed')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        return [
            'baseCode' => (string) config('settings.default_currency'),
            'currencies' => Currency::orderBy('code')->get()->map(fn (Currency $currency) => [
                'row' => $currency,
                'edit' => CurrencyResource::canEdit($currency)
                    ? EditCurrency::getUrl(['record' => $currency->code])
                    : null,
            ]),
        ];
    }
}
