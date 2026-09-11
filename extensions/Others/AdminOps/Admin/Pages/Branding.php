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
     * The three core settings, with the base file name core stores each under. Its own
     * form renames whatever is uploaded to logo-light.webp / logo-dark.webp / favicon.ico
     * regardless of what the file actually is, which leaves a PNG sitting in a .ico and a
     * browser refusing to draw it — so the real extension is kept and the setting records
     * the full name, which is what core reads back.
     *
     * @var array<string, array{label: string, file: string, accept: string, hint: string}>
     */
    public const IMAGES = [
        'logo' => [
            'label' => 'Logo',
            'file' => 'logo-light',
            'accept' => 'image/*',
            'hint' => 'Shown on light backgrounds, across the admin and the client area.',
        ],
        'logo_dark' => [
            'label' => 'Dark Logo',
            'file' => 'logo-dark',
            'accept' => 'image/*',
            'hint' => 'Used where the background is dark. Falls back to the logo above.',
        ],
        'favicon' => [
            'label' => 'Favicon',
            'file' => 'favicon',
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

            $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
            $name = $image['file'] . '.' . $extension;

            // A second upload in a different format would otherwise leave the first file
            // behind on the disk with nothing pointing at it.
            $previous = (string) config('settings.' . $key);

            if ($previous !== '' && $previous !== $name) {
                Storage::disk('public')->delete($previous);
            }

            $file->storeAs('', $name, ['disk' => 'public']);

            Setting::updateOrCreate(
                ['key' => $key, 'settingable_id' => null, 'settingable_type' => null],
                ['value' => $name, 'type' => 'file', 'encrypted' => false],
            );

            // config was read at boot, so flushing the cache alone leaves this request
            // still holding the old value — the preview would go on showing the picture
            // that has just been replaced.
            config(['settings.' . $key => $name]);

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

        // The file goes with the row. Leaving it behind would mean an orphan on the disk
        // that nothing points at and nothing ever cleans up.
        if ($current = (string) config('settings.' . $key)) {
            Storage::disk('public')->delete($current);
        }

        Setting::where('key', $key)->whereNull('settingable_type')->delete();

        CoreSettings::flushCache();

        // Same reason as save(): without this the row reappears with its picture and its
        // Remove button until the page is loaded again, which reads as "nothing happened".
        config(['settings.' . $key => null]);

        Notification::make()->title(self::IMAGES[$key]['label'] . ' removed')->success()->send();
    }
}
