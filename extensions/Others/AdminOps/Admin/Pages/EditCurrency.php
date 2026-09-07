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
 *
 * Core's own `/admin/currencies/{code}/edit` is a bare Filament resource form with five
 * fields and no rate; this is the reference's field set on the reference's chrome, and
 * that URL now lands here — see {@see \Paymenter\Extensions\Others\AdminOps\AdminOps}.
 * Everything core's form could do, this does too, so nothing is lost by the redirect.
 *
 * The base currency's own row is the one place the reference locks down: its code cannot
 * change (core's EditCurrency refuses the same thing) and its rate is 1 by definition, so
 * both are shown read-only with the reason on them rather than as editable fields that
 * quietly refuse to save.
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
            'format' => 'in:' . implode(',', CurrenciesList::FORMATS),
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

        Notification::make()->title('Currency updated')
            ->body($this->isBase() || $this->rate === ''
                ? null
                : 'Use Update Product Prices on the Currencies screen to reprice from this rate.')
            ->success()->send();
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
