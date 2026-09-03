<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\OrderResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddNewClient;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageOrders;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\SupportTickets;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ViewSearchClients;

/**
 * The data behind WHMCS's left rail: Shortcuts, System Information, Advanced Search and
 * Staff Online. Structure only — `rail.blade.php` renders it.
 *
 * Everything is permission-checked and URL-resolved here, for the reason at
 * {@see WhmcsNavigation::resolveUrl()}: this renders on every admin page, so a link that
 * cannot be built must vanish rather than throw.
 *
 * @link docs/02b-admin-area.md
 */
class Rail
{
    /**
     * The section the current page is in — the reference's rail lists the menu you are inside,
     * so it is {@see WhmcsNavigation}'s own groups viewed a second way. Sharing them means the
     * rail and the bar cannot disagree about what exists or who may see it.
     *
     * @return array{label: string, icon: string|\BackedEnum|null, items: array<int, array{label: string, url: string, badge: ?string}>}|null
     */
    public static function section(): ?array
    {
        $group = WhmcsNavigation::activeGroup();

        if (!$group) {
            return null;
        }

        $items = [];

        foreach ($group->getItems() as $item) {
            $items[] = [
                'label' => $item->getLabel(),
                'url' => (string) $item->getUrl(),
                'badge' => $item->getBadge(),
            ];
        }

        return [
            'label' => $group->getLabel(),
            // String or BackedEnum, whichever the group was given — the icon component
            // resolves both, so neither is normalised away here.
            'icon' => $group->getIcon(),
            'items' => $items,
        ];
    }

    /**
     * The rail's sections — plural, because the reference's Clients area shows three at
     * once: Clients, Products/Services (with a "- Category" sub-entry per product group,
     * exactly as it lists "- Shared Hosting"), and Affiliates. Everywhere else the rail
     * stays one section, the group you are in.
     *
     * @return array<int, array{label: string, icon: string|\BackedEnum|null, items: array<int, array{label: string, url: string, badge: ?string}>}>
     */
    public static function sections(): array
    {
        $section = static::section();

        if (!$section) {
            return [];
        }

        if ($section['label'] === 'Billing') {
            return static::billingSections($section);
        }

        if ($section['label'] === 'Support') {
            return static::supportSections($section);
        }

        // The reference's Reports rail: every real report, A to Z.
        if ($section['label'] === 'Reports'
            && class_exists(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ReportView::class)) {
            try {
                $section['items'] = collect(Reports::railList())
                    ->map(fn (string $key, string $label): array => [
                        'label' => $label,
                        'url' => \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ReportView::getUrl(['key' => $key]),
                        'badge' => null,
                    ])->values()->all();
            } catch (\Throwable $e) {
                // A rail without report links, never a broken rail.
            }

            return [$section];
        }

        if ($section['label'] !== 'Clients') {
            return [$section];
        }

        $find = fn (string $label): ?array => collect($section['items'])
            ->first(fn (array $item): bool => $item['label'] === $label);

        $item = fn (?array $found): array => $found ? [$found] : [];

        $products = $item($find('Products/Services'));

        if ($products !== []) {
            $products[0]['label'] = 'List All Products/Services';

            try {
                // Same exclusion as the menu's: the addon catalogue is a category, but it
                // has its own entry below and must not be listed twice.
                foreach (Category::query()->whereNull('parent_id')
                    ->where('name', '!=', \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ServiceAddons::CATEGORY)
                    ->orderBy('sort')->orderBy('name')->get() as $category) {
                    $products[] = [
                        'label' => '- ' . $category->name,
                        'url' => $products[0]['url'] . '?category=' . $category->id,
                        'badge' => null,
                    ];
                }
            } catch (\Throwable $e) {
                // No categories is a rail without sub-entries, never a broken rail.
            }
        }

        return array_values(array_filter([
            [
                'label' => 'Clients',
                'icon' => 'ri-group-line',
                'items' => array_merge(
                    $item($find('View/Search Clients')),
                    $item($find('Add New Client')),
                    $item($find('Manage Users')),
                ),
            ],
            [
                'label' => 'Products/Services',
                'icon' => 'ri-instance-line',
                'items' => array_merge(
                    $products,
                    $item($find('Service Addons')),
                    // The reference lists Domain Registrations between Service Addons and
                    // Cancellation Requests — a real page here (honestly empty; the store
                    // registers no domains), so the rail takes it from the menu like any
                    // other entry.
                    $item($find('Domain Registrations')),
                    $item($find('Cancellation Requests')),
                ),
            ],
            [
                'label' => 'Affiliates',
                'icon' => 'ri-share-forward-line',
                'items' => $item($find('Manage Affiliates')),
            ],
        ], fn (array $s): bool => $s['items'] !== []));
    }

