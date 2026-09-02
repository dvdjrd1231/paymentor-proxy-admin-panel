<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Classes\Settings as CoreSettings;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #39 — WHMCS's General Settings: the file-folder tab bar (General, Localisation,
 * Ordering, …, Other) over label-left rows with an inline hint per field. Every field is
 * one of Paymenter's real settings, read from {@see CoreSettings::settings()} and saved
 * exactly the way core's own Settings page saves — same Setting rows, same cache flush —
 * so the two pages can never disagree. Tabs whose WHMCS content has no Paymenter
 * equivalent (Ordering, Domains, Affiliates) say so instead of inventing fields.
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
        'domains' => 'Domains',
        'mail' => 'Mail',
        'support' => 'Support',
        'invoices' => 'Invoices',
        'credit' => 'Credit',
        'affiliates' => 'Affiliates',
        'security' => 'Security',
        'social' => 'Social',
        'other' => 'Other',
    ];

    /** Which of core's setting groups feed each tab. */
    private const GROUPS = [
        'general' => ['general', 'theme'],
        'localisation' => [],           // three settings pulled out of 'general' below
        'ordering' => [],
        'domains' => [],
        'mail' => ['mail'],
        'support' => ['tickets'],
        'invoices' => ['invoices', 'tax'],
        'credit' => ['credits'],
        'affiliates' => [],
        'security' => ['security'],
        'social' => ['social-login'],
        'other' => ['other', 'cronjob'],
    ];

    /** WHMCS keeps language/timezone under Localisation; core keeps them in 'general'. */
    private const LOCALISATION = ['timezone', 'app_language', 'allowed_languages'];

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
        foreach (collect(CoreSettings::settings())->flatten(1) as $setting) {
            if (in_array($setting['type'] ?? 'text', ['file', 'placeholder'], true)) {
                continue;
            }

            $this->values[$setting['name']] = config('settings.' . $setting['name'], $setting['default'] ?? null);
        }
    }

    /** The current tab's settings, in core's own order. */
    public function fields(): array
    {
        $all = CoreSettings::settings();

        if ($this->tab === 'localisation') {
            return array_values(array_filter(
                $all['general'] ?? [],
                fn (array $s) => in_array($s['name'], self::LOCALISATION, true),
            ));
        }

        $fields = [];
        foreach (self::GROUPS[$this->tab] ?? [] as $group) {
            foreach ($all[$group] ?? [] as $setting) {
                if ($this->tab === 'general' && in_array($setting['name'], self::LOCALISATION, true)) {
                    continue;
                }

                // Uploads (logos, favicon) stay on core's own form — a text box bound to
                // a file setting would only corrupt it.
                if (in_array($setting['type'] ?? 'text', ['file', 'placeholder'], true)) {
                    continue;
                }

                $fields[] = $setting;
            }
        }

        return $fields;
    }

    /** Saves core's way: same Setting rows, same change detection, same cache flush. */
    public function save(): void
    {
        Gate::authorize('has-permission', 'admin.settings.update');

        $definitions = collect(CoreSettings::settings())->flatten(1)->keyBy('name');
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
