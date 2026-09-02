<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\ServerResource;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #43 — WHMCS's Servers screen as the navy list. The reference's usage columns are
 * kept honest: "Panel Usage" is live tunnel capacity read from the proxy panel (the same
 * numbers Panel Locations shows), and "Products / Services" is what this installation
 * actually runs through the module. WHMCS's server *groups* have no Paymenter equivalent —
 * a product attaches to one module directly — and the page says so instead of drawing an
 * empty table that could never fill.
 */
class ServersList extends Page
{
    protected string $view = 'adminops::pages.servers-list';

    protected static ?string $slug = 'servers-list';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public ?int $confirming = null;

    public bool $confirmEnable = false;

    public static function canAccess(): bool
    {
        return ServerResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Servers';
    }

    public function getSubheading(): ?string
    {
        return 'This is where you configure the server modules Paymenter provisions services '
            . 'through. Each product is attached to one module on its own edit page.';
    }

    public function confirm(int $id, bool $enable): void
    {
        $this->confirming = $id;
        $this->confirmEnable = $enable;
    }

    public function run(): void
    {
        [$id, $enable] = [$this->confirming, $this->confirmEnable];
        $this->reset(['confirming', 'confirmEnable']);

        $server = Server::find($id);

        if (!$server || !ServerResource::canEdit($server)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $server->update(['enabled' => $enable]);

        Notification::make()
            ->title($enable ? 'Server enabled' : 'Server disabled')
            ->body($enable
                ? $server->name . ' provisions again.'
                : $server->name . ' stops provisioning new services. Running services are untouched.')
            ->success()->send();
    }

    protected function getViewData(): array
    {
        $servers = Server::orderBy('name')->get()->map(fn (Server $server) => [
            'row' => $server,
            'products' => Product::where('server_id', $server->id)->count(),
            'services' => Service::whereIn('product_id', Product::where('server_id', $server->id)->pluck('id'))
                ->where('status', Service::STATUS_ACTIVE)->count(),
            'usage' => $this->usage($server),
            'edit' => ServerResource::canEdit($server) ? ServerResource::getUrl('edit', ['record' => $server]) : null,
        ]);

        return [
            'servers' => $servers,
            'newUrl' => ServerResource::canCreate() ? ServerResource::getUrl('create') : null,
        ];
    }

    /**
     * Live capacity for the ProxyPanel module — the panel's own tunnel totals, the same
     * numbers Panel Locations reads. Cached for a minute so this list never hangs on the
     * panel; any failure shows a dash rather than an error.
     */
    private function usage(Server $server): ?string
    {
        if ($server->extension !== 'ProxyPanel') {
            return null;
        }

        return Cache::remember('adminops.server-usage.' . $server->id, 60, function (): ?string {
            try {
                $api = \Paymenter\Extensions\Servers\ProxyPanel\Support\PanelApi::resolve();

                if (!$api?->isConfigured()) {
                    return null;
                }

                $rows = collect($api->locations());

                if ($rows->isEmpty()) {
                    return null;
                }

                $used = $rows->sum(fn ($row) => (int) ($row['used'] ?? 0));
                $total = $rows->sum(fn ($row) => (int) ($row['total'] ?? 0));

                return number_format($used) . ' of ' . number_format($total) . ' tunnels in use';
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
