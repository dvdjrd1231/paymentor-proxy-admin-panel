<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Admin\Clusters\Extensions as ExtensionsCluster;
use App\Admin\Clusters\InvoiceCluster;
use App\Admin\Clusters\Services as ServicesCluster;
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
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailCampaigns;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\GeneralSettings;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\NetworkIssues;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\OpenNewTicket;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\PredefinedReplies;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\DatabaseStatus;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\DomainResolver;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\PhpCompatibility;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\PhpInfo;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\SystemCleanup;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\QuotesList;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ReportsHome;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ReportView;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddTransaction;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\OfflineCcProcessing;
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
// Without this import, `CancellationRequests::class` in clients() names a class in *this*
// namespace that does not exist, and the class_exists guard silently dropped the entry.
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\CancellationRequests;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\Catalogue;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\DomainRegistrations;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageUsers;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ProductsServices;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ViewSearchClients;
use Paymenter\Extensions\Others\Affiliates\Admin\Resources\AffiliateResource;
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
        static::markSystemSettingsPlaced();

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
        // Never placed, so the extension's own resource sat in Addons as a second,
        // un-styled "Affiliate" beside Manage Affiliates below.
        static::$placed[AffiliateResource::class] = true;

        // Whichever branch the Cancellation Requests ternary below does not take is placed
        // here regardless — static::link() only marks placed the resource it actually links,
        // so the other one sat unclaimed and the Addons catch-all picked it up as a second,
        // un-styled "Service Cancellations".
        static::$placed[ServiceCancellationResource::class] = true;

        // Issue #30: the menu entry is now the WHMCS-styled CancellationRequests page, so
        // the extension's resource is no longer marked placed by link() — claim it here or
        // the Addons catch-all resurfaces it (it redirects to that page anyway).
        if (class_exists(CancellationRequestResource::class)) {
            static::$placed[CancellationRequestResource::class] = true;
        }

        return static::group('Clients', 'ri-group-line', [
            // The reference's own screens, not core's list: Leandro's feedback screenshots
            // are of View/Search Clients and Manage Users, so those pages exist here and the
            // menu leads to them. Core's UserResource stays reachable through their Actions
            // column — it is still the only place a user is edited.
            static::page(ViewSearchClients::class, 'View/Search Clients'),
            // Issue #36: the reference's actual order is View/Search Clients, Add New
            // Client, Manage Users — the previous comment here had Manage Users sitting
            // between the two, which the reference's own dropdown does not.
            static::page(AddNewClient::class, 'Add New Client'),
            static::page(ManageUsers::class, 'Manage Users'),
            static::page(ProductsServices::class, 'Products/Services'),
            // The category entries follow their parent, and the menu script folds them
            // into the reference's side panel — "Products/Services ▸" opening on hover.
            ...static::categoryItems(),
            // Issue #7: addons on running services, where the reference's sidebar puts them.
            static::page(ServiceAddons::class, 'Service Addons'),
            // The reference's Domain Registrations screen — a real page, honestly empty,
            // because this store registers no domains. It replaced a dead rail label that
            // read as a rendering fault.
            static::page(DomainRegistrations::class, 'Domain Registrations'),
            // Ours, not core's: core's list offers Edit and Delete, and deleting a request is
            // indistinguishable from refusing it. Falls back to core's when the extension is
            // not installed, so the entry never disappears.
            // Issue #30: the WHMCS-styled page, with the Cancellations extension's real
            // accept/refuse behind it. Falls back to core's resource when that extension
            // is not installed, so the entry never disappears.
            class_exists(\Paymenter\Extensions\Others\Cancellations\Support\Requests::class)
                ? static::pageWithBadge(CancellationRequests::class, 'Cancellation Requests', fn () => Metrics::cancellationsPending(), 'danger')
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
                // The addon catalogue is a product category by construction, but it already
                // has its own menu entry and its own screen — listing it here as well put
                // "Service Addons" in the menu twice, one line above the other.
                ->where('name', '!=', ServiceAddons::CATEGORY)
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
        // The cluster that groups ServiceResource for core's own navigation is a second,
        // separate entry Filament registers as a page, not a resource — missed the first
        // time round, which is how "Services" ended up in Addons anyway.
        static::$placed[ServicesCluster::class] = true;

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
        // Same cluster gap as ServicesCluster in orders() — its own separate page entry.
        static::$placed[InvoiceCluster::class] = true;

        // These three were never placed at all — only ever read for a badge count, never
        // excluded from the Addons sweep — so the extension's own native resource sat in
        // Addons the whole time as a second, un-styled "Billable Items" / "Quotes" /
        // "Refund Requests", one menu below the page that actually replaces it. Placed here,
        // next to the pages that supersede them, rather than where they are read.
        static::$placed[BillableItemResource::class] = true;
        static::$placed[QuoteResource::class] = true;
        // Add Transaction: real, reachable from a row on Offline CC Processing below, but
        // the reference has no menu entry for it at all — opened from an invoice, not
        // browsed to — so none is invented here either. Placed anyway so the Addons
        // catch-all does not sweep it in as an entry the reference never had.
        static::$placed[AddTransaction::class] = true;
        static::$placed[RefundRequestResource::class] = true;

        // Issue #11: core's raw Invoice Transactions list is the old window standard —
        // its home is the Transactions List page above, so it is claimed here and the
        // Addons sweep stops resurfacing it.
        static::$placed[InvoiceTransactionResource::class] = true;

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
            static::pageLink(
                QuotesList::class,
                'Quotes',
                badge: fn () => class_exists(QuoteResource::class) ? QuoteResource::getNavigationBadge() : null,
                badgeColor: 'info',
            ),
            // The reference's Quotes sub-entries: the Valid/Expired split the resource
            // already filters by.
            static::pageLink(QuotesList::class, '- Valid', params: ['view' => 'valid']),
            static::pageLink(QuotesList::class, '- Expired', params: ['view' => 'expired']),
            static::pageLink(CreateQuote::class, '- Create New Quote'),
            // The reference's last three, on their nearest honest homes: recording a manual
            // payment IS offline card processing here, and a refund request is what a
            // dispute looks like in Paymenter.
            // Issue #15: the reference's own page is a queue — invoices belonging to a
            // client with a card on file — not a form. Add Transaction is real and still
            // reachable (an Attempt Charge link on a row here goes straight to it), but the
            // reference has no menu entry for it at all — it is opened from an invoice, not
            // browsed to — so this does not invent one either. See its placement above,
            // next to where the item for it used to be.
            static::page(OfflineCcProcessing::class, 'Offline CC Processing'),
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
        // Same story as the three placed in billing() above: read for nothing here, never
        // excluded, so the extension's own resource sat in Addons as a second, un-styled
        // "Announcement" / "Kb Article" the whole time.
        static::$placed[AnnouncementResource::class] = true;
        static::$placed[KbArticleResource::class] = true;
        // Predefined Replies below reads and writes the same CannedResponse rows this
        // resource does — the two are one feature under two names, and only one was ever
        // excluded from the catch-all.
        static::$placed[CannedResponseResource::class] = true;

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
            static::updatePaymenterEntry(),
            class_exists(AutomationStatus::class)
                ? static::page(AutomationStatus::class, 'Automation Status')
                : static::page(CronStats::class, 'Automation Status'),
            static::link(
                ProvisioningOperationResource::class,
                'Module Queue',
                badge: fn () => Metrics::provisioningFailures(),
                badgeColor: 'danger',
            ),
            static::emailCampaignsEntry(),
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
            // Issue #33: "I want it displayed similarly to WHMCS" — the WHMCS display of
            // cron activity IS the Automation Status page, so the entry opens it. Core's
            // raw CronStats chart page stays claimed so the Addons sweep leaves it be.
            static::cronStatisticsEntry(),
            static::link(FailedJobResource::class, '- Failed Jobs'),
            static::link(AuditResource::class, '- Audit Log'),
            static::link(ErrorLogResource::class, '- Error Log'),
            // Issue #17's "Nothing was modified": this entry pointed at core's raw
            // HttpLogResource, so the WHMCS-shaped page (Billing's Gateway Log) was
            // never what a click here reached. Same page here now; the raw resource
            // stays claimed so the Addons sweep leaves it be.
            static::httpLogEntry(),
            // Paymenter screens the reference's menus have no slot for, kept reachable
            // here — the reference's own System submenu is its junk drawer too.
            static::fixedTermsEntry(),
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
        // Same cluster gap as ServicesCluster/InvoiceCluster above — its own separate page
        // entry alongside ExtensionResource, which is placed below in this same group.
        static::$placed[ExtensionsCluster::class] = true;

        // Issue #35: "Products" (core's raw resource, no drag) sat right under
        // Products/Services (the catalogue, which drags) — Leandro opened the wrong one
        // and read the feature as missing. The reference's Setup menu carries only
        // Products/Services; single records are created/edited through the catalogue's
        // own Edit and Create buttons, which land on these resources' pages.
        static::$placed[ProductResource::class] = true;
        static::$placed[CategoryResource::class] = true;
        // Issue #45: the menu entry is now the quick-buttons PaymentGateways page, so the
        // core resource is no longer claimed by link() — claim it here or the Addons
        // sweep resurfaces it (its edit form is still reached from that page's Edit).
        static::$placed[GatewayResource::class] = true;
        // Issue #43: same treatment for Servers — the menu entry is the quick-actions
        // page; core's resource (still the edit/create surface) is claimed here.
        static::$placed[ServerResource::class] = true;
        // Issue #42: same for Configurable Options — the menu entry is the groups page.
        static::$placed[ConfigOptionResource::class] = true;
        // Issue #48: same for Email Templates — the menu entry is the grouped page.
        static::$placed[NotificationTemplateResource::class] = true;
        // Issue #46: same for Currencies — the menu entry is the WHMCS-shaped page.
        static::$placed[CurrencyResource::class] = true;
        // Issues #49/#50: same for Administrator Roles and API Credentials.
        static::$placed[RoleResource::class] = true;
        static::$placed[ApiResource::class] = true;
        // Issue #51: same for OAuth clients — the menu entry is the WHMCS-shaped page.
        static::$placed[OauthClientResource::class] = true;

        return static::group('Setup', 'ri-settings-3-line', [
            // First, as it is on the reference: the catalogue as a whole, ordered by
            // dragging.
            static::page(Catalogue::class, 'Products/Services'),
            // The reference's Auto Terminate/Fixed Term field, which lives on the product's
            // Pricing tab there and cannot here — a resource's form is no more extensible
            // from an extension than its table is.
            static::link(ProductTermResource::class, 'Auto Terminate'),
            static::page(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ConfigOptionGroups::class, 'Configurable Options'),
            static::page(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ServersList::class, 'Servers'),
            static::page(PanelLocations::class, 'Panel Locations'),
            // Issue #45: the quick-buttons page; core's resource stays claimed below.
            static::page(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\PaymentGateways::class, 'Payment Gateways'),
            static::page(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\CurrenciesList::class, 'Currencies'),
            static::link(CustomPropertyResource::class, 'Custom Client Fields'),
            static::page(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::class, 'Email Templates'),
            static::page(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\AdminRoles::class, 'Administrator Roles'),
            static::page(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ApiCredentials::class, 'API Credentials'),
            // Issue #51: the WHMCS-shaped list; core's resource keeps create/edit and
            // is claimed just below so the Addons sweep leaves it be.
            class_exists(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\OauthClients::class)
                ? static::page(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\OauthClients::class, 'OpenID Connect')
                : static::link(OauthClientResource::class, 'OpenID Connect'),
            static::link(ExtensionResource::class, 'Extensions'),
            // Issue #39: the WHMCS-shaped tabbed settings page; core's raw form stays
            // reachable as System Settings' first tile.
            static::page(GeneralSettings::class, 'General Settings'),
            // Updates lives in Utilities, as it does on the reference ("Update WHMCS").
        ]);
    }

    /**
     * Not a menu of its own — {@see Toolbar} is what actually links System Settings, from
     * the wrench. This exists only to mark the page placed, the same way building (and
     * discarding) setup() above does for everything in it: without this, an unplaced Page
     * is exactly what the Addons catch-all is for, and System Settings would end up
     * reachable from the wrench *and* from Addons — a second, harder to find copy of the
     * one link that was just fixed.
     */
    private static function markSystemSettingsPlaced(): void
    {
        static::$placed[SystemSettings::class] = true;
        // Issue #39: the menu's General Settings entry is now the WHMCS-shaped tabbed
        // page; core's raw form stays reachable as System Settings' first tile, and is
        // claimed here so the Addons sweep leaves it be.
        static::$placed[Settings::class] = true;

        // Core's "Available Extensions" page is deliberately NOT claimed: the reference's
        // top bar has an Addons menu, and with every other stray claimed (issue #11) this
        // page is what honestly belongs in it — WHMCS's Addons lists addon modules, and
        // extensions are exactly that here.
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
     * Issue #33's "- Cron Statistics": opens {@see AutomationStatus} (the WHMCS-shaped
     * display of the same data), with core's raw CronStats page claimed either way so the
     * Addons sweep does not resurface it. Falls back to core's page when the extension
     * page is unavailable.
     */
    /** {@see utilities()}'s "- HTTP Log" — the WHMCS-shaped log, never the raw list. */
    /**
     * "- Fixed Terms": the new-window-standard page {@see \Paymenter\Extensions\Others\AdminOps\Admin\Pages\FixedTerms},
     * carrying the same overdue-terms badge the raw resource had. Core's resource stays
     * claimed either way so the Addons sweep does not resurface it a second time.
     */
    private static function fixedTermsEntry(): ?NavigationItem
    {
        static::$placed[ServiceTermResource::class] = true;

        $badge = fn (): ?string => class_exists(ServiceTermResource::class)
            ? ServiceTermResource::getNavigationBadge()
            : null;

        $page = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\FixedTerms::class;

        if (class_exists($page) && $page::canAccess()) {
            static::$placed[$page] = true;
            $url = static::resolveUrl(fn (): string => $page::getUrl());

            if ($url !== null) {
                return NavigationItem::make('- Fixed Terms')
                    ->url($url)
                    ->isActiveWhen(fn (): bool => request()->url() === $url)
                    ->badge($badge, 'danger');
            }
        }

        return static::link(ServiceTermResource::class, '- Fixed Terms', badge: $badge, badgeColor: 'danger');
    }

    private static function httpLogEntry(): ?NavigationItem
    {
        static::$placed[HttpLogResource::class] = true;

        if (class_exists(HttpLogs::class) && HttpLogs::canAccess()) {
            $url = static::resolveUrl(fn (): string => HttpLogs::getUrl());

            if ($url !== null) {
                return NavigationItem::make('- HTTP Log')
                    ->url($url)
                    ->isActiveWhen(fn (): bool => request()->url() === $url);
            }
        }

        return static::link(HttpLogResource::class, '- HTTP Log');
    }

    /**
     * Email Campaigns is the email log's WHMCS face, so the raw EmailLogResource is
     * claimed with it — unclaimed it sat in the Addons sweep as a second "Email Logs"
     * in the old window standard (issue #11's complaint, spotted on its sibling).
     */
    private static function emailCampaignsEntry(): ?NavigationItem
    {
        static::$placed[EmailLogResource::class] = true;

        return static::page(EmailCampaigns::class, 'Email Campaigns');
    }

    /**
     * Issue #27's "Update Paymenter": opens {@see UpdatePaymenter} (the WHMCS-shaped
     * Update screen), with core's raw Updates page claimed either way so the Addons
     * sweep does not resurface it. Falls back to core's page when the extension page
     * is unavailable.
     */
    private static function updatePaymenterEntry(): ?NavigationItem
    {
        static::$placed[Updates::class] = true;

        $page = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\UpdatePaymenter::class;

        // The new page itself is claimed too — NavigationItem::make() does not mark
        // placed the way static::page() does, so the Addons sweep listed it a second
        // time in the raw style (seen in issue #11's screenshot).
        static::$placed[$page] = true;

        if (class_exists($page) && $page::canAccess()) {
            $url = static::resolveUrl(fn (): string => $page::getUrl());

            if ($url !== null) {
                return NavigationItem::make('Update Paymenter')
                    ->url($url)
                    ->isActiveWhen(fn (): bool => request()->url() === $url);
            }
        }

        return static::page(Updates::class, 'Update Paymenter');
    }

    private static function cronStatisticsEntry(): ?NavigationItem
    {
        static::$placed[CronStats::class] = true;

        if (class_exists(AutomationStatus::class) && AutomationStatus::canAccess()) {
            $url = static::resolveUrl(fn (): string => AutomationStatus::getUrl());

            if ($url !== null) {
                return NavigationItem::make('- Cron Statistics')
                    ->url($url)
                    ->isActiveWhen(fn (): bool => request()->url() === $url);
            }
        }

        return static::page(CronStats::class, '- Cron Statistics');
    }

    /** {@see page()}, plus the badge treatment {@see link()} gives a resource entry. */
    private static function pageWithBadge(string $page, string $label, \Closure $badge, string $badgeColor): ?NavigationItem
    {
        $item = static::page($page, $label);

        if ($item === null) {
            return null;
        }

        return $item->badge(function () use ($badge): ?string {
            try {
                $count = (int) $badge();

                return $count > 0 ? (string) $count : null;
            } catch (\Throwable $e) {
                return null;
            }
        }, $badgeColor);
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
