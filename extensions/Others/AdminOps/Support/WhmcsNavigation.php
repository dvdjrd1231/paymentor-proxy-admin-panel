<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Pages\CronStats;
use App\Admin\Pages\Settings;
use App\Admin\Pages\Updates;
use App\Admin\Resources\ApiResource;
use App\Admin\Resources\Audits\AuditResource;
use App\Admin\Resources\CategoryResource;
use App\Admin\Resources\ConfigOptionResource;
use App\Admin\Resources\CouponResource;
use App\Admin\Resources\CurrencyResource;
use App\Admin\Resources\CustomPropertyResource;
use App\Admin\Resources\EmailLogResource;
use App\Admin\Resources\ErrorLogResource;
use App\Admin\Resources\ExtensionResource;
use App\Admin\Resources\FailedJobResource;
use App\Admin\Resources\GatewayResource;
use App\Admin\Resources\HttpLogResource;
use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\InvoiceTransactions\InvoiceTransactionResource;
use App\Admin\Resources\NotificationTemplateResource;
use App\Admin\Resources\OauthClientResource;
use App\Admin\Resources\OrderResource;
use App\Admin\Resources\ProductResource;
use App\Admin\Resources\RoleResource;
use App\Admin\Resources\ServerResource;
use App\Admin\Resources\ServiceCancellationResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TaxRateResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use App\Models\Category;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddNewClient;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddNewOrder;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AnnouncementsAdmin;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\CreateQuote;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\DownloadsAdmin;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\NetworkIssues;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\OpenNewTicket;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\PredefinedReplies;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\DatabaseStatus;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\DomainResolver;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\PhpCompatibility;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\PhpInfo;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\SystemCleanup;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ReportsHome;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ReportView;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddTransaction;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\BillableItemsList;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\HttpLogs;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\KnowledgebaseList;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\RefundRequests;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ServiceAddons;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\SystemSettings;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\TodoList;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\UtilitiesCalendar;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\WhoisLookup;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\SupportOverview;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\SupportTickets;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageInvoices;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageOrders;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AutomationStatus;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\Catalogue;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageUsers;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ViewSearchClients;
use Paymenter\Extensions\Others\Announcements\Admin\Resources\AnnouncementResource;
use Paymenter\Extensions\Others\BillableItems\Admin\Resources\BillableItemResource;
use Paymenter\Extensions\Others\Cancellations\Admin\Resources\CancellationRequestResource;
use Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Pages\Transactions;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\InvoiceOpsResource;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundRequestResource;
use Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundResource;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbArticleResource;
use Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbCategoryResource;
use Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource;
use Paymenter\Extensions\Others\ProvisioningOps\Admin\Resources\ProvisioningOperationResource;
use Paymenter\Extensions\Others\Quotes\Admin\Resources\QuoteResource;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ProductTermResource;
use Paymenter\Extensions\Others\TermLimits\Admin\Resources\ServiceTermResource;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\CannedResponseResource;
use Paymenter\Extensions\Others\TicketTools\Admin\Resources\TicketNoteResource;
use Paymenter\Extensions\Servers\ProxyPanel\Admin\Pages\PanelLocations;

/**
 * The WHMCS menu bar, rebuilt over Paymenter's resources: grouped by what you are *doing*
 * (Clients, Orders, Billing…) rather than by record type as core groups them.
 *
 * Uses `Panel::navigation()` because a resource's group is a static property on the resource
 * class — regrouping the normal way would mean editing two dozen core files.
 *
 * Taking navigation over makes anything not named here unreachable, so {@see addons()} runs
 * last and sweeps up whatever was not placed. A new extension therefore still appears,
 * landing in Addons until someone files it properly.
 *
 * Every entry is permission-checked: a limited role sees a shorter menu, never a link that
 * answers 403.
 *
 * @link docs/02b-admin-area.md
 */
class WhmcsNavigation
{
    /**
     * Classes already given a home, so the Addons catch-all can tell what is left.
     *
     * @var array<class-string, true>
     */
    private static array $placed = [];

