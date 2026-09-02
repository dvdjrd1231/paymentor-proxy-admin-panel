<?php

namespace Paymenter\Extensions\Servers\ProxyPanel\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
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
 * The table is backed by `Table::records()` rather than a query: the rows come from the panel
 * over HTTP, not from our database, and Filament's array data source gives search, sort,
 * filters and pagination over them with no local copy to go stale.
 *
 * @link docs/modules/proxypanel.md
 * @link docs/client-brief/locations.md
 */
class PanelLocations extends Page implements HasTable
{
    use InteractsWithTable;

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
            . $sellable . ' currently sellable.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, array $sort, int $page, int $recordsPerPage): LengthAwarePaginator => $this->rows($search, $sort, $page, $recordsPerPage))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('tag')
                    ->label('Tag')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country_name')
                    ->label('Country')
                    ->description(fn (array $record): ?string => $record['state'] ?: null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')->label('City')->searchable()->sortable(),
                TextColumn::make('continent')->label('Continent')->sortable()->toggleable(),
                TextColumn::make('total')->label('Tunnels')->numeric()->sortable()->alignRight(),
                TextColumn::make('used')->label('Used')->numeric()->sortable()->alignRight(),
                TextColumn::make('free')
                    ->label('Free')
                    ->numeric()
                    ->sortable()
                    ->alignRight()
                    // Free capacity is the number that decides whether a region can be sold,
                    // so it is coloured rather than left as one more figure in a row.
                    ->color(fn (array $record): string => match (true) {
                        (int) ($record['free'] ?? 0) > 0 => 'success',
                        (int) ($record['total'] ?? 0) > 0 => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'enabled'))
                    ->color(fn (?string $state): string => ($state ?? 'enabled') === 'enabled' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['enabled' => 'Enabled', 'disabled' => 'Disabled']),
                SelectFilter::make('sellable')
                    ->label('Availability')
                    ->options([
                        'sellable' => 'Sellable now',
                        'out' => 'Out of stock',
                        'empty' => 'No tunnels',
                    ]),
                SelectFilter::make('continent')->options(fn (): array => $this->continents()),
            ])
            ->recordActions([
                $this->viewAction(),
                $this->toggleStatusAction(),
                $this->editAction(),
                $this->deleteAction(),
            ])
            ->defaultSort('tag')
            ->emptyStateHeading('No locations')
            ->emptyStateDescription('The panel returned no locations, or it could not be reached.');
    }

    /**
     * Rows for the table: filtered, sorted and paginated in PHP.
     *
     * The panel's list endpoint takes only `page` — there is no server-side search, sort or
     * filter to delegate to — so the whole catalogue (246 rows, 3 requests) is fetched and
     * worked on here. `PanelApi` memoises the fetch for the request.
     *
     * A `LengthAwarePaginator` is returned rather than a plain collection because Filament
     * renders exactly what the data source hands back: returning all 246 rows produced a
     * 5.5 MB page. Slicing here keeps it to one screen's worth.
     */
    private function rows(?string $search, array $sort, int $page, int $perPage): LengthAwarePaginator
    {
        $api = $this->api();
        $empty = fn (): LengthAwarePaginator => new LengthAwarePaginator([], 0, max(1, $perPage), $page);

        if (!$api?->isConfigured()) {
            return $empty();
        }

        try {
            $rows = collect($api->locations());
        } catch (\Throwable $e) {
            // The banner in the view explains it; an exception here would blank the page.
            return $empty();
        }

        $rows = $rows->map(function (array $row): array {
            $row['status'] ??= 'enabled';
            // Filament keys array records by `__key`; the tag is the panel's own identifier
            // and the one every other endpoint addresses a location by.
            $row['__key'] = (string) ($row['tag'] ?? $row['id'] ?? '');

            return $row;
        })->filter(fn (array $row): bool => $row['__key'] !== '');

        if (filled($search)) {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function (array $row) use ($needle): bool {
                foreach (['tag', 'country_name', 'city', 'state', 'continent', 'country'] as $field) {
                    if (str_contains(mb_strtolower((string) ($row[$field] ?? '')), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        $rows = $this->applyFilters($rows);

        [$column, $direction] = [$sort[0] ?? 'tag', $sort[1] ?? 'asc'];
        $numeric = in_array($column, ['total', 'used', 'free'], true);

        $rows = $rows->sortBy(
            fn (array $row) => $numeric ? (int) ($row[$column] ?? 0) : mb_strtolower((string) ($row[$column] ?? '')),
            SORT_REGULAR,
            $direction === 'desc',
        )->values();

        $perPage = max(1, $perPage);

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->all(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    private function applyFilters(Collection $rows): Collection
    {
        $status = $this->tableFilters['status']['value'] ?? null;
        $sellable = $this->tableFilters['sellable']['value'] ?? null;
        $continent = $this->tableFilters['continent']['value'] ?? null;

        if (filled($status)) {
            $rows = $rows->filter(fn (array $r): bool => ($r['status'] ?? 'enabled') === $status);
        }

        if (filled($continent)) {
            $rows = $rows->filter(fn (array $r): bool => ($r['continent'] ?? '') === $continent);
        }

        if (filled($sellable)) {
            $rows = $rows->filter(function (array $r) use ($sellable): bool {
                $total = (int) ($r['total'] ?? 0);
                $free = (int) ($r['free'] ?? 0);
                $enabled = ($r['status'] ?? 'enabled') === 'enabled';

                return match ($sellable) {
                    'sellable' => $enabled && $free > 0,
                    'out' => $total > 0 && (!$enabled || $free < 1),
                    'empty' => $total < 1,
                    default => true,
                };
            });
        }

        return $rows;
    }

    /** @return array<string, string> */
    private function continents(): array
    {
        try {
            return collect($this->api()?->locations() ?? [])
                ->pluck('continent')
                ->filter()
                ->unique()
                ->sort()
                ->mapWithKeys(fn (string $c): array => [$c => $c])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    /**
     * The full row, fetched per-location rather than taken from the list.
     *
     * `GET /locations/{tag}` is the only endpoint that returns the DigitalOcean / Linode /
     * Vultr region priorities, which are what actually decide where a tunnel gets built.
     */
    private function viewAction(): Action
    {
        return Action::make('view')
            ->label('View')
            ->icon(Heroicon::Eye)
            ->color('gray')
            ->modalHeading(fn (array $record): string => 'Location ' . $record['tag'])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (array $record) {
                try {
                    $detail = $this->api()->location($record['tag']);
                } catch (\Throwable $e) {
                    $detail = ['error' => $e->getMessage()];
                }

                return view('servers.proxypanel::admin.location-detail', ['detail' => $detail, 'row' => $record]);
            });
    }

    private function toggleStatusAction(): Action
    {
        return Action::make('toggleStatus')
            ->label(fn (array $record): string => ($record['status'] ?? 'enabled') === 'enabled' ? 'Disable' : 'Enable')
            ->icon(fn (array $record): string => ($record['status'] ?? 'enabled') === 'enabled'
                ? 'heroicon-m-pause-circle'
                : 'heroicon-m-play-circle')
            ->color(fn (array $record): string => ($record['status'] ?? 'enabled') === 'enabled' ? 'warning' : 'success')
            ->requiresConfirmation()
            ->modalDescription(fn (array $record): string => ($record['status'] ?? 'enabled') === 'enabled'
                ? 'Disabling removes this location from checkout. Services already running there are not affected.'
                : 'Enabling offers this location at checkout again, if it has free tunnels.')
            ->action(function (array $record): void {
                $enable = ($record['status'] ?? 'enabled') !== 'enabled';

                $this->run(
                    fn () => $this->api()->setLocationStatus($record['tag'], $enable),
                    $record['tag'] . ' ' . ($enable ? 'enabled' : 'disabled'),
                );
            });
    }

    private function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->icon(Heroicon::PencilSquare)
            ->color('gray')
            ->fillForm(function (array $record): array {
                try {
                    $detail = $this->api()->location($record['tag']);
                } catch (\Throwable $e) {
                    $detail = $record;
                }

                return $this->formStateFrom($detail);
            })
            ->schema(fn (): array => $this->locationForm())
            ->modalHeading(fn (array $record): string => 'Edit ' . $record['tag'])
            ->modalSubmitActionLabel('Save')
            ->action(function (array $record, array $data): void {
                $this->run(
                    fn () => $this->api()->updateLocation($record['tag'], $this->payloadFrom($data)),
                    $record['tag'] . ' updated',
                );
            });
    }

    private function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $record): string => 'Delete ' . $record['tag'] . '?')
            ->modalDescription('This removes the location from the panel. It cannot be undone from here.')
            // A location with tunnels in use is capacity someone has paid for; the panel may
            // refuse anyway, but there is no reason to send the request and find out.
            ->disabled(fn (array $record): bool => (int) ($record['used'] ?? 0) > 0)
            ->tooltip(fn (array $record): ?string => (int) ($record['used'] ?? 0) > 0
                ? 'In use by ' . $record['used'] . ' tunnel(s) — cannot be deleted'
                : null)
            ->action(function (array $record): void {
                $this->run(
                    fn () => $this->api()->deleteLocation($record['tag']),
                    $record['tag'] . ' deleted',
                );
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New location')
                ->icon(Heroicon::Plus)
                ->schema(fn (): array => $this->locationForm())
                ->modalHeading('New panel location')
                ->modalSubmitActionLabel('Create')
                ->action(function (array $data): void {
                    $this->run(
                        fn () => $this->api()->createLocation($this->payloadFrom($data)),
                        'Location created',
                    );
                }),
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::ArrowPath)
                ->color('gray')
                ->action(fn () => $this->resetTable()),
        ];
    }

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
    private const PROVIDERS = [
        'do' => ['label' => 'DigitalOcean', 'length' => 4, 'example' => 'nyc1'],
        'vultr' => ['label' => 'Vultr', 'length' => 3, 'example' => 'ewr'],
        'sevencloud' => ['label' => 'SevenCloud', 'length' => 6, 'example' => 'mci-00'],
    ];

    /**
     * Create/update share one form — the panel takes the same body for both.
     *
     * The provider blocks are the reason this is not a two-field dialog: each cloud has its
     * own region codes, and the panel tries them in priority order when it builds a tunnel.
     * The length rules are enforced here rather than left to the panel so a typo comes back
     * as a field error instead of a rejected round trip.
     */
    private function locationForm(): array
    {
        $blocks = [];

        foreach (self::PROVIDERS as $key => $provider) {
            $fields = [];

            foreach ([1, 2, 3] as $n) {
                $fields[] = TextInput::make($key . '_prio' . $n)
                    ->label('Priority ' . $n)
                    ->required()
                    ->minLength($provider['length'])
                    ->maxLength($provider['length'])
                    ->placeholder($provider['example'])
                    ->helperText($n === 1 ? 'Exactly ' . $provider['length'] . ' characters' : null);
            }

            $blocks[] = Section::make($provider['label'] . ' regions')
                ->description('Tried in order when a tunnel is built here. All three are required by the panel.')
                ->columns(3)
                ->schema($fields);
        }

        return [
            Section::make('Location')
                ->columns(2)
                ->schema([
                    TextInput::make('continent')->required()->placeholder('Europe'),
                    TextInput::make('country')->label('Country code')->required()->maxLength(2)->placeholder('DE'),
                    TextInput::make('country_name')->label('Country name')->required()->placeholder('Germany'),
                    TextInput::make('state')->placeholder('North Rhine-Westphalia'),
                    // The panel derives the tag from country and city (Antarctica + "Paymenter
                    // Roundtrip" became `aq-pay-1`), so neither can be edited afterwards
                    // without the tag and the row disagreeing.
                    TextInput::make('city')->required()->placeholder('Bonn'),
                    TextInput::make('region_code')->required()->placeholder('DE-NW'),
                    TextInput::make('zip_code')->label('ZIP code'),
                ]),
            ...$blocks,
        ];
    }

    /** Panel row → flat form state. */
    private function formStateFrom(array $detail): array
    {
        $state = [];

        foreach (['continent', 'country', 'country_name', 'state', 'city', 'region_code', 'zip_code'] as $field) {
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

        foreach (['continent', 'country', 'country_name', 'state', 'city', 'region_code', 'zip_code'] as $field) {
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
            $this->resetTable();
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

        return ['error' => $error];
    }
}
