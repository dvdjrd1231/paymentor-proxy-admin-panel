<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Classes\Settings as CoreSettings;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\SettingsReference;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #39 - WHMCS's General Settings, to Leandro's screenshots of configgeneral.php
 * (2026-09-07): the file-folder tab bar over label-left rows, each with the reference's
 * own hint.
 *
 * ## How a field gets here
 *
 * {@see REFERENCE} lists the reference's fields, tab by tab, in its order. Each entry
 * either names a real Paymenter setting - read from {@see CoreSettings::settings()} and
 * saved exactly the way core's own Settings page saves, so the two can never disagree -
 * or carries a `why`, in which case it renders disabled with that reason on it.
 *
 * The disabled entries are the point, not padding: WHMCS has settings for features this
 * platform does not have, and a tab that quietly omits them looks complete while leaving
 * an admin hunting for a switch that was never there.
 *
 * **Domains is deliberately absent.** Domains were removed from this store entirely
 * (section 10 of the brief), so the whole tab would be disabled rows - agreed with
 * Leandro, 2026-09-07, along with omitting MarketConnect, Apps & Integrations, Sign-In
 * Integrations and Fraud Protection, which are WHMCS services rather than settings.
 */
class GeneralSettings extends Page
{
    protected string $view = 'adminops::pages.general-settings';

    protected static ?string $slug = 'general-settings';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** The reference's tab bar, in its order. */
    public const TABS = [
        'general' => 'General',
        'localisation' => 'Localisation',
        'ordering' => 'Ordering',
        'mail' => 'Mail',
        'support' => 'Support',
        'invoices' => 'Invoices',
        'credit' => 'Credit',
        'security' => 'Security',
        'social' => 'Social',
        'other' => 'Other',
    ];

    #[Url(as: 'tab')]
    public string $tab = 'general';

    /** @var array<string, mixed> */
    public array $values = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.settings.view');
    }

    public function getTitle(): string
    {
        return 'General Settings';
    }

    public function mount(): void
    {
        foreach ($this->definitions() as $setting) {
            if (in_array($setting['type'] ?? 'text', ['file', 'placeholder'], true)) {
                continue;
            }

            $this->values[$setting['name']] = config('settings.' . $setting['name'], $setting['default'] ?? null);
        }
    }

    /**
     * The current tab's rows, in the reference's order.
     *
     * A row either resolves to a real core setting — carrying its type, options and
     * default — or is a disabled row with the reason it cannot be offered here. Naming a
     * setting that does not exist degrades to a disabled row rather than throwing, so a
     * core upgrade that renames one shows a gap instead of a 500.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        $definitions = $this->definitions();
        $rows = [];

        foreach (SettingsReference::all()[$this->tab] ?? [] as $row) {
            $name = $row['setting'] ?? null;
            $definition = $name ? ($definitions[$name] ?? null) : null;

            if ($name && !$definition) {
                $rows[] = [
                    'label' => $row['label'],
                    'why' => 'This setting is not present in the installed Paymenter version.',
                ];

                continue;
            }

            if (!$definition) {
                $rows[] = ['label' => $row['label'], 'why' => $row['why'] ?? ''];

                continue;
            }

            // Uploads stay on core's own form: a text box bound to a file setting would
            // only corrupt it.
            if (in_array($definition['type'] ?? 'text', ['file', 'placeholder'], true)) {
                $rows[] = [
                    'label' => $row['label'],
                    'why' => 'This is an uploaded file — set it under Setup → System Settings.',
                ];

                continue;
            }

            // array_merge, not `+`: the union operator keeps the LEFT operand's keys, so
            // `$definition + [...]` silently kept core's own label and the screen read
            // "System Email" and "App URL" where the reference says Email Address and
            // Domain. The reference's wording is the whole point of this page.
            $rows[] = array_merge($definition, [
                'label' => $row['label'],
                'hint' => $row['hint'] ?? ($definition['description'] ?? null),
            ]);
        }

        return $rows;
    }

    /**
     * Every setting this page can bind to: core's own, plus the handful this extension
     * declares for fields Paymenter has no setting behind ({@see SettingsReference::own}).
     *
     * One place, because mount(), fields() and save() must agree about what exists — if
     * save() did not know about a key, the control would accept input and quietly drop it.
     *
     * @return \Illuminate\Support\Collection<string, array<string, mixed>>
     */
    private function definitions(): \Illuminate\Support\Collection
    {
        return collect(CoreSettings::settings())->flatten(1)->keyBy('name')
            ->merge(SettingsReference::own());
    }

    /** Saves core's way: same Setting rows, same change detection, same cache flush. */
    public function save(): void
    {
        Gate::authorize('has-permission', 'admin.settings.update');

        $definitions = $this->definitions();
        $stored = Setting::whereNull('settingable_type')
            ->whereIn('key', array_keys($this->values))
            ->get()
            ->keyBy('key');

        foreach ($this->values as $key => $value) {
            $definition = $definitions[$key] ?? null;

            if (!$definition || in_array($definition['type'] ?? 'text', ['file', 'placeholder'], true)) {
                continue;
            }

            $current = $stored[$key]->value ?? $definition['default'] ?? null;
            $boolean = ($definition['database_type'] ?? 'string') === 'boolean';

            if ($value === $current && (!$boolean || (bool) $value === (bool) $current)) {
                continue;
            }

            if ($row = $stored[$key] ?? null) {
                $row->update([
                    'value' => $value,
                    'type' => $definition['database_type'] ?? 'string',
                    'encrypted' => $definition['encrypted'] ?? false,
                ]);
            } else {
                Setting::create([
                    'key' => $key,
                    'value' => $value,
                    'settingable_type' => null,
                    'type' => $definition['database_type'] ?? 'string',
                    'encrypted' => $definition['encrypted'] ?? false,
                ]);
            }
        }

        CoreSettings::flushCache();

        Notification::make()->title('Saved successfully!')->success()->send();
    }

    protected function getViewData(): array
    {
        return ['fields' => $this->fields()];
    }
}
