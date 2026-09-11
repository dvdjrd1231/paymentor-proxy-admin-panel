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
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        return $this->fieldsFor($this->tab);
    }

    /**
     * Every tab's rows at once, so the page can switch tabs without asking the server.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function fieldsByTab(): array
    {
        $byTab = [];

        foreach (array_keys(self::TABS) as $tab) {
            $byTab[$tab] = $this->fieldsFor($tab);
        }

        return $byTab;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fieldsFor(string $tab): array
    {
        $definitions = $this->definitions();
        $rows = [];

        foreach (SettingsReference::all()[$tab] ?? [] as $row) {
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
        return ['fieldsByTab' => $this->fieldsByTab()];
    }
}
