<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\TaxRateResource;
use App\Models\Setting;
use App\Models\TaxRate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's Tax Configuration screen, to Leandro's screenshots of
 * `setup/payments/tax` (2026-09-08): four tabs over one page.
 */
class TaxConfiguration extends Page
{
    protected string $view = 'adminops::pages.tax-configuration';

    protected static ?string $slug = 'tax-configuration';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** general | vat | rules | advanced */
    public string $tab = 'general';

    public bool $taxEnabled = false;

    /** exclusive | inclusive */
    public string $taxType = 'exclusive';

    public bool $customNumbering = false;

    public string $numberFormat = '';

    public string $nextNumber = '';

    public string $numberPadding = '';

    /** The Quick Add band. */
    public array $rule = ['name' => 'Tax', 'rate' => '', 'country' => 'all'];

    public ?int $confirming = null;

    public static function canAccess(): bool
    {
        return TaxRateResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Tax Configuration';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->load();
    }

    private function load(): void
    {
        $this->taxEnabled = (bool) config('settings.tax_enabled');
        $this->taxType = config('settings.tax_type') === 'inclusive' ? 'inclusive' : 'exclusive';

        $this->numberFormat = (string) (config('settings.invoice_number_format') ?? '');
        $this->nextNumber = (string) (config('settings.invoice_number') ?? '');
        $this->numberPadding = (string) (config('settings.invoice_number_padding') ?? '');

        // "Custom" means a format has been set. With none, invoices are numbered by their
        // own id, which is the platform's default rather than a scheme someone chose.
        $this->customNumbering = $this->numberFormat !== '';
    }

    /** Write one store-wide setting. */
    private function put(string $key, mixed $value): void
    {
        $row = Setting::whereNull('settingable_type')->where('key', $key)->first();

        if ($row) {
            $row->update(['value' => $value]);
        } else {
            Setting::create(['key' => $key, 'value' => $value, 'settingable_type' => null, 'type' => 'string']);
        }
    }

    public function saveGeneral(): void
    {
        $this->validate([
            'taxType' => 'required|in:exclusive,inclusive',
            'numberFormat' => 'nullable|string|max:255',
            'nextNumber' => 'nullable|integer|min:1',
            'numberPadding' => 'nullable|integer|min:0|max:12',
        ], attributes: [
            'taxType' => 'taxation type', 'numberFormat' => 'invoice number format',
            'nextNumber' => 'next invoice number', 'numberPadding' => 'invoice number padding',
        ]);

        $this->put('tax_enabled', $this->taxEnabled ? '1' : '0');
        $this->put('tax_type', $this->taxType);

        // Unticking Custom Invoice Numbering clears the format rather than remembering it,
        // because a format left behind would come back the moment the box was ticked and
        // silently renumber the next invoice.
        $this->put('invoice_number_format', $this->customNumbering ? $this->numberFormat : '');
        $this->put('invoice_number', $this->nextNumber);
        $this->put('invoice_number_padding', $this->numberPadding);

        // Settings are cached; without this the page re-reads the old values on the very
        // next request and the save looks like it did nothing.
        \App\Classes\Settings::flushCache();

        $this->load();

        Notification::make()->title('Tax settings saved')->success()->send();
    }

    /** The reference's Quick Add: a name, a rate and the country it applies to. */
    public function addRule(): void
    {
        abort_unless(TaxRateResource::canCreate(), 403);

        $this->validate([
            'rule.name' => 'required|string|max:255',
            'rule.rate' => 'required|numeric|min:0|max:999.99',
            'rule.country' => 'required|string|max:8',
        ], attributes: ['rule.name' => 'name', 'rule.rate' => 'tax rate', 'rule.country' => 'country']);

        // One rate per country: the column is unique, and a second row would be a rule
        // that silently never fires.
        if (TaxRate::where('country', $this->rule['country'])->exists()) {
            $this->addError('rule.country', 'There is already a rate for that country. Delete it first, or edit it below.');

            return;
        }

        TaxRate::create([
            'name' => $this->rule['name'],
            'rate' => (float) $this->rule['rate'],
            'country' => $this->rule['country'],
        ]);

        $this->rule = ['name' => 'Tax', 'rate' => '', 'country' => 'all'];

        Notification::make()->title('Rule added')->success()->send();
    }

    public function runDelete(): void
    {
        $id = $this->confirming;
        $this->reset('confirming');

        $rate = TaxRate::find($id);

        if (!$rate || !TaxRateResource::canDelete($rate)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $rate->delete();

        Notification::make()->title('Rule deleted')->success()->send();
    }

    protected function getViewData(): array
    {
        $countries = config('app.countries');
        unset($countries['']);

        return [
            'countries' => $countries,
            'rates' => TaxRate::orderBy('country')->get(),
            'canManage' => TaxRateResource::canCreate(),
            'editUrl' => fn (TaxRate $rate) => TaxRateResource::canEdit($rate)
                ? TaxRateResource::getUrl('edit', ['record' => $rate])
                : null,
        ];
    }
}
