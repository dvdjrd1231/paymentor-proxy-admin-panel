<?php

namespace App\Admin\Pages;

use App\Classes\FilamentInput;
use App\Classes\Settings as ClassesSettings;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $title = 'Settings';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-settings-3-line';

    protected static string|\BackedEnum|null $activeNavigationIcon = 'ri-settings-3-fill';

    protected string $view = 'admin.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $setting_values = [];
        foreach (ClassesSettings::settings() as $group => $settings) {
            foreach ($settings as $setting) {
                $setting_values[$setting['name']] = config("settings.{$setting['name']}", $setting['default'] ?? null);
            }
        }

        $this->form->fill($setting_values);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (ClassesSettings::settings() as $key => $categories) {
            $tab = Tab::make($key)
                ->label(ucwords(str_replace('-', ' ', $key)))
                ->schema(function () use ($categories) {
                    $inputs = [];
                    foreach ($categories as $setting) {
                        $inputs[] = FilamentInput::convert($setting);
                    }

                    return $inputs;
                });

            $tabs[] = $tab;
        }

        return $schema
            ->components([
                Form::make([
                    Tabs::make('Tabs')
                        ->tabs($tabs)
                        ->persistTabInQueryString(),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Say which field blocked the save, and where it lives.
     *
     * Every tab is validated on submit, but only the open tab is on screen. A bad value on
     * another tab — `ticket_mail_email` set to "support" is the one that prompted this —
     * therefore rejected the form while the admin, sitting on Security, saw a Save button
     * that simply did nothing: no message, no highlight, nothing in the log, because the
     * request never reached save().
     *
     * Filament's own inline error still renders on the offending field; this adds the
     * pointer to it, naming the setting and the tab so it can be found without hunting
     * through all eleven.
     */
    public function onValidationError(ValidationException $exception): void
    {
        $tabs = collect(ClassesSettings::settings())
            ->flatMap(fn ($settings, $group) => collect($settings)->mapWithKeys(
                fn ($setting) => [$setting['name'] => ucwords(str_replace('-', ' ', $group))]
            ));

        $fields = collect($exception->validator->errors()->keys())
            // Errors are keyed by the form's state path, e.g. `data.ticket_mail_email`.
            ->map(fn ($key) => str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : $key)
            ->unique()
            ->map(fn ($name) => isset($tabs[$name]) ? "{$name} ({$tabs[$name]} tab)" : $name);

        Notification::make()
            ->title('Not saved — check ' . $fields->implode(', '))
            ->body($exception->validator->errors()->first())
            ->danger()
            ->persistent()
            ->send();
    }

    public function save(): void
    {
        Gate::authorize('has-permission', 'admin.settings.update');

        $data = $this->form->getState();

        $settings = Setting::where('settingable_type', null)
            ->whereIn('key', array_keys($data))
            ->get()
            ->keyBy('key');

        foreach ($data as $key => $value) {
            // Get only the settings that have changed.
            //
            // The null check has to happen before the object cast, not after: `(object) null`
            // produces an empty stdClass, which is truthy, so the guard as written never
            // fired once and a stale field still reached the property writes below.
            $definition = collect(ClassesSettings::settings())->flatten(1)->firstWhere('name', $key);

            if (!$definition) {
                // Ignore stale fields from a previous settings schema instead of breaking the
                // entire Livewire save request.
                continue;
            }

            $avSetting = (object) $definition;
            $avSetting->value = $settings[$key]->value ?? $avSetting->default ?? null;

            if ($value !== $avSetting->value || (($avSetting->database_type ?? 'string') === 'boolean' && (bool) $value !== (bool) $avSetting->value)) {
                if ($setting = $settings[$key] ?? null) {
                    $setting->update([
                        'value' => $value,
                        'type' => $avSetting->database_type ?? 'string',
                        'encrypted' => $avSetting->encrypted ?? false,
                    ]);
                } else {
                    Setting::create([
                        'key' => $key,
                        'value' => $value,
                        'settingable_type' => null,
                        'type' => $avSetting->database_type ?? 'string',
                        'encrypted' => $avSetting->encrypted ?? false,
                    ]);
                }
            }
        }

        // SettingsProvider caches the complete settings collection. Flush it so the next
        // request, queue worker, and auth page see the values just saved.
        ClassesSettings::flushCache();

        Notification::make()
            ->title('Saved successfully!')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        /** @var User */
        $user = auth()->user();

        return $user && $user->hasPermission('admin.settings.view');
    }
}
