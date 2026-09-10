<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Admin\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Servers\ProxyPanel\Support\PanelApi;

/**
 * The panel's location catalogue, managed from Paymenter.
 *
 * A location is the unit of proxy capacity: it carries a pool of tunnels (`total`), how many
 * are in use (`used`) and how many remain (`free`), and it is what the Region select at
 * checkout is built from. Until now the only way to see or change any of that was the panel's
 * own UI, while the consequences — a region quietly going out of stock mid-campaign — landed
 * here. This puts the catalogue, its capacity and its on/off switch in the admin panel.
 *
 * Drawn on the WHMCS window standard (Leandro, 2026-09-09) rather than a Filament table:
 * the navy grid, the Search/Filter band, quiet row-action icons and the "Are you sure?"
 * modals every other converted screen uses. The rows still come from the panel over HTTP
 * on every load — nothing is stored here.
 *
 * @link docs/modules/proxypanel.md
 */
class PanelLocations extends Page
{
    protected string $view = 'servers.proxypanel::admin.panel-locations';

    /**
     * One segment, no slash. Filament builds a page's route name by replacing `/` with `.`
     * in the slug, so `panel/locations` produced the name `…pages.panel.locations`, which
     * the navigation item then failed to resolve — the sidebar 500'd while the page itself
     * rendered. Verified on the server.
     */
    protected static ?string $slug = 'panel-locations';

    protected static string|\UnitEnum|null $navigationGroup = 'Panel';

    protected static ?string $navigationLabel = 'Locations';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?int $navigationSort = 1;

