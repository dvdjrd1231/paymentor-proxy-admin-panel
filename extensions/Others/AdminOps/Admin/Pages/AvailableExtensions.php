<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ExtensionResource;
use App\Helpers\ExtensionHelper;
use App\Models\Extension;
use App\Services\Extensions\UploadExtensionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Available Extensions on the house standard (Leandro, 2026-09-07: "Adjust all Paymenter
 * admin windows to the current standard", of `/admin/extensions/extension?tab=installable`).
 *
 * Core's own screen could not be brought to the standard with CSS. Two of its problems are
 * in the markup: its heading is the raw class name — "Extension" — and it draws a *second
 * sidebar inside the content*, repeating the two entries the left rail already carries.
 * So this is that screen rebuilt, same treatment as Products, Gateways, Currencies and
 * Roles before it, and core's URL redirects here.
 *
 * Everything it does, this does: browse the marketplace, install what is already on disk,
 * and upload a zip. Install and upload go through core's own
 * {@see ExtensionHelper} and {@see UploadExtensionService} rather than reimplementing
 * either — this page is the chrome, not a second installer.
 */
class AvailableExtensions extends Page
{
    use WithFileUploads;

    protected string $view = 'adminops::pages.available-extensions';

    protected static ?string $slug = 'available-extensions';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** The reference's two tabs; `tab=installable` is core's own name for the second. */
    #[Url]
    public string $tab = 'marketplace';

    #[Url]
    public string $q = '';

    /** all | gateway | server | other */
    #[Url]
    public string $type = 'all';

    public int $shown = self::PER_PAGE;

    private const PER_PAGE = 12;

    /** Set when the marketplace cannot be reached — shown instead of an empty grid. */
    public ?string $error = null;

    public $upload;

    /** Which on-disk extension is awaiting its "Are you sure?". */
    public ?string $confirming = null;

    public static function canAccess(): bool
    {
        return ExtensionResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Available Extensions';
    }

    public function getSubheading(): ?string
    {
        return 'Browse the Paymenter marketplace, install an extension already on this server, '
            . 'or upload one as a zip.';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function loadMore(): void
    {
        $this->shown += self::PER_PAGE;
    }

    public function updatedQ(): void
    {
        $this->shown = self::PER_PAGE;
    }

    public function updatedType(): void
    {
        $this->shown = self::PER_PAGE;
    }

    /**
     * The marketplace listing, cached for six hours exactly as core caches it — and under
     * core's own key, so the two screens never disagree and a warm cache stays warm across
     * the switch.
     *
     * @return array<int, array<string, mixed>>
     */
    public function marketplace(): array
    {
        try {
            $all = Cache::remember('paymenter_marketplace_extensions', now()->addHours(6), function () {
                $response = Http::timeout(15)
                    ->withUserAgent('Paymenter/' . config('app.version') . ' (https://paymenter.org)')
                    ->get('https://api.paymenter.org/extensions', ['limit' => 999]);

                if (!$response->successful()) {
                    Log::error('Paymenter Marketplace API request failed', [
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                return $response->json('extensions', []);
            });
        } catch (\Throwable $exception) {
            Log::error('Paymenter Marketplace API connection failed: ' . $exception->getMessage());
            $this->error = 'Failed to connect to the Paymenter Marketplace. Check this server\'s internet connection.';

            return [];
        }

        if ($all === null) {
            $this->error = 'The Paymenter Marketplace is currently unavailable. Please try again later.';

            return [];
        }

        return collect($all)
            ->when($this->q !== '', fn ($c) => $c->filter(fn ($i) => stripos($i['name'] ?? '', $this->q) !== false))
            ->when($this->type !== 'all', fn ($c) => $c->where('type', $this->type))
            ->values()->all();
    }

    /**
     * Extensions sitting on disk that have no row yet — core's "Ready to Install".
     *
     * @return array<int, array<string, mixed>>
     */
    public function installable(): array
    {
        return array_values(ExtensionHelper::getInstallableExtensions());
    }

    public function install(): void
    {
        $name = $this->confirming;
        $this->confirming = null;

        if (!$name || !ExtensionResource::canCreate()) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $record = collect($this->installable())->firstWhere('name', $name);

        if (!$record) {
            Notification::make()->title('Extension not found')
                ->body('It may have been installed already.')->warning()->send();

            return;
        }

        $extension = Extension::create([
            'name' => $record['name'],
            'type' => $record['type'],
            'extension' => $record['name'],
        ]);

        // Core's install path, verbatim. The migrations then follow from AdminOps's own
        // enable hook — see AdminOps::keepExtensionMigrationsApplied(), which is why a
        // freshly installed extension now has its tables.
        ExtensionHelper::call($extension, 'installed', mayFail: true);

        Notification::make()->title('Extension installed')
            ->body('Enable it and set it up on the Extensions screen.')->success()->send();

        $this->redirect(ExtensionResource::getUrl('edit', ['record' => $extension->id]), true);
    }

    /** The reference's Upload Extension — core's own service does the unpacking. */
    public function uploadExtension(): void
    {
        if (!ExtensionResource::canCreate()) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $this->validate([
            'upload' => 'required|file|mimes:zip|max:10240',
        ], attributes: ['upload' => 'extension file']);

        try {
            $stored = $this->upload->store('extensions/uploaded');
            $type = app(UploadExtensionService::class)->handle(storage_path('app/' . $stored));

            $where = match ($type) {
                'server' => 'Set it up under Setup → Servers.',
                'gateway' => 'Set it up under Setup → Payment Gateways.',
                default => 'It is now on the Ready to Install tab.',
            };

            $this->reset('upload');
            $this->tab = $type === 'other' ? 'installable' : $this->tab;

            Notification::make()->title('Extension uploaded')->body($where)->success()->send();
        } catch (\Throwable $exception) {
            Notification::make()->title('Failed to upload extension')
                ->body($exception->getMessage())->danger()->send();
        }
    }
}