    /**
     * The reference's Billing rail is four boxes, not one: Billing, Invoices, Billable
     * Items, Quotes — each list renamed the way the reference names it ("List All
     * Invoices" under an "Invoices" heading). Built from the same one nav group, so the
     * split can never disagree with the bar about what exists.
     *
     * @param  array{label: string, icon: string|\BackedEnum|null, items: array<int, array{label: string, url: string, badge: ?string}>}  $section
     * @return array<int, array{label: string, icon: string|\BackedEnum|null, items: array<int, array{label: string, url: string, badge: ?string}>}>
     */
    private static function billingSections(array $section): array
    {
        $items = collect($section['items']);

        $take = function (string $label, ?string $rename = null) use ($items): array {
            $found = $items->first(fn (array $item): bool => $item['label'] === $label);

            if (!$found) {
                return [];
            }

            $found['label'] = $rename ?? $found['label'];

            return [$found];
        };

        // Everything each named box claims; whatever is left stays in the Billing box, so
        // a new entry appears rather than vanishing.
        $claimed = [
            'Invoices', '- Paid', '- Draft', '- Unpaid', '- Overdue', '- Cancelled',
            '- Refunded', '- Collections', '- Payment Pending',
            'Billable Items', '- Uninvoiced Items', '- Recurring Items', '- Add New',
            'Quotes', '- Valid', '- Expired', '- Create New Quote',
        ];

        $rest = $items->reject(fn (array $item): bool => in_array($item['label'], $claimed, true))->values()->all();

        return array_values(array_filter([
            ['label' => 'Billing', 'icon' => 'ri-bank-card-line', 'items' => $rest],
            [
                'label' => 'Invoices',
                'icon' => 'ri-file-list-3-line',
                'items' => array_merge(
                    $take('Invoices', 'List All Invoices'),
                    $take('- Paid'), $take('- Draft'), $take('- Unpaid'), $take('- Overdue'),
                    $take('- Cancelled'), $take('- Refunded'), $take('- Collections'),
                    $take('- Payment Pending'),
                ),
            ],
            [
                'label' => 'Billable Items',
                'icon' => 'ri-price-tag-3-line',
                // The reference's own order: List All, Uninvoiced, Recurring, then Add
                // New last.
                'items' => array_merge(
                    $take('Billable Items', 'List All Billable Items'),
                    $take('- Uninvoiced Items'), $take('- Recurring Items'),
                    // The reference's own sidebar drops the dash on Add New, unlike
                    // the dropdown menu, which now carries it for the same reason
                    // Uninvoiced/Recurring Items do — see WhmcsNavigation::billing().
                    $take('- Add New', 'Add New'),
                ),
            ],
            [
                'label' => 'Quotes',
                'icon' => 'ri-draft-line',
                'items' => array_merge(
                    $take('Quotes', 'List All Quotes'),
                    $take('- Valid'), $take('- Expired'),
                    $take('- Create New Quote', 'Create New Quote'),
                ),
            ],
        ], fn (array $s): bool => $s['items'] !== []));
    }

    /**
     * The reference's Support rail: the Support box, the Filter Tickets form (a real form —
     * its selects and inputs land on the tickets page as URL filters), and Network Issues.
     * Ticket status views live in the form's Status select with their counts, which is why
     * they are claimed out of the Support box.
     *
     * @param  array{label: string, icon: string|\BackedEnum|null, items: array<int, array{label: string, url: string, badge: ?string}>}  $section
     * @return array<int, array<string, mixed>>
     */
    private static function supportSections(array $section): array
    {
        $items = collect($section['items']);

        $take = function (string $label, ?string $rename = null) use ($items): array {
            $found = $items->first(fn (array $item): bool => $item['label'] === $label);

            if (!$found) {
                return [];
            }

            $found['label'] = $rename ?? $found['label'];

            return [$found];
        };

        $counts = [];
        $ticketsUrl = $items->first(fn (array $item): bool => $item['label'] === 'Support Tickets')['url'] ?? null;

        if (class_exists(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\SupportTickets::class) && $ticketsUrl) {
            try {
                $counts = (new \Paymenter\Extensions\Others\AdminOps\Admin\Pages\SupportTickets)->statusCounts();
            } catch (\Throwable $e) {
            }
        }

        return array_values(array_filter([
            [
                'label' => 'Support',
                'icon' => 'ri-customer-service-line',
                'items' => array_merge(
                    $take('Announcements'),
                    $take('Downloads'),
                    $take('Knowledgebase'),
                    $take('Support Tickets'),
                    $take('Open New Ticket'),
                    $take('Predefined Replies'),
                ),
            ],
            $ticketsUrl ? [
                'label' => 'Filter Tickets',
                'icon' => 'ri-filter-line',
                'items' => [],
                'form' => 'filter-tickets',
                'action' => strtok($ticketsUrl, '?'),
                'counts' => $counts,
                'departments' => (array) config('settings.ticket_departments'),
            ] : null,
            // The reference shows Tag Cloud on its ticket list pages. Paymenter tickets
            // have no tags, so its one honest word is "None" — the reference's own empty
            // state.
            str_contains(request()->path(), 'support-tickets') ? [
                'label' => 'Tag Cloud',
                'icon' => 'ri-price-tag-3-line',
                'items' => [],
                'form' => 'tag-cloud',
            ] : null,
            [
                'label' => 'Network Issues',
                'icon' => 'ri-wifi-off-line',
                'items' => array_merge(
                    $take('- Open'),
                    $take('- Scheduled'),
                    $take('- Resolved'),
                    $take('- Create New', 'Create New'),
                ),
            ],
        ], fn (?array $s): bool => $s !== null && ($s['items'] !== [] || ($s['form'] ?? null))));
    }

