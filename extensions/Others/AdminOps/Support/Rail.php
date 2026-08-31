<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\OrderResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddNewClient;

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
                foreach (Category::query()->whereNull('parent_id')->orderBy('sort')->orderBy('name')->get() as $category) {
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
                'items' => array_merge($products, $item($find('Cancellation Requests'))),
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
            'Billable Items', '- Uninvoiced Items', '- Recurring Items',
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
                'items' => array_merge(
                    $take('Billable Items', 'List All Billable Items'),
                    $take('- Uninvoiced Items'), $take('- Recurring Items'),
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
        return array_values(array_filter([
            static::pageShortcut(AddNewClient::class, 'Add New Client', 'ri-user-add-line'),
            static::shortcut(OrderResource::class, 'Add New Order', 'ri-shopping-cart-2-line'),
            static::shortcut('Paymenter\\Extensions\\Others\\Quotes\\Admin\\Resources\\QuoteResource', 'Create New Quote', 'ri-draft-line'),
            static::shortcut(InvoiceResource::class, 'Create Invoice', 'ri-bill-line'),
            static::shortcut(TicketResource::class, 'Open New Ticket', 'ri-mail-add-line'),
            static::shortcut(ServiceResource::class, 'Add New Service', 'ri-add-box-line'),
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
            'Paymenter' => config('app.version') ?: null,
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Environment' => ucfirst(app()->environment()),
        ]);
    }

    /**
     * The filtered lists WHMCS puts under Advanced Search, each with its count. Counts come
     * from {@see Metrics} so the rail and the menu badges cannot disagree.
     *
     * @return array<int, array{label: string, count: int, url: string}>
     */
    public static function searches(): array
    {
        try {
            return static::buildSearches();
        } catch (\Throwable $e) {
            // Same rule as the shortcuts: this rail is on every admin page, so a counting
            // query or a route that fails must cost the panel a box, not a 500.
            return [];
        }
    }

    /**
     * @return array<int, array{label: string, count: int, url: string}>
     */
    private static function buildSearches(): array
    {
        $searches = [];

        if (ServiceResource::canViewAny()) {
            $searches[] = [
                'label' => 'Pending services',
                'count' => Metrics::servicesPending(),
                'url' => ServiceResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]]),
            ];
            $searches[] = [
                'label' => 'Suspended services',
                'count' => Metrics::servicesSuspended(),
                'url' => ServiceResource::getUrl('index', ['filters' => ['status' => ['value' => 'suspended']]]),
            ];
        }

        if (InvoiceResource::canViewAny()) {
            $searches[] = [
                'label' => 'Unpaid invoices',
                'count' => Metrics::invoicesUnpaid(),
                'url' => InvoiceResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']], 'sort' => 'due_at']),
            ];
        }

        if (TicketResource::canViewAny()) {
            $searches[] = [
                'label' => 'Tickets awaiting reply',
                'count' => Metrics::ticketsAwaitingReply(),
                'url' => TicketResource::getUrl('index', ['tab' => 'open']),
            ];
        }

        return $searches;
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
