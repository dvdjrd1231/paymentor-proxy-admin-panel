<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\CurrencyResource;
use App\Models\Currency;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #46 — WHMCS's Currencies screen: the intro, the navy grid, the update buttons,
 * and the Add Additional Currency inline form. WHMCS's two buttons (Update Exchange
 * Rates / Update Product Prices) are one real operation here: the CurrencyRates
 * extension pulls the published rate and rewrites the secondary-currency prices in one
 * sync, because Paymenter stores a price per currency rather than a conversion rate.
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
        ], attributes: ['newCode' => 'currency code']);

        Currency::create([
            'code' => strtoupper($this->newCode),
            'name' => $this->newName ?: strtoupper($this->newCode),
            'prefix' => $this->newPrefix,
            'suffix' => $this->newSuffix ?: strtoupper($this->newCode),
            'format' => $this->newFormat,
        ]);

        $this->reset(['adding', 'newCode', 'newName', 'newPrefix', 'newSuffix']);
        Notification::make()->title('Currency added')
            ->body('Give products a price in it, or let Currency Rates fill prices on its next sync.')
            ->success()->send();
    }

    /** WHMCS's two update buttons as the one real operation both describe. */
    public function updateRates(): void
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
                \App\Models\Extension::where('extension', 'CurrencyRates')->first()->settings ?? [])->sync();
            Notification::make()->title('Rates and prices updated')
                ->body(collect($result)->map(fn ($v, $k) => "$k: " . (is_scalar($v) ? $v : json_encode($v)))->implode(' · ') ?: 'Sync completed.')
                ->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Sync failed')->body($e->getMessage())->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        return [
            'currencies' => Currency::orderBy('code')->get()->map(fn (Currency $currency) => [
                'row' => $currency,
                'edit' => CurrencyResource::canEdit($currency)
                    ? CurrencyResource::getUrl('edit', ['record' => $currency])
                    : null,
            ]),
        ];
    }
}