    /**
     * Each with its own small icon, as every WHMCS shortcut has — the icon column is half of
     * what makes that list scannable.
     *
     * @return array<int, array{label: string, url: string, icon: string}>
     */
    public static function shortcuts(): array
    {
        // Issue #37: the reference's own shortcut list, in its order, each to the screen
        // that really does the thing.
        return array_values(array_filter([
            static::pageShortcut(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddNewClient::class, 'Add New Client', 'ri-user-add-line'),
            static::pageShortcut(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddNewOrder::class, 'Add New Order', 'ri-shopping-cart-2-line'),
            static::pageShortcut(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\CreateQuote::class, 'Create New Quote', 'ri-draft-line'),
            static::pageShortcut(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\TodoList::class, 'Create New To-Do Entry', 'ri-checkbox-line'),
            static::pageShortcut(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\OpenNewTicket::class, 'Open New Ticket', 'ri-mail-add-line'),
            static::pageShortcut(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\WhoisLookup::class, 'WHOIS Lookup', 'ri-global-line'),
            static::pageShortcut(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices::class, 'Generate Due Invoices', 'ri-refresh-line'),
            static::pageShortcut(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddTransaction::class, 'Attempt CC Captures', 'ri-bank-card-line'),
        ]));
    }

    /**
     * One shortcut, or nothing if the role may not create that record. `canCreate()` answers
     * the policy, not whether a `create` route exists — a resource may authorise creation and
     * only offer it in a modal — so the URL is resolved here too.
     *
     * @return array{label: string, url: string, icon: string}|null
     */
    /** A shortcut to one of our own pages, guarded by the page's own canAccess(). */
    private static function pageShortcut(string $page, string $label, string $icon): ?array
    {
        if (!class_exists($page) || (method_exists($page, 'canAccess') && !$page::canAccess())) {
            return null;
        }

        try {
            return ['label' => $label, 'url' => $page::getUrl(), 'icon' => $icon];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function shortcut(string $resource, string $label, string $icon): ?array
    {
        if (!class_exists($resource) || !static::reachable($resource) || !$resource::canCreate()) {
            return null;
        }

        try {
            return ['label' => $label, 'url' => $resource::getUrl('create'), 'icon' => $icon];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Core gates some resources in `canAccess()` rather than the policy. Nothing the rail links
     * to does today; guarded so the next one that does cannot reintroduce the 403 the menu had.
     * See {@see WhmcsNavigation::reachable()}.
     */
    private static function reachable(string $resource): bool
    {
        return !method_exists($resource, 'canAccess') || $resource::canAccess();
    }

    /**
     * WHMCS's System Information panel.
     *
     * Its version, licence and expiry lines have no equivalent here and inventing them would
     * be worse than leaving them out, so this carries what is actually true of this
     * installation and useful to know before changing anything.
     *
     * @return array<string, string>
     */
    public static function systemInformation(): array
    {
        return array_filter([
            'Store' => config('app.name'),
            'Paymenter' => self::paymenterVersion(),
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Environment' => ucfirst(app()->environment()),
        ]);
    }

    /**
     * Issue #27: core ships `app.version = development` because this install runs from
     * source rather than a tagged release — the string is core's, not a warning. Shown
     * here as the deployed commit instead, which answers "what is running" honestly;
     * the config value itself stays untouched for core's own update checks.
     */
    private static function paymenterVersion(): ?string
    {
        $version = config('app.version') ?: null;

        if ($version !== 'development') {
            return $version;
        }

        return \Illuminate\Support\Facades\Cache::remember('adminops.version-label', 3600, function (): string {
            try {
                $ref = trim((string) file_get_contents(base_path('.git/HEAD')));

                if (str_starts_with($ref, 'ref:')) {
                    $branch = trim(substr($ref, 4));

                    // A loose ref file can be stale after git packs its refs; whichever of
                    // the two files was written last holds the truth.
                    $candidates = [];
                    $loose = base_path('.git/' . $branch);
                    if (is_file($loose)) {
                        $candidates[filemtime($loose)] = trim((string) file_get_contents($loose));
                    }

                    $packed = base_path('.git/packed-refs');
                    if (is_file($packed) && preg_match('/^([0-9a-f]{40}) ' . preg_quote($branch, '/') . '$/m',
                        (string) file_get_contents($packed), $match)) {
                        $candidates[filemtime($packed)] = $match[1];
                    }

                    krsort($candidates);
                    $ref = (string) (reset($candidates) ?: '');
                }

                return $ref !== '' ? 'source @ ' . substr($ref, 0, 7) : 'development';
            } catch (\Throwable $e) {
                return 'development';
            }
        });
    }

    /**
     * WHMCS's Advanced Search widget: pick an area, pick a field, type, Search. It replaced
     * the earlier count-link list because the reference's box is this form — and the form is
     * real: every field maps to a URL filter the destination page already reads, so it can
     * only offer searches that actually run, over records the role may view.
     *
     * @return array<int, array{label: string, action: string, fields: array<int, array{label: string, param: string}>}>
     */
    public static function advancedSearch(): array
    {
        try {
            return static::buildAdvancedSearch();
        } catch (\Throwable $e) {
            // Same rule as the shortcuts: this rail is on every admin page, so a URL that
            // fails to resolve must cost the panel a box, not a 500.
            return [];
        }
    }

    /**
     * The permission check reads the resource (a user/service/invoice/ticket policy, not a
     * page's own), because that is the real gate; the action lands on the WHMCS-styled page
     * the rest of the menu already leads to for the same records.
     *
     * @return array<int, array{label: string, action: string, fields: array<int, array{label: string, param: string}>}>
     */
    private static function buildAdvancedSearch(): array
    {
        $types = [];

        $add = function (string $page, string $resource, string $label, array $fields) use (&$types): void {
            if (!class_exists($page) || !class_exists($resource) || !$resource::canViewAny()) {
                return;
            }

            if (method_exists($page, 'canAccess') && !$page::canAccess()) {
                return;
            }

            $types[] = [
                'label' => $label,
                // The path alone: the form appends its one field as the query string.
                'action' => strtok((string) $page::getUrl(), '?'),
                'fields' => collect($fields)
                    ->map(fn (string $param, string $field): array => ['label' => $field, 'param' => $param])
                    ->values()->all(),
            ];
        };

        // Each param below is a #[Url] property the page filters by — the same filters its
        // own Search/Filter band submits.
        $add(ViewSearchClients::class, UserResource::class, 'Clients', [
            'Client Name' => 'name',
            'Email Address' => 'email',
            'Phone Number' => 'phone',
            'Client ID' => 'cid',
        ]);
        $add(ManageOrders::class, OrderResource::class, 'Orders', [
            'Order # / Client' => 'q',
        ]);
        $add(ManageInvoices::class, InvoiceResource::class, 'Invoices', [
            'Invoice # / Client' => 'q',
        ]);
        $add(ProductsServices::class, ServiceResource::class, 'Products/Services', [
            'Client Name/Email' => 'client',
            'Product Name' => 'product',
        ]);
        $add(SupportTickets::class, TicketResource::class, 'Tickets', [
            'Subject/Message' => 'q',
            'Email Address' => 'email',
            'Ticket #' => 'tid',
        ]);

        return $types;
    }

    /**
     * Colleagues signed in right now, the signed-in administrator first.
     *
     * WHMCS lists you in your own Staff Online panel, and it is the one entry that proves
     * the panel is working rather than merely empty.
     *
     * @return array<int, array{name: string, self: bool, seen: ?string}>
     */
    public static function staffOnline(): array
    {
        $me = Auth::user();
        $rows = [];

        if ($me) {
            $rows[] = ['name' => $me->name, 'self' => true, 'seen' => 'now'];
        }

        foreach (Metrics::staffOnline() as $member) {
            if ($me && $member->name === $me->name) {
                continue;
            }

            $rows[] = [
                'name' => $member->name,
                'self' => false,
                'seen' => $member->last_activity?->diffForHumans(),
            ];
        }

        return $rows;
    }
}