    public const PER_PAGE = 25;

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    /**
     * Managing panel infrastructure is a strictly-more-dangerous thing than reading the
     * services built on it, so it is gated on the server permission rather than on being
     * able to see the admin panel at all.
     */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('admin.servers.view');
    }

    public function getTitle(): string
    {
        return 'Panel locations';
    }

    public function getSubheading(): ?string
    {
        $api = $this->api();

        if (!$api?->isConfigured()) {
            return null;
        }

        try {
            $rows = $api->locations();
        } catch (\Throwable $e) {
            return null;
        }

        $sellable = collect($rows)->filter(
            fn ($r) => ($r['status'] ?? 'enabled') === 'enabled' && (int) ($r['free'] ?? 0) > 0
        )->count();

        // Issue #44 ("why store the locations here if they already exist in adminProxies?"
        // then relabelled "I don't understand"): nothing is stored here. Said as plainly
        // as possible, on the page, where the question keeps being asked.
        return 'This page stores nothing. It is a live window into adminProxies: the same '
            . count($rows) . ' locations, read over the API each time, shown here because their '
            . 'stock and on/off switches decide which Regions customers can buy at checkout. '
            . 'The Tunnels / Used / Free counts are the panel\'s own figures at this moment — '
            . 'inventory is counted on the adminProxies side, and re-checked there again at '
            . 'the moment of provisioning. ' . $sellable . ' currently sellable.';
    }

    // ── Search/Filter band ───────────────────────────────────────────────────

    #[Url]
    public bool $filter = false;

    #[Url]
    public string $q = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $sellable = '';

    #[Url]
    public string $continent = '';

    #[Url]
    public int $page = 1;

    public function search(): void
    {
        $this->page = 1;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    // ── Modal state ──────────────────────────────────────────────────────────

    /** Tag whose full row (with provider priorities) is open in the view modal. */
    public ?string $viewing = null;

    /** Tag being edited, or null. */
    public ?string $editing = null;

    public bool $creating = false;

    /** @var array<string, string> location fields plus provider_prioN, bound in the modal */
    public array $form = [];

    /** @var array{tag: string, enable: bool}|null */
    public ?array $confirmToggle = null;

    public ?string $confirmDelete = null;

    /**
     * The providers this panel actually builds tunnels on, and the exact code length each
     * one demands.
     *
     * Taken from the live panel, not from `docs/client-brief/locations.md` — that document
     * is out of date and following it fails. Verified 2026-08-25 by round-tripping a
     * throwaway location through create/read/update/status/delete:
     *
     *   - sending `linode` is rejected outright: "Unexpected item 'linode'";
     *   - `sevencloud` is mandatory and undocumented: "The mandatory item
     *     'sevencloud › prio1' is missing";
     *   - every priority is mandatory and length-checked to the exact width below —
     *     an empty string fails with "expects to be in range 4..4, 0 bytes given".
     *
     * @var array<string, array{label: string, length: int, example: string}>
     */
    public const PROVIDERS = [
        'do' => ['label' => 'DigitalOcean', 'length' => 4, 'example' => 'nyc1'],
        'vultr' => ['label' => 'Vultr', 'length' => 3, 'example' => 'ewr'],
        'sevencloud' => ['label' => 'SevenCloud', 'length' => 6, 'example' => 'mci-00'],
    ];

    private const LOCATION_FIELDS = ['continent', 'country', 'country_name', 'state', 'city', 'region_code', 'zip_code'];

    public function openView(string $tag): void
    {
        $this->viewing = $tag;
    }

    public function openCreate(): void
    {
        $this->form = $this->blankForm();
        $this->creating = true;
        $this->editing = null;
        $this->resetValidation();
    }

    public function openEdit(string $tag): void
    {
        try {
            $detail = $this->api()->location($tag);
        } catch (\Throwable $e) {
            $detail = collect($this->allRows())->firstWhere('tag', $tag) ?? [];
        }

        $this->form = [...$this->blankForm(), ...array_filter($this->formStateFrom($detail), fn ($v) => $v !== null)];
        $this->editing = $tag;
        $this->creating = false;
        $this->resetValidation();
    }

    public function closeModals(): void
    {
        $this->reset(['viewing', 'editing', 'creating', 'confirmToggle', 'confirmDelete']);
    }

    public function saveLocation(): void
    {
        $rules = [
            'form.continent' => 'required|string',
            'form.country' => 'required|string|size:2',
            'form.country_name' => 'required|string',
            'form.state' => 'nullable|string',
            'form.city' => 'required|string',
            'form.region_code' => 'required|string',
            'form.zip_code' => 'nullable|string',
        ];
        $names = ['form.country' => 'country code', 'form.country_name' => 'country name', 'form.region_code' => 'region code'];

        // Every priority is mandatory and length-checked to the exact width the panel
        // demands — a typo comes back as a field error instead of a rejected round trip.
        foreach (self::PROVIDERS as $key => $provider) {
            foreach ([1, 2, 3] as $n) {
                $rules['form.' . $key . '_prio' . $n] = 'required|string|size:' . $provider['length'];
                $names['form.' . $key . '_prio' . $n] = $provider['label'] . ' priority ' . $n;
            }
        }

        $this->validate($rules, attributes: $names);

        $editing = $this->editing;

        $this->run(
            fn () => $editing
                ? $this->api()->updateLocation($editing, $this->payloadFrom($this->form))
                : $this->api()->createLocation($this->payloadFrom($this->form)),
            $editing ? $editing . ' updated' : 'Location created',
        );
    }

    public function askToggle(string $tag): void
    {
        $row = collect($this->allRows())->firstWhere('tag', $tag);

        if ($row) {
            $this->confirmToggle = ['tag' => $tag, 'enable' => ($row['status'] ?? 'enabled') !== 'enabled'];
        }
    }

    public function runToggle(): void
    {
        [$tag, $enable] = [$this->confirmToggle['tag'] ?? '', (bool) ($this->confirmToggle['enable'] ?? false)];
        $this->confirmToggle = null;

        if ($tag !== '') {
            $this->run(
                fn () => $this->api()->setLocationStatus($tag, $enable),
                $tag . ' ' . ($enable ? 'enabled' : 'disabled'),
            );
        }
    }

    public function askDelete(string $tag): void
    {
        $this->confirmDelete = $tag;
    }

    public function runDelete(): void
    {
        $tag = $this->confirmDelete;
        $this->confirmDelete = null;

        // A location with tunnels in use is capacity someone has paid for; the panel may
        // refuse anyway, but there is no reason to send the request and find out.
        $row = collect($this->allRows())->firstWhere('tag', $tag);

        if (!$row || (int) ($row['used'] ?? 0) > 0) {
            Notification::make()->title('Cannot delete')
                ->body('This location has tunnels in use — capacity someone has paid for.')
                ->danger()->send();

            return;
        }

        $this->run(fn () => $this->api()->deleteLocation($tag), $tag . ' deleted');
    }

    // ── Rows ─────────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> every location, unfiltered */
    private function allRows(): array
    {
        $api = $this->api();

        if (!$api?->isConfigured()) {
            return [];
        }

        try {
            return array_values(array_map(function (array $row): array {
                $row['status'] ??= 'enabled';

                return $row;
            }, $api->locations()));
        } catch (\Throwable $e) {
            // The banner in the view explains it; an exception here would blank the page.
            return [];
        }
    }

    /**
     * The page of rows the grid draws: filtered and paginated in PHP — the panel's list
     * endpoint takes only `page`, so the whole catalogue (~246 rows, memoised per request
     * by PanelApi) is fetched and worked on here.
     */
    private function paginated(): LengthAwarePaginator
    {
        $rows = collect($this->allRows());

        if ($this->q !== '') {
            $needle = mb_strtolower($this->q);
            $rows = $rows->filter(function (array $row) use ($needle): bool {
                foreach (['tag', 'country_name', 'city', 'state', 'continent', 'country'] as $field) {
                    if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($this->status !== '') {
            $rows = $rows->filter(fn (array $r): bool => ($r['status'] ?? 'enabled') === $this->status);
        }

        if ($this->continent !== '') {
            $rows = $rows->filter(fn (array $r): bool => ($r['continent'] ?? '') === $this->continent);
        }

        if ($this->sellable !== '') {
            $rows = $rows->filter(function (array $r): bool {
                $total = (int) ($r['total'] ?? 0);
                $free = (int) ($r['free'] ?? 0);
                $enabled = ($r['status'] ?? 'enabled') === 'enabled';

                return match ($this->sellable) {
                    'sellable' => $enabled && $free > 0,
                    'out' => $total > 0 && (!$enabled || $free < 1),
                    'empty' => $total < 1,
                    default => true,
                };
            });
        }

        $rows = $rows->sortBy(fn (array $row) => mb_strtolower((string) ($row['tag'] ?? '')))->values();

        return new LengthAwarePaginator(
            $rows->forPage($this->page, self::PER_PAGE)->values()->all(),
            $rows->count(),
            self::PER_PAGE,
            $this->page,
        );
    }

    /** @return array<int, string> */
    private function continents(): array
    {
        return collect($this->allRows())->pluck('continent')->filter()->unique()->sort()->values()->all();
    }

    // ── Panel plumbing ───────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function blankForm(): array
    {
        $form = array_fill_keys(self::LOCATION_FIELDS, '');

        foreach (array_keys(self::PROVIDERS) as $provider) {
            foreach ([1, 2, 3] as $n) {
                $form[$provider . '_prio' . $n] = '';
            }
        }

        return $form;
    }

    /** Panel row → flat form state. */
    private function formStateFrom(array $detail): array
    {
        $state = [];

        foreach (self::LOCATION_FIELDS as $field) {
            $state[$field] = $detail[$field] ?? null;
        }

        foreach (array_keys(self::PROVIDERS) as $provider) {
            foreach (['prio1', 'prio2', 'prio3'] as $prio) {
                $state[$provider . '_' . $prio] = $detail[$provider][$prio] ?? null;
            }
        }

        return $state;
    }

    /** Flat form state → the nested body the panel documents. */
    private function payloadFrom(array $data): array
    {
        $payload = [];

        foreach (self::LOCATION_FIELDS as $field) {
            $payload[$field] = (string) ($data[$field] ?? '');
        }

        // `linode` is deliberately absent: the panel rejects the whole request if it is
        // present, even empty. See the PROVIDERS docblock.
        foreach (array_keys(self::PROVIDERS) as $provider) {
            $payload[$provider] = [
                'prio1' => (string) ($data[$provider . '_prio1'] ?? ''),
                'prio2' => (string) ($data[$provider . '_prio2'] ?? ''),
                'prio3' => (string) ($data[$provider . '_prio3'] ?? ''),
            ];
        }

        return $payload;
    }

    /**
     * Run a panel call, report either way, and drop the cached list.
     *
     * Every mutating action goes through here so a panel refusal always surfaces as a
     * notification rather than a 500 — the panel answers some failures with HTTP 200 and
     * others with an HTML error page, and neither should reach the operator raw.
     */
    private function run(callable $call, string $success): void
    {
        try {
            $call();

            Notification::make()->title($success)->success()->send();

            $this->api = null;
            $this->closeModals();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('The panel refused that')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    private ?PanelApi $api = null;

    private function api(): ?PanelApi
    {
        return $this->api ??= PanelApi::resolve();
    }

    protected function getViewData(): array
    {
        $api = $this->api();
        $error = null;

        if (!$api) {
            $error = 'No ProxyPanel server is configured. Add one under Admin → Servers.';
        } elseif (!$api->isConfigured()) {
            $error = 'The ProxyPanel server has no API URL or token set.';
        } else {
            try {
                $api->locations();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $viewDetail = null;

        if ($this->viewing !== null) {
            try {
                $viewDetail = $this->api()->location($this->viewing);
            } catch (\Throwable $e) {
                $viewDetail = ['error' => $e->getMessage()];
            }
        }

        $locations = $this->paginated();

        if ($this->page > 1 && $locations->isEmpty()) {
            $this->page = max(1, $locations->lastPage());
            $locations = $this->paginated();
        }

        return [
            'error' => $error,
            'locations' => $locations,
            'continents' => $this->continents(),
            'viewDetail' => $viewDetail,
        ];
    }
}
