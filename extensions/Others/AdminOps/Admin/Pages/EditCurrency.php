<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\CurrencyResource;
use App\Models\Currency;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's currency editor (Leandro, 2026-09-07: "currencies-list and currencies/BRL/edit
 * Update these pages to be same as target WHMCS screenshot. Also, these pages don't have
 * 'Base Conv, Rate' Field").
 */
class EditCurrency extends Page
{
    protected string $view = 'adminops::pages.edit-currency';

    protected static ?string $slug = 'currency';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Same reasoning as {@see EditInvoice::$invoice} — not `$record`. */
    public Currency $currency;

    public string $name = '';

    public string $prefix = '';

    public string $suffix = '';

    public string $format = '1,000.00';

    public string $rate = '';

    /** The reference's "Update Pricing" — reprice this currency from the rate on save. */
    public bool $updatePricing = false;

    public bool $confirmingDelete = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record}';
    }

    public static function canAccess(): bool
    {
        return CurrencyResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Edit Currency — ' . $this->currency->code;
    }

    public function mount(string $record): void
    {
        abort_unless(static::canAccess(), 403);

        $this->currency = Currency::where('code', strtoupper($record))->firstOrFail();
        $this->name = (string) $this->currency->name;
        $this->prefix = (string) $this->currency->prefix;
        $this->suffix = (string) $this->currency->suffix;
        $this->format = (string) $this->currency->format;
        $this->rate = $this->currency->base_conv_rate === null
            ? ''
            : rtrim(rtrim(number_format((float) $this->currency->base_conv_rate, 8, '.', ''), '0'), '.');
    }

    /** True for the currency everything else is priced against. */
    public function isBase(): bool
    {
        return $this->currency->code === (string) config('settings.default_currency');
    }

    public function save(): void
    {
        abort_unless(CurrencyResource::canEdit($this->currency), 403);

        $this->validate([
            'name' => 'required|string|max:255',
            'prefix' => 'nullable|string|max:10',
            'suffix' => 'nullable|string|max:10',
            // Rule::in — see the note in {@see CurrenciesList::addCurrency}; the formats
            // contain commas, which the "in:a,b" string form treats as separators.
            'format' => [\Illuminate\Validation\Rule::in(CurrenciesList::FORMATS)],
            'rate' => $this->isBase() ? 'nullable' : 'nullable|numeric|gt:0',
        ], attributes: ['rate' => 'base conversion rate']);

        $this->currency->fill([
            'name' => $this->name,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'format' => $this->format,
        ]);

        // Ours, so not in core's $fillable — forceFill or it is dropped without a word.
        $this->currency->forceFill([
            'base_conv_rate' => $this->isBase()
                ? 1
                : ($this->rate === '' ? null : (float) $this->rate),
        ])->save();

        if ($this->updatePricing && !$this->isBase() && $this->rate !== '') {
            $this->repriceFromRate();

            return;
        }

        Notification::make()->title('Currency updated')
            ->body($this->isBase() || $this->rate === ''
                ? null
                : 'Use Update Product Prices on the Currencies screen to reprice from this rate.')
            ->success()->send();
    }

    /** Rewrite this one currency's product prices from the rate just saved. */
    private function repriceFromRate(): void
    {
        $this->updatePricing = false;

        if (!class_exists(\Paymenter\Extensions\Others\CurrencyRates\Support\RateSync::class)) {
            Notification::make()->title('Currency updated')
                ->body('The Currency Rates extension is not installed, so prices were left as they are.')
                ->warning()->send();

            return;
        }

        try {
            $sync = new \Paymenter\Extensions\Others\CurrencyRates\Support\RateSync(
                providerUrl: '',
                base: (string) config('settings.default_currency'),
                targets: [$this->currency->code],
                markupPercent: 0,
                rounding: 'none',
            );
            $result = $sync->run(false, [$this->currency->code => (float) $this->rate]);

            Notification::make()->title('Currency updated and prices recalculated')
                ->body(($result['updated'] ?? 0) . ' price(s) rewritten at ' . $this->rate . ' ' . $this->currency->code . ' per ' . config('settings.default_currency') . '.')
                ->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Saved, but repricing failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function delete()
    {
        abort_unless(CurrencyResource::canDelete($this->currency), 403);

        if ($this->isBase()) {
            Notification::make()->title('The default currency cannot be deleted')
                ->body('Change the default under System Settings first.')->danger()->send();
            $this->confirmingDelete = false;

            return null;
        }

        // A currency still carrying services, orders or credits cannot go: those rows point
        // at it by code, and removing it would leave money referencing nothing.
        $inUse = collect(['services', 'orders', 'credits'])
            ->first(fn (string $relation) => $this->currency->{$relation}()->exists());

        if ($inUse) {
            Notification::make()->title('Currency is in use')
                ->body('There are ' . $inUse . ' priced in ' . $this->currency->code . '. Move them first.')
                ->danger()->send();
            $this->confirmingDelete = false;

            return null;
        }

        $code = $this->currency->code;
        $this->currency->delete();

        Notification::make()->title($code . ' deleted')->success()->send();

        return redirect()->to(CurrenciesList::getUrl());
    }
}