    /**
     * The built menus, memoised for the request.
     *
     * @var array<int, NavigationGroup>|null
     */
    private static ?array $groups = null;

    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        return $builder->groups(static::groups());
    }

    /**
     * Built once per request and shared with the rail, which shows the section you are in —
     * so the two cannot disagree about what exists, and the permission checks run once.
     *
     * @return array<int, NavigationGroup>
     */
    public static function groups(): array
    {
        if (static::$groups !== null) {
            return static::$groups;
        }

        // Reset per build, not per process: a stale array would leak between requests under
        // a persistent worker and silently empty the Addons menu.
        static::$placed = [];

        $groups = array_values(array_filter([
            static::clients(),
            static::orders(),
            static::billing(),
            static::support(),
            static::reports(),
            static::utilities(),
            // Built, then dropped below. See the note before the return.
            static::setup(),
            // Last, and only last: it can only work out the remainder once the rest have
            // claimed what they wanted.
            static::addons(),
        ]));

        // The reference has no Setup menu — its setup screens live behind the wrench in the
        // toolbar, which is where {@see Toolbar::utilities()} now puts ours. The group is
        // still *built* rather than simply deleted, because building it is what marks its
        // resources as placed; skip that and the Addons catch-all would sweep every setup
        // screen into one long dropdown.
        return static::$groups = array_values(array_filter(
            $groups,
            fn (NavigationGroup $group): bool => $group->getLabel() !== 'Setup',
        ));
    }

    /**
     * The group the current page belongs to, or null on the dashboard. Matched on path, not
     * Filament's active flag — entries report themselves inactive ({@see link()}) because
     * several point at one resource under different filters. Longest match wins.
     */
    public static function activeGroup(): ?NavigationGroup
    {
        $path = rtrim(parse_url(request()->url(), PHP_URL_PATH) ?? '', '/');
        $best = null;
        $bestLength = 0;

        foreach (static::groups() as $group) {
            foreach ($group->getItems() as $item) {
                $itemPath = rtrim(parse_url((string) $item->getUrl(), PHP_URL_PATH) ?? '', '/');

                if ($itemPath === '' || !str_starts_with($path, $itemPath)) {
                    continue;
                }

                if (strlen($itemPath) > $bestLength) {
                    $best = $group;
                    $bestLength = strlen($itemPath);
                }
            }
        }

        return $best;
    }

    private static function clients(): ?NavigationGroup
    {
        return static::group('Clients', 'ri-group-line', [
            // The reference's own screens, not core's list: Leandro's feedback screenshots
            // are of View/Search Clients and Manage Users, so those pages exist here and the
            // menu leads to them. Core's UserResource stays reachable through their Actions
            // column — it is still the only place a user is edited.
            static::page(ViewSearchClients::class, 'View/Search Clients'),
            static::page(AddNewClient::class, 'Add New Client'),
            static::page(ManageUsers::class, 'Manage Users'),
            static::page(ProductsServices::class, 'Products/Services'),
            // The reference's flyout, flattened: Filament's dropdown cannot nest, so the
            // category entries sit indented under Products/Services exactly as the rail
            // draws them — same labels, same filtered URLs.
            ...static::categoryItems(),
            // Issue #7: addons on running services, where the reference's sidebar puts them.
            static::page(ServiceAddons::class, 'Service Addons'),
            // Ours, not core's: core's list offers Edit and Delete, and deleting a request is
            // indistinguishable from refusing it. Falls back to core's when the extension is
            // not installed, so the entry never disappears.
            class_exists(CancellationRequestResource::class)
                ? static::link(
                    CancellationRequestResource::class,
                    'Cancellation Requests',
                    badge: fn () => Metrics::cancellationsPending(),
                    badgeColor: 'danger',
                )
                : static::link(
                    ServiceCancellationResource::class,
                    'Cancellation Requests',
                    badge: fn () => Metrics::cancellationsPending(),
                    badgeColor: 'danger',
                ),
            static::page(ManageAffiliates::class, 'Manage Affiliates'),
        ]);
    }

    /**
     * A "- Category" entry per product group for the Clients dropdown, mirroring the rail.
     * Empty on any failure: a menu without sub-entries, never a broken menu.
     *
     * @return array<int, NavigationItem>
     */
    private static function categoryItems(): array
    {
        if (!class_exists(ProductsServices::class) || !ProductsServices::canAccess()) {
            return [];
        }

        try {
            $base = ProductsServices::getUrl();

            return Category::query()
                ->whereNull('parent_id')
                ->orderBy('sort')
                ->orderBy('name')
                ->get()
                ->map(fn ($category): NavigationItem => NavigationItem::make('- ' . $category->name)
                    ->url($base . '?category=' . $category->id)
                    ->isActiveWhen(fn (): bool => false))
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Status filters point at *services*, not orders: a Paymenter order is a container row
     * with no status of its own. Same reasoning as {@see Metrics::newServices()}.
     */
    private static function orders(): ?NavigationGroup
    {
        // Core's Orders and Services resources are reached from Manage Orders' rows now;
        // marked placed so the Addons catch-all does not sweep them back in.
        static::$placed[OrderResource::class] = true;
        static::$placed[ServiceResource::class] = true;

        // The reference's sidebar, on the reference's own page: one Manage Orders screen,
        // the filters told apart by ?status=. {@see ManageOrders}.
        return static::group('Orders', 'ri-shopping-bag-4-line', [
            static::pageLink(ManageOrders::class, 'List All Orders'),
            static::pageLink(
                ManageOrders::class,
                '- Pending Orders',
                params: ['status' => 'pending'],
                badge: fn () => Metrics::servicesPending(),
                badgeColor: 'info',
            ),
            static::pageLink(ManageOrders::class, '- Active Orders', params: ['status' => 'active']),
            static::pageLink(ManageOrders::class, '- Fraud Orders', params: ['status' => 'fraud']),
            static::pageLink(
                ManageOrders::class,
                '- Suspended Orders',
                params: ['status' => 'suspended'],
                badge: fn () => Metrics::servicesSuspended(),
                badgeColor: 'warning',
            ),
            static::pageLink(ManageOrders::class, '- Cancelled Orders', params: ['status' => 'cancelled']),
            static::page(AddNewOrder::class, 'Add New Order'),
        ]);
    }

    private static function billing(): ?NavigationGroup
    {
        // Core's invoice list is reached from Manage Invoices' rows; placed for the same
        // reason as OrderResource above.
        static::$placed[InvoiceResource::class] = true;

        // The reference's Billing menu, in the reference's order: Transactions List, then
        // Invoices with its status flyout, then the rest. {@see ManageInvoices}.
        return static::group('Billing', 'ri-bill-line', [
            static::page(Transactions::class, 'Transactions List'),
            static::pageLink(ManageInvoices::class, 'Invoices'),
            static::pageLink(ManageInvoices::class, '- Paid', params: ['status' => 'paid']),
            static::pageLink(ManageInvoices::class, '- Draft', params: ['status' => 'draft']),
            static::pageLink(
                ManageInvoices::class,
                '- Unpaid',
                params: ['status' => 'unpaid'],
                badge: fn () => Metrics::invoicesUnpaid(),
                badgeColor: fn () => Metrics::invoicesOverdue() ? 'danger' : 'gray',
            ),
            static::pageLink(ManageInvoices::class, '- Overdue', params: ['status' => 'overdue']),
            static::pageLink(ManageInvoices::class, '- Cancelled', params: ['status' => 'cancelled']),
            static::pageLink(ManageInvoices::class, '- Refunded', params: ['status' => 'refunded']),
            static::pageLink(ManageInvoices::class, '- Collections', params: ['status' => 'collections']),
            static::pageLink(ManageInvoices::class, '- Payment Pending', params: ['status' => 'payment_pending']),
            // Issue #13: the WHMCS-shaped list and Add New form; the extension's own
            // sweeper still does the invoicing.
            static::pageLink(
                BillableItemsList::class,
                'Billable Items',
                badge: fn () => class_exists(BillableItemResource::class)
                    ? BillableItemResource::getNavigationBadge()
                    : null,
                badgeColor: 'warning',
            ),
            static::pageLink(BillableItemsList::class, '- Uninvoiced Items', params: ['view' => 'uninvoiced']),
            static::pageLink(BillableItemsList::class, '- Recurring Items', params: ['view' => 'recurring']),
            static::link(
                QuoteResource::class,
                'Quotes',
                badge: fn () => class_exists(QuoteResource::class) ? QuoteResource::getNavigationBadge() : null,
                badgeColor: 'info',
            ),
            // The reference's Quotes sub-entries: the Valid/Expired split the resource
            // already filters by.
            static::link(QuoteResource::class, '- Valid',
                params: ['filters' => ['valid' => ['isActive' => true]]]),
            static::link(QuoteResource::class, '- Expired',
                params: ['filters' => ['status' => ['value' => 'expired']]]),
            static::pageLink(CreateQuote::class, '- Create New Quote'),
            // The reference's last three, on their nearest honest homes: recording a manual
            // payment IS offline card processing here, and a refund request is what a
            // dispute looks like in Paymenter.
            static::page(AddTransaction::class, 'Offline CC Processing'),
            static::pageLink(
                RefundRequests::class,
                'Disputes',
                badge: fn () => class_exists(RefundRequestResource::class)
                    ? RefundRequestResource::getNavigationBadge()
                    : null,
                badgeColor: 'danger',
            ),
            // The reference keeps its gateway log under Billing, and this is the nearest
            // thing Paymenter has: every outbound HTTP call, gateways included.
            static::page(HttpLogs::class, 'Gateway Log'),
        ]);
    }

    /** The reference's Support menu, entry for entry; Paymenter-only screens follow it. */
    private static function support(): ?NavigationGroup
    {
        return static::group('Support', 'ri-customer-service-line', [
            static::page(SupportOverview::class, 'Support Overview'),
            static::pageLink(
                SupportTickets::class,
                'Support Tickets',
                badge: fn () => Metrics::ticketsAwaitingReply(),
                badgeColor: 'warning',
            ),
            static::pageLink(SupportTickets::class, '- Flagged Tickets', params: ['view' => 'flagged']),
            static::pageLink(SupportTickets::class, '- All Active Tickets', params: ['view' => 'active']),
            static::pageLink(SupportTickets::class, '- Open', params: ['view' => 'open']),
            static::pageLink(SupportTickets::class, '- Answered', params: ['view' => 'answered']),
            static::pageLink(SupportTickets::class, '- Customer-Reply', params: ['view' => 'customer-reply']),
            static::pageLink(SupportTickets::class, '- On Hold', params: ['view' => 'on-hold']),
            static::pageLink(SupportTickets::class, '- In Progress', params: ['view' => 'in-progress']),
            static::pageLink(SupportTickets::class, '- Closed', params: ['view' => 'closed']),
            static::page(OpenNewTicket::class, 'Open New Ticket'),
            static::page(PredefinedReplies::class, 'Predefined Replies'),
            static::page(AnnouncementsAdmin::class, 'Announcements'),
            static::page(DownloadsAdmin::class, 'Downloads'),
            static::page(KnowledgebaseList::class, 'Knowledgebase'),
            static::pageLink(NetworkIssues::class, 'Network Issues'),
            static::pageLink(NetworkIssues::class, '- Open', params: ['view' => 'open']),
            static::pageLink(NetworkIssues::class, '- Scheduled', params: ['view' => 'scheduled']),
            static::pageLink(NetworkIssues::class, '- Resolved', params: ['view' => 'resolved']),
            static::pageLink(NetworkIssues::class, '- Create New', params: ['creating' => 1]),
        ]);
    }

    /**
     * Paymenter has no reporting module, so each entry is the pre-filtered list a report would
     * have been a view of. Staff look for this menu by name; filtered lists beat no menu.
     */
    /** The reference's Reports menu, entry for entry, More… leading to the full grid. */
    private static function reports(): ?NavigationGroup
    {
        return static::group('Reports', 'ri-line-chart-line', [
            static::page(ReportsHome::class, 'Reports'),
            static::pageLink(ReportView::class, 'Daily Performance', params: ['key' => 'daily-performance']),
            static::pageLink(ReportView::class, 'Income Forecast', params: ['key' => 'income-forecast']),
            static::pageLink(ReportView::class, 'Annual Income Report', params: ['key' => 'annual-income']),
            static::pageLink(ReportView::class, 'New Customers', params: ['key' => 'new-customers']),
            static::pageLink(ReportView::class, 'Ticket Feedback Scores', params: ['key' => 'ticket-feedback']),
            // The reference's Batch Invoice PDF Export has no counterpart — invoice PDFs
            // download per invoice — so its slot points at the grid that says so.
            static::page(ReportsHome::class, 'Batch Invoice PDF Exp…'),
            static::page(ReportsHome::class, 'More…'),
        ]);
    }

    /** Logs, the job queue, the cron and the updater — where the reference keeps them. */
    /**
     * The reference's Utilities menu, in its order, each slot the nearest real thing:
     * Module Queue is the provisioning queue, Email Campaigns is the email log, and the
     * reference's small tools — Calendar, To-Do List, WHOIS Lookup, Domain Resolver — are
     * real pages. System folds the operational logs into the reference's submenu.
     */
    private static function utilities(): ?NavigationGroup
    {
        return static::group('Utilities', 'ri-tools-line', [
            static::page(Updates::class, 'Update Paymenter'),
            class_exists(AutomationStatus::class)
                ? static::page(AutomationStatus::class, 'Automation Status')
                : static::page(CronStats::class, 'Automation Status'),
            static::link(
                ProvisioningOperationResource::class,
                'Module Queue',
                badge: fn () => Metrics::provisioningFailures(),
                badgeColor: 'danger',
            ),
            static::link(EmailLogResource::class, 'Email Campaigns'),
            static::link(NotificationTemplateResource::class, 'Email Marketer'),
            static::page(UtilitiesCalendar::class, 'Calendar'),
            static::page(TodoList::class, 'To-Do List'),
            static::page(WhoisLookup::class, 'WHOIS Lookup'),
            static::page(DomainResolver::class, 'Domain Resolver'),
            // Administrator accounts, kept apart from the Clients menu on purpose: Manage
            // Users lists client logins only, as the reference does, and staff are managed
            // here — core's Users list is where roles are assigned.
            static::pageLink(DatabaseStatus::class, 'System'),
            // The reference's System submenu, its four entries first.
            static::pageLink(DatabaseStatus::class, '- Database Status'),
            static::pageLink(SystemCleanup::class, '- System Cleanup'),
            static::pageLink(PhpInfo::class, '- PHP Info'),
            static::pageLink(PhpCompatibility::class, '- PHP Version Compatibility'),
            static::link(UserResource::class, '- Administrators'),
            static::page(CronStats::class, '- Cron Statistics'),
            static::link(FailedJobResource::class, '- Failed Jobs'),
            static::link(AuditResource::class, '- Audit Log'),
            static::link(ErrorLogResource::class, '- Error Log'),
            static::link(HttpLogResource::class, '- HTTP Log'),
            // Paymenter screens the reference's menus have no slot for, kept reachable
            // here — the reference's own System submenu is its junk drawer too.
            static::link(
                ServiceTermResource::class,
                '- Fixed Terms',
                badge: fn () => class_exists(ServiceTermResource::class)
                    ? ServiceTermResource::getNavigationBadge()
                    : null,
                badgeColor: 'danger',
            ),
            static::link(TicketResource::class, '- All Tickets (Core)'),
            static::link(TicketNoteResource::class, '- Internal Notes'),
            static::link(KbCategoryResource::class, '- Knowledgebase Categories'),
            static::link(RefundResource::class, '- Refunds'),
            static::link(InvoiceOpsResource::class, '- Invoice Operations'),
            static::link(CouponResource::class, '- Coupons'),
            static::link(PaymentFeeRuleResource::class, '- Payment Fee Rules'),
            static::link(GatewayRuleResource::class, '- Gateway Rules'),
            static::link(TaxRateResource::class, '- Tax Rates'),
        ]);
    }

    /** WHMCS's Setup menu — the spanner in its top-right corner. */
    private static function setup(): ?NavigationGroup
    {
        return static::group('Setup', 'ri-settings-3-line', [
            // First, as it is on the reference: the catalogue as a whole, ordered by
            // dragging. The two resource lists below it are where a single record is
            // created, edited and deleted.
            static::page(Catalogue::class, 'Products/Services'),
            static::link(ProductResource::class, 'Products'),
            static::link(CategoryResource::class, 'Categories'),
            // The reference's Auto Terminate/Fixed Term field, which lives on the product's
            // Pricing tab there and cannot here — a resource's form is no more extensible
            // from an extension than its table is.
            static::link(ProductTermResource::class, 'Auto Terminate'),
            static::link(ConfigOptionResource::class, 'Configurable Options'),
            static::link(ServerResource::class, 'Servers'),
            static::page(PanelLocations::class, 'Panel Locations'),
            static::link(GatewayResource::class, 'Payment Gateways'),
            static::link(CurrencyResource::class, 'Currencies'),
            static::link(CustomPropertyResource::class, 'Custom Properties'),
            static::link(NotificationTemplateResource::class, 'Email Templates'),
            static::link(RoleResource::class, 'Administrator Roles'),
            static::link(ApiResource::class, 'API Keys'),
            static::link(OauthClientResource::class, 'OAuth Clients'),
            static::link(ExtensionResource::class, 'Extensions'),
            static::page(Settings::class, 'General Settings'),
            // Updates lives in Utilities, as it does on the reference ("Update WHMCS").
        ]);
    }

    /**
     * Everything the menus above did not claim, so the panel stays complete by construction
     * rather than by remembering to edit this file when an extension is added.
     */
    private static function addons(): ?NavigationGroup
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if (!$panel) {
            return null;
        }

        $items = [];

        foreach ($panel->getResources() as $resource) {
            if (isset(static::$placed[$resource])) {
                continue;
            }

            $items[] = static::link($resource, static::labelFor($resource));
        }

        foreach ($panel->getPages() as $page) {
            // The dashboard is the brand logo's destination, not a menu entry.
            if (isset(static::$placed[$page]) || is_subclass_of($page, Dashboard::class)) {
                continue;
            }

            $items[] = static::page($page, static::labelFor($page));
        }

        return static::group('Addons', 'ri-puzzle-line', $items);
    }

    // ── plumbing ────────────────────────────────────────────────────────────────

    /**
     * Null when every entry was filtered out — an empty dropdown reads as breakage.
     *
     * @param  array<NavigationItem|null>  $items
     */
    private static function group(string $label, string $icon, array $items): ?NavigationGroup
    {
        $items = array_values(array_filter($items));

        if ($items === []) {
            return null;
        }

        return NavigationGroup::make($label)->icon($icon)->items($items);
    }

    /**
     * Null if the resource is absent or barred. `class_exists` is what decides, not the
     * `use` above — an import loads nothing — so a removed extension drops out of the menu
     * rather than fatalling the panel.
     *
     * @param  array<string, mixed>  $params
     */
    private static function link(
        string $resource,
        string $label,
        string $page = 'index',
        array $params = [],
        ?\Closure $badge = null,
        string|\Closure|null $badgeColor = null,
    ): ?NavigationItem {
        if (!class_exists($resource)) {
            return null;
        }

        if (!static::reachable($resource)) {
            return null;
        }

        $permitted = $page === 'create'
            ? $resource::canCreate()
            : $resource::canViewAny();

        if (!$permitted) {
            return null;
        }

        // Claimed even when this particular entry is a second link to the same resource:
        // "Pending Orders" and "Active Orders" both being ServiceResource is exactly why
        // the Addons catch-all must not add a third, unfiltered copy of it.
        static::$placed[$resource] = true;

        $url = static::resolveUrl(fn (): string => $resource::getUrl($page, $params));

        if ($url === null) {
            return null;
        }

        $item = NavigationItem::make($label)
            ->url($url)
            // Two links to the same resource would otherwise both highlight. Nothing here
            // can tell which filter is applied, so neither claims to be the active one.
            ->isActiveWhen(fn (): bool => false);

        return static::withBadge($item, $badge, $badgeColor);
    }

    /**
     * Whether the *screen* is open, not merely whether the record policy allows it. Core
     * overrides `canAccess()` on three resources for reasons a policy cannot express —
     * `TaxRateResource` closes when `settings.tax_enabled` is off, `ErrorLog` and `HttpLog`
     * need `admin.debug_logs.view` — and Filament enforces it on the request. Checking only
     * the policy put Tax Rates in the Billing menu on an install with tax off, answering 403.
     */
    private static function reachable(string $resource): bool
    {
        return !method_exists($resource, 'canAccess') || $resource::canAccess();
    }

    /**
     * A link to a standalone page with query parameters — the reference's status filters
     * ("Pending Orders", "- Paid") are one page told apart by `?status=`.
     *
     * @param  array<string, mixed>  $params
     */
    private static function pageLink(string $page, string $label, array $params = [], ?\Closure $badge = null, string|\Closure|null $badgeColor = null): ?NavigationItem
    {
        if (!class_exists($page)) {
            return null;
        }

        if (method_exists($page, 'canAccess') && !$page::canAccess()) {
            return null;
        }

        static::$placed[$page] = true;

        $url = static::resolveUrl(fn (): string => $page::getUrl($params));

        if ($url === null) {
            return null;
        }

        $item = NavigationItem::make($label)
            ->url($url)
            ->isActiveWhen(fn (): bool => request()->fullUrl() === $url);

        return static::withBadge($item, $badge, $badgeColor);
    }

    /** A link to a standalone page, same rules as {@see link()}. */
    private static function page(string $page, string $label): ?NavigationItem
    {
        // `canAccess()` comes from the CanAuthorizeAccess trait, which a page is not
        // obliged to use — a page without it authorises nothing and is simply reachable.
        if (!class_exists($page)) {
            return null;
        }

        if (method_exists($page, 'canAccess') && !$page::canAccess()) {
            return null;
        }

        static::$placed[$page] = true;

        $url = static::resolveUrl(fn (): string => $page::getUrl());

        if ($url === null) {
            return null;
        }

        return NavigationItem::make($label)
            ->url($url)
            ->isActiveWhen(fn (): bool => request()->url() === $url);
    }

    /**
     * Resolve the URL now and drop the entry if it cannot be built. **This guard took the
     * admin down once:** `NavigationItem::url()` accepts a closure, so a bad URL fails while
     * the topbar *renders* — an unhandled 500 on every admin page — not while the menu is
     * assembled. So "the navigation builds" proves nothing; the URLs must resolve too.
     *
     * Caught rather than special-cased, because any route taking a parameter has this problem
     * (the Addons catch-all swept in `ClientSummary`, which needs a customer id).
     */
    private static function resolveUrl(\Closure $url): ?string
    {
        try {
            return $url();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** A number or nothing, never a zero: a column of grey zeroes is noise. */
    private static function withBadge(NavigationItem $item, ?\Closure $badge, string|\Closure|null $color): NavigationItem
    {
        if (!$badge) {
            return $item;
        }

        // Colour is the second argument of badge(), not a setter of its own.
        //
        // Wrapped for the same reason as resolveUrl(): a badge closure is evaluated while
        // the topbar renders, so a counting query that throws — a table an extension has
        // not migrated yet, say — would be a 500 on every admin page rather than a missing
        // number. A menu that cannot count is still a usable menu.
        return $item->badge(
            function () use ($badge): ?string {
                try {
                    return ($count = $badge()) ? (string) $count : null;
                } catch (\Throwable $e) {
                    return null;
                }
            },
            $color,
        );
    }

    /** The resource's or page's own navigation label, so nothing has to be renamed twice. */
    private static function labelFor(string $class): string
    {
        $label = method_exists($class, 'getNavigationLabel') ? $class::getNavigationLabel() : null;

        return filled($label) ? $label : str(class_basename($class))->beforeLast('Resource')->headline()->toString();
    }
}
