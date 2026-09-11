<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Classes\Settings as CoreSettings;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Logo, dark logo and favicon (Leandro, 2026-09-11). The three settings have always been
 * there — core declares them in App\Classes\Settings' general group — but nothing in this
 * admin reached them: General Settings skips every file setting, and core's own form is
 * off the menu. This is that form, on the account menu where Leandro looked for it.
 */
class Branding extends Page
{
    use WithFileUploads;

    protected string $view = 'adminops::pages.branding';

    protected static ?string $slug = 'branding';

    /** Navigation is the account menu's, not the sidebar's. */
    protected static bool $shouldRegisterNavigation = false;

    /**
     * The three core settings, with the file name core stores each under. Keeping its
     * names means an upload here and an upload on core's form are the same file.
     *
     * @var array<string, array{label: string, file: string, accept: string, hint: string}>
     */
    public const IMAGES = [
        'logo' => [
            'label' => 'Logo',
            'file' => 'logo-light.webp',
            'accept' => 'image/*',
            'hint' => 'Shown on light backgrounds, across the admin and the client area.',
        ],
        'logo_dark' => [
            'label' => 'Dark Logo',
            'file' => 'logo-dark.webp',
            'accept' => 'image/*',
            'hint' => 'Used where the background is dark. Falls back to the logo above.',
        ],
        'favicon' => [
            'label' => 'Favicon',
            'file' => 'favicon.ico',
            'accept' => 'image/x-icon,image/png,image/svg+xml',
            'hint' => 'The browser tab icon. A .ico, .png or .svg file.',
        ],
    ];

    /** @var array<string, TemporaryUploadedFile|null> */
    public array $uploads = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.settings.view');
    }

    public function getTitle(): string
    {
        return 'Branding';
    }

    /** What is on the public disk now, as a URL the page can show. */
    public function currentUrl(string $key): ?string
    {
        $value = config('settings.' . $key);

        if (!$value || !Storage::disk('public')->exists($value)) {
            return null;
        }

        // Cache-busted: the file name never changes, so a browser that has seen the old
        // logo would go on showing it after an upload.
        return Storage::url($value) . '?v=' . Storage::disk('public')->lastModified($value);
    }

    public function save(): void
    {
        abort_unless((bool) Auth::user()?->hasPermission('admin.settings.update'), 403);

        $this->validate(
            collect(self::IMAGES)->mapWithKeys(fn (array $image, string $key): array => [
                'uploads.' . $key => ['nullable', 'file', 'max:4096', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/x-icon,image/vnd.microsoft.icon'],
            ])->all(),
            attributes: collect(self::IMAGES)->mapWithKeys(fn (array $image, string $key): array => [
                'uploads.' . $key => strtolower($image['label']),
            ])->all(),
        );

        $saved = 0;

        foreach (self::IMAGES as $key => $image) {
            $file = $this->uploads[$key] ?? null;

            if (!$file instanceof TemporaryUploadedFile) {
                continue;
            }

            // Core's own name for the file, on core's own disk, so whichever form uploaded
            // it there is only ever one of each.
            $file->storeAs('', $image['file'], ['disk' => 'public']);

            Setting::updateOrCreate(
                ['key' => $key, 'settingable_id' => null, 'settingable_type' => null],
                ['value' => $image['file'], 'type' => 'file', 'encrypted' => false],
            );

            $this->uploads[$key] = null;
            $saved++;
        }

        if ($saved === 0) {
            Notification::make()->title('Nothing to save')->body('Choose an image first.')->warning()->send();

            return;
        }

        CoreSettings::flushCache();

        Notification::make()
            ->title($saved === 1 ? 'Image saved' : $saved . ' images saved')
            ->body('The panel picks the new artwork up on the next page load.')
            ->success()->send();
    }

    /** Drop one back to the default, which is the app name as text. */
    public function clear(string $key): void
    {
        abort_unless((bool) Auth::user()?->hasPermission('admin.settings.update'), 403);
        abort_unless(array_key_exists($key, self::IMAGES), 404);

        // The row goes, not the file: core reads the setting, and leaving the upload on
        // disk means a clear can be undone by re-saving without re-uploading.
        Setting::where('key', $key)->whereNull('settingable_type')->delete();

        CoreSettings::flushCache();

        Notification::make()->title(self::IMAGES[$key]['label'] . ' removed')->success()->send();
    }
}
