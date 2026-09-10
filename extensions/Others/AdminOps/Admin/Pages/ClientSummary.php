<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use App\Enums\InvoiceTransactionStatus;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\InvoiceTransaction;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Models\ClientNote;
use Paymenter\Extensions\Others\AdminOps\Support\Money;
use Paymenter\Extensions\Others\ClientTools\Models\Contact;

/**
 * The reference's **Client Profile**: one customer, one screen, in tabs.
 *
 * It began as a summary page beside Paymenter's own sub-pages, which was a misreading of the
 * reference — WHMCS has **one** client page and *Summary* is its first tab, not a second
 * screen. Everything else is a tab on the same page: Products/Services, Billable Items,
 * Invoices, Transactions, Tickets, Emails, Log. So this is that page, and Summary is where
 * it opens.
 *
 * Paymenter holds all of it already, spread over six sub-pages, so answering "who is this and
 * what is going on with them" — the first thing support does on every ticket — cost five page
 * loads. Now it costs one, and moving between tabs costs a query rather than a page.
 *
 * **A tab loads only its own data.** The obvious build renders everything and hides the rest
 * with CSS, which is fine for six rows and ruinous for a customer with four hundred invoices
 * — every visit would pay for every tab. `$tab` is a Livewire property and
 * {@see getViewData()} switches on it.
 *
 * Read-only by design: everything editable stays on the core pages that own it, so there is
 * one place a change can be made and one set of validation rules to trust.
 *
 * @link docs/02b-admin-area.md
 */
class ClientSummary extends Page
{
    protected string $view = 'adminops::pages.client-summary';

    protected static ?string $slug = 'client-summary';

    /**
     * Reached from the customer list, never from the sidebar — a summary with no customer
     * chosen would have nothing to show.
     */
    protected static bool $shouldRegisterNavigation = false;

    /**
     * The customer being summarised.
     *
     * Deliberately *not* called `$record`: Livewire assigns route parameters to public
     * properties of the same name before `mount()` runs, so a `public User $record` would
     * be handed the raw `{record}` string from the URL and fail on the type before this
     * page ever got to resolve it. Public rather than protected so Livewire rehydrates it
     * from its key on the follow-up request that runs the impersonate action.
     */
    public User $customer;

    /**
     * Which tab is showing.
     *
     * Public and query-stringed so a tab is a URL: support pastes "the Invoices tab of
     * customer 41" into a ticket and it opens there, which a tab held only in component
     * state cannot do.
     */
    #[Url]
    public string $tab = 'summary';

    /**
     * The Products/Services tab's selected service — the reference's per-service editor.
     * URL-bound so the order view's "Product/Service" link and the services list's ID
     * column land here with the service already open.
     */
    #[Url]
    public ?int $service = null;

    /** The service editor's form state. */
    public array $svc = [];

    /** The reference's inline Add New Addon form (user request, 2026-09-04). */
    public bool $addingAddon = false;

    /** @var array<string, mixed> */
    public array $addon = self::ADDON_DEFAULTS;

    private const ADDON_DEFAULTS = [
        'productId' => '', 'name' => '', 'quantity' => 1, 'price' => '', 'setupFee' => '',
        'status' => 'active', 'subscriptionId' => '', 'notes' => '', 'terminationDate' => '',
        'invoice' => true,
    ];

    /** The reference's Admin Notes box — stored as a user property, so it is real. */
    public string $adminNotes = '';

    /**
     * The Profile tab's editable fields — the reference's Profile is the client's edit
     * form, not a read-only sheet. Names and email live on the user; the rest are
     * properties, saved by {@see saveProfile()}.
     *
     * @var array<string, string>
     */
    public array $pf = [];

    /** @var array<string, bool> email_pref_* on the profile, editable */
    public array $pfPrefs = [];

    /** @var array<string, bool> setting_* on the profile, editable */
    public array $pfSettings = [];

    /**
     * The Profile tab's Client Group. Stored as the user property `client_group_id`, the
     * same way every other per-client value here is, so nothing on `users` changes.
     */
    public string $pfGroup = '';

    /** The property keys the Profile form edits besides the user's own columns. */
    private const PF_PROPS = ['company_name', 'address', 'address2', 'city', 'state', 'zip', 'country', 'phone', 'currency'];

    private const PREF_KEYS = ['general', 'invoice', 'support', 'product', 'domain', 'affiliate'];

    private const SETTING_KEYS = ['late_fees', 'overdue_notices', 'tax_exempt', 'separate_invoices', 'disable_cc', 'marketing_optin', 'status_update', 'single_sign_on'];

    /**
     * The reference's tabs, less the ones Paymenter has nothing behind.
     *
     * **Users** and **Contacts** are the reference's sub-account model, and ClientTools
     * turned out to have built exactly it: `ext_ct_contacts` carries a person on the
     * account, and `is_sub_account` with `permissions` is the reference's Associate User.
     * So both tabs are here and both write real records.
     *
     * Dropped deliberately rather than shown empty: **Domains** — removed from this store
     * entirely (§10 of the brief), so the tab would never hold a row. A tab that always
     * says "none" teaches people to stop opening tabs.
     *
     * @var array<string, string>
     */
    private const TABS = [
        'summary' => 'Summary',
        'profile' => 'Profile',
        'users' => 'Users',
        'contacts' => 'Contacts',
        'services' => 'Products/Services',
        'domains' => 'Domains',
        'billable' => 'Billable Items',
        'invoices' => 'Invoices',
        'quotes' => 'Quotes',
        'transactions' => 'Transactions',
        'tickets' => 'Tickets',
        'emails' => 'Emails',
        'notes' => 'Notes',
        'log' => 'Log',
    ];

    /**
     * Which page of the showing tab's list, for the reference's pagination band.
     *
     * Query-stringed alongside the tab so page two of someone's invoices is a URL, and reset
     * whenever the tab changes — page 3 of Invoices means nothing on Emails.
     */
    #[Url]
    public int $page = 1;

    /** Rows in the showing tab's list before paging, set by {@see paged()}. */
    private int $rowTotal = 0;

    public function updatedTab(): void
    {
        $this->page = 1;
    }

    /**
     * One page of a list, and the total behind it.
     *
     * Takes the query rather than a collection so the count is a COUNT and the page is a
     * LIMIT — a client with four hundred invoices should not load four hundred rows to show
     * twenty of them.
     */
    private function paged($query)
    {
        $this->rowTotal = (clone $query)->count();

        return $query->forPage(max(1, $this->page), self::TAB_ROWS)->get();
    }

    /** The same, for the lists that are assembled in PHP rather than queried. */
    private function pagedCollection(Collection $rows)
    {
        $this->rowTotal = $rows->count();

        return $rows->forPage(max(1, $this->page), self::TAB_ROWS)->values();
    }

    // ── Contacts ────────────────────────────────────────────────────────────────
    //
    // The reference picks a contact from a select whose last option is "Add New", then
    // edits one form. `contact` is the picked id, or '' while adding.

    public string $contact = '';

    public array $contactForm = [
        'first_name' => '', 'last_name' => '', 'email' => '', 'company_name' => '',
        'address' => '', 'address2' => '', 'city' => '', 'state' => '', 'zip' => '',
        'country' => '', 'phone' => '',
    ];

    /** The reference's Email Notifications ticks, as ClientTools stores them. */
    public array $contactPrefs = [];

    public bool $confirmingContactDelete = false;

    // ── Users ───────────────────────────────────────────────────────────────────

    public bool $associating = false;

    public string $associateContact = '';

    /** @var array<int, string> */
    public array $associatePermissions = [];

    // ── Notes ───────────────────────────────────────────────────────────────────

    public string $newNote = '';

    public bool $newNoteSticky = false;

    public ?int $confirmingNote = null;

    // ── Log filters ─────────────────────────────────────────────────────────────

    public array $logFilter = ['date' => '', 'description' => '', 'user' => '', 'ip' => ''];

    // ── The Summary tab's selection and Bulk Actions row ────────────────────────
    //
    // Leandro, 2026-09-07: "I want it to be 100% identical to the WHMCS one." The
    // reference's tables lead with a tick column and close with a With Selected row and a
    // Bulk Actions row; both were missing, and With Selected was a pair of dead buttons.

    /** @var array<int|string, bool> Ticked services, keyed by service id. */
    public array $picked = [];

    /** pending | active | suspended | cancelled — the reference's "- Set Status -". */
    public string $bulkStatus = '';

    public bool $bulkHold = false;

    public string $bulkHoldUntil = '';

    /** Which bulk action is awaiting its "Are you sure?" — invoice | delete | apply. */
    public ?string $confirmingBulk = null;

    /** @return array<int> */
    private function pickedIds(): array
    {
        return array_map('intval', array_keys(array_filter($this->picked)));
    }

    /**
     * The Summary table's "+": open that service in the Products/Services tab without a
     * page load, which is this screen's equivalent of the catalogue page expanding a row
     * in place.
     */
    public function openService(int $id): void
    {
        $this->service = $id;
        $this->tab = 'services';
        $this->loadSvc();
    }

    /** The header tick: all of this client's services, or none. */
    public function toggleAll(bool $on): void
    {
        $this->picked = $on
            ? Service::where('user_id', $this->customer->id)->pluck('id')->mapWithKeys(fn ($id) => [$id => true])->all()
            : [];
    }

    public function askBulk(string $action): void
    {
        if ($this->pickedIds() === []) {
            Notification::make()->title('Tick at least one item first.')->warning()->send();

            return;
        }

        $this->confirmingBulk = $action;
    }

    public function runBulk(): void
    {
        $action = $this->confirmingBulk;
        $this->confirmingBulk = null;

        // Re-read from the database rather than trusting the ids: a tick is client-side,
        // and only this customer's services may be touched from this customer's page.
        $services = Service::whereIn('id', $this->pickedIds())
            ->where('user_id', $this->customer->id)->get();

        if ($services->isEmpty()) {
            return;
        }

        match ($action) {
            'invoice' => $this->invoiceSelected($services),
            'delete' => $this->deleteSelected($services),
            'apply' => $this->applyBulk($services),
            default => null,
        };

        $this->picked = [];
    }

    /**
     * The reference's "Invoice Selected Items": one invoice for the client carrying a line
     * per ticked service, each line referencing the service it bills — the same shape
     * core's own renewal invoices have, so payment flows through it unchanged.
     */
    private function invoiceSelected($services): void
    {
        if (!InvoiceResource::canCreate()) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        // One currency per invoice: an invoice has a single currency_code, so services
        // priced in two currencies cannot share one. The reference never faces this
        // because it converts everything to the client's currency.
        $currencies = $services->pluck('currency_code')->unique();

        if ($currencies->count() > 1) {
            Notification::make()->title('Mixed currencies')
                ->body('The ticked services are priced in ' . $currencies->implode(', ')
                    . '. Invoice one currency at a time.')->danger()->send();

            return;
        }

        $invoice = DB::transaction(function () use ($services, $currencies) {
            $invoice = Invoice::create([
                'user_id' => $this->customer->id,
                'currency_code' => $currencies->first(),
                'due_at' => now()->addDays(7),
                'status' => 'pending',
            ]);

            foreach ($services as $service) {
                $invoice->items()->create([
                    'description' => ($service->product?->name ?? 'Service') . ' #' . $service->id,
                    'price' => (float) $service->price,
                    'quantity' => max(1, (int) $service->quantity),
                    'reference_id' => $service->id,
                    'reference_type' => Service::class,
                ]);
            }

            return $invoice;
        });

        Notification::make()->title('Invoice #' . ($invoice->number ?: $invoice->id) . ' created')
            ->body($services->count() . ' item(s), due in 7 days.')->success()->send();
    }

    /** The reference's "Delete Selected Items" — the record, not the provisioned service. */
    private function deleteSelected($services): void
    {
        if (!ServiceResource::canViewAny()) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $active = $services->whereIn('status', ['active', 'suspended']);

        if ($active->isNotEmpty()) {
            // Deleting the row leaves whatever the panel provisioned running and unbilled,
            // which is the one outcome nobody wants from a bulk button.
            Notification::make()->title('Terminate first')
                ->body('Service(s) ' . $active->pluck('id')->implode(', ') . ' are still live. '
                    . 'Terminate them before deleting the records.')->danger()->send();

            return;
        }

        $count = $services->count();
        Service::whereIn('id', $services->pluck('id'))->delete();

        Notification::make()->title($count . ' service record(s) deleted')->success()->send();
    }

    /** The reference's Bulk Actions row: Set Status, and Do Not Suspend Until. */
    private function applyBulk($services): void
    {
        if ($this->bulkStatus === '' && !$this->bulkHold) {
            Notification::make()->title('Nothing to apply')
                ->body('Choose a status, or set a "do not suspend until" date.')->warning()->send();

            return;
        }

        $until = null;

        if ($this->bulkHold) {
            foreach (['m/d/Y', 'Y-m-d'] as $format) {
                try {
                    $until = \Carbon\Carbon::createFromFormat($format, trim($this->bulkHoldUntil));
                    break;
                } catch (\Throwable $e) {
                }
            }

            if (!$until) {
                Notification::make()->title('Enter a date as MM/DD/YYYY')->danger()->send();

                return;
            }
        }

        foreach ($services as $service) {
            if ($this->bulkStatus !== '') {
                $service->update(['status' => $this->bulkStatus]);
            }

            if ($until) {
                // The same property the service editor writes, enforced by the same hourly
                // ServiceOverrides sweep — not a second mechanism doing the same job.
                $service->properties()->updateOrCreate(
                    ['key' => 'no_suspend_until'],
                    ['name' => 'Do Not Suspend Until', 'value' => $until->format('Y-m-d')],
                );
            }
        }

        Notification::make()->title($services->count() . ' service(s) updated')->success()->send();
        $this->reset(['bulkStatus', 'bulkHold', 'bulkHoldUntil']);
    }

    /**
     * The tab labels, with the reference's live "Notes (n)" count.
     *
     * @return array<string, string>
     */
    private function tabLabels(): array
    {
        $labels = self::TABS;

        // The reference's "Notes (n)" counts the notes on the account. It used to count the
        // one free-text field, so it could only ever say 0 or 1 — the tab is a list now.
        $labels['notes'] = 'Notes (' . number_format(
            Schema::hasTable('ext_ao_client_notes')
                ? ClientNote::where('user_id', $this->customer->id)->count()
                : 0
        ) . ')';

        return $labels;
    }

    /** How many rows of each kind on the Summary tab before "see all" takes over. */
    private const ROWS = 8;

    /** A tab of its own is a list, not a preview, so it can afford to be longer. */
    private const TAB_ROWS = 50;

    /**
     * The record is part of the path rather than the slug so the route keeps a clean name
     * (`…pages.client-summary`); putting `{record}` in `$slug` would bake the braces into
     * the route name, which `getUrl()` then has to match literally.
     */
    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record}';
    }

    public function mount(int|string $record): void
    {
        static::authorizeResourceAccess();

        $this->customer = User::query()
            ->with(['role', 'credits', 'properties.parent_property'])
            ->findOrFail($record);

        $this->adminNotes = (string) $this->customer->properties
            ->firstWhere('key', 'admin_notes')?->value;

        $prop = fn (string $key): string => (string) $this->customer->properties->firstWhere('key', $key)?->value;

        $this->pf = [
            'first_name' => (string) $this->customer->first_name,
            'last_name' => (string) $this->customer->last_name,
            'email' => $this->customer->email,
            ...collect(self::PF_PROPS)->mapWithKeys(fn ($key) => [$key => $prop($key)])->all(),
        ];

        foreach (self::PREF_KEYS as $key) {
            // Missing means the client predates the standard; the standard default is on.
            $this->pfPrefs[$key] = $prop('email_pref_' . $key) !== '0';
        }

        $settingDefaults = ['late_fees' => true, 'overdue_notices' => true, 'status_update' => true, 'single_sign_on' => true];

        foreach (self::SETTING_KEYS as $key) {
            $held = $prop('setting_' . $key);
            $this->pfSettings[$key] = $held === '' ? ($settingDefaults[$key] ?? false) : $held === '1';
        }

        $this->pfGroup = $prop('client_group_id');

        if ($this->service) {
            $this->loadSvc();
        }
    }

    // ── The Products/Services tab's service editor ───────────────────────────
    // The reference's per-service screen inside the client profile: reached from the
    // order view's "Product/Service" link and the services list's ID column.

    public function updatedService(): void
    {
        $this->loadSvc();
    }

    /** The tab's own picker: choose a service, press Go. */
    public function goService(?int $id): void
    {
        $this->service = $id ?: null;
        $this->loadSvc();
    }

    private function loadSvc(): void
    {
        $service = $this->service
            ? $this->customer->services()
                ->with(['product.server', 'plan', 'properties', 'configs.configOption', 'coupon'])
                ->find($this->service)
            : null;

        if (!$service) {
            $this->service = null;
            $this->svc = [];

            return;
        }

        $prop = fn (string $key): string => (string) $service->properties->firstWhere('key', $key)?->value;

        $this->svc = [
            'productId' => $service->product_id,
            'planId' => $service->plan_id,
            'quantity' => (int) $service->quantity,
            'price' => number_format((float) $service->price, 2, '.', ''),
            'status' => (string) $service->status,
            'regDate' => $service->created_at?->format('m/d/Y') ?? '',
            'nextDue' => $service->expires_at?->format('m/d/Y') ?? '',
            'subscriptionId' => (string) $service->subscription_id,
            'domain' => $prop('domain'),
            'dedicatedIp' => $prop('dedicated_ip'),
            'username' => $prop('proxy_username'),
            'password' => $prop('proxy_password'),
            'svcNotes' => $prop('admin_notes'),
            // The reference's Service ID and api-key rows — always present, empty until
            // the panel provisions them (user feedback, 2026-09-04: they must not vanish
            // on an unprovisioned service). The keys are ProxyPanel's own.
            'serviceId' => $prop('proxypanel_service_id'),
            'apiKey' => $prop('proxy_api_key'),
            // WHMCS's Termination Date: the day this service ends regardless of renewals.
            // Stored as a property; saving one writes/updates the real end-of-period
            // cancellation the Cancellations sweeper terminates on (see saveService).
            'terminationDate' => $prop('termination_date'),
            // WHMCS's Override Auto-Suspend: the overdue ladder leaves this service alone
            // until the date passes. Enforced by AdminOps::boot()'s post-cron restore.
            'noSuspend' => $prop('no_suspend_until') !== '',
            'noSuspendUntil' => $prop('no_suspend_until'),
            // WHMCS's Auto-Terminate End of Cycle: an end-of-period cancellation with a
            // reason, written as the real ServiceCancellation row core already honours.
            'autoTerminate' => (bool) $service->cancellation,
            'autoTerminateReason' => (string) ($service->cancellation?->reason ?? ''),
            // The remaining properties (Proxies, Service ID, api-key…), editable as the
            // reference's custom fields are. A list, not a key-map: property keys can
            // carry characters wire:model paths cannot.
            'props' => $service->properties
                ->whereNotIn('key', ['domain', 'dedicated_ip', 'admin_notes', 'proxy_username', 'proxy_password', 'termination_date', 'no_suspend_until', 'proxypanel_service_id', 'proxy_api_key'])
                ->map(fn ($property): array => [
                    'key' => (string) $property->key,
                    'name' => (string) ($property->name ?: $property->key),
                    'value' => (string) $property->value,
                ])->values()->all(),
            // The reference's Region select and friends: each config option, its picked
            // value editable among that option's own children.
            'configs' => $service->configs
                ->mapWithKeys(fn ($config) => [(string) $config->config_option_id => (string) $config->config_value_id])
                ->all(),
        ];
    }

    public function toggleAddingAddon(): void
    {
        $this->addingAddon = !$this->addingAddon;
    }

    /** Prefill the recurring amount from the picked addon product's own plan. */
    public function updatedAddonProductId(): void
    {
        if (!$this->addon['productId']) {
            return;
        }

        $plan = \App\Models\Plan::where('priceable_type', \App\Models\Product::class)
            ->where('priceable_id', (int) $this->addon['productId'])->first();
        $currency = config('settings.default_currency', 'USD');
        $this->addon['price'] = number_format((float) ($plan?->price($currency)->price ?? 0), 2, '.', '');
    }

    /**
     * The reference's Add New Addon, inline on the Products/Services tab (it used to
     * be a redirect to the Service Addons page — user feedback, 2026-09-04). Identical
     * mechanics to ServiceAddons::attach(): the addon is a real service row sharing the
     * parent's order and due date, linked in ext_service_addons; plus the reference's
     * Custom Name (the service's label column), Status, and Generate Invoice after
     * Adding (an invoice made exactly the way the daily cron makes one).
     */
    public function saveAddon(): void
    {
        $this->validate([
            'addon.productId' => 'required|exists:products,id',
            'addon.quantity' => 'required|integer|min:1',
            'addon.price' => 'required|numeric|min:0',
            'addon.setupFee' => 'nullable|numeric|min:0',
            'addon.status' => 'required|in:pending,active',
            'addon.name' => 'nullable|string|max:255',
            'addon.subscriptionId' => 'nullable|string|max:255',
            'addon.notes' => 'nullable|string|max:2000',
        ], attributes: ['addon.productId' => 'predefined addon', 'addon.quantity' => 'quantity', 'addon.price' => 'recurring amount', 'addon.setupFee' => 'setup fee', 'addon.status' => 'status']);

        $parent = $this->customer->services()->findOrFail($this->service);
        $product = \App\Models\Product::findOrFail((int) $this->addon['productId']);
        $plan = \App\Models\Plan::where('priceable_type', \App\Models\Product::class)
            ->where('priceable_id', $product->id)->first();

        // Same guard as ServiceAddons::attach() — a plan-less service 500s core's own
        // pages wherever the plan is dereferenced (issue #4, seen live).
        if (!$plan) {
            $this->addError('addon.productId', 'This addon has no price plan yet. Open the addon product in the catalogue and add a monthly price first.');

            return;
        }

        DB::transaction(function () use ($parent, $product, $plan): void {
            $service = \App\Models\Service::create([
                'order_id' => $parent->order_id,
                'product_id' => $product->id,
                'plan_id' => $plan->id,
                'quantity' => max(1, (int) $this->addon['quantity']),
                'price' => (float) $this->addon['price'],
                'status' => $this->addon['status'],
                'user_id' => $parent->user_id,
                'currency_code' => $parent->currency_code,
                'expires_at' => $parent->expires_at,
                'subscription_id' => trim((string) $this->addon['subscriptionId']) ?: null,
            ]);

            // The reference's Custom Name. `label` is a real column with a product-name
            // fallback accessor, but core keeps it out of $fillable — forceFill, or the
            // name silently vanishes (caught by the live test on first deploy).
            if (trim((string) $this->addon['name']) !== '') {
                $service->forceFill(['label' => trim((string) $this->addon['name'])])->save();
            }

            if (trim((string) $this->addon['notes']) !== '') {
                $service->properties()->updateOrCreate(['key' => 'admin_notes'], ['name' => 'Admin Notes', 'value' => trim((string) $this->addon['notes'])]);
            }

            // The reference's Termination Date on the addon — the same property the
            // ServiceOverrides sweep enforces on any service.
            $termDay = null;
            foreach (['m/d/Y', 'Y-m-d'] as $format) {
                try {
                    $termDay = \Carbon\Carbon::createFromFormat($format, trim((string) $this->addon['terminationDate']));
                    break;
                } catch (\Throwable $e) {
                }
            }
            if ($termDay) {
                $service->properties()->updateOrCreate(['key' => 'termination_date'], ['name' => 'Termination Date', 'value' => $termDay->format('Y-m-d')]);
            }

            \Paymenter\Extensions\Others\AdminOps\Models\ServiceAddon::create([
                'service_id' => $service->id,
                'parent_service_id' => $parent->id,
            ]);

            // The reference's "Generate Invoice after Adding" — the exact shape the
            // daily cron's invoices_created pass writes, plus the reference's Setup Fee
            // as its own one-time line on that first invoice.
            $setupFee = (float) ($this->addon['setupFee'] ?: 0);

            if (($this->addon['invoice'] ?? false) && ((float) $this->addon['price'] > 0 || $setupFee > 0)) {
                $invoice = $service->invoices()->make([
                    'user_id' => $service->user_id,
                    'status' => 'pending',
                    'due_at' => $service->expires_at ?? now()->addDays(7),
                    'currency_code' => $service->currency_code,
                ]);
                $invoice->save();

                if ((float) $this->addon['price'] > 0) {
                    $invoice->items()->create([
                        'reference_id' => $service->id,
                        'reference_type' => \App\Models\Service::class,
                        'price' => $service->price,
                        'quantity' => $service->quantity,
                        'description' => $service->description,
                    ]);
                }

                if ($setupFee > 0) {
                    $invoice->items()->create([
                        'reference_id' => null,
                        'reference_type' => null,
                        'price' => $setupFee,
                        'quantity' => 1,
                        'description' => ($service->label ?: $product->name) . ' — Setup Fee',
                    ]);
                }
            }
        });

        $invoiced = ($this->addon['invoice'] ?? false)
            && ((float) $this->addon['price'] > 0 || (float) ($this->addon['setupFee'] ?: 0) > 0);
        $this->addingAddon = false;
        $this->addon = self::ADDON_DEFAULTS;
        Notification::make()->title('Addon added')
            ->body('It renews with its parent service.' . ($invoiced ? ' An invoice for it is now pending.' : ''))->success()->send();
    }

    /** The editor's Save Changes — every field lands on the real column or property. */
    public function saveService(): void
    {
        $service = $this->customer->services()->find($this->service);

        if (!$service) {
            return;
        }

        $this->validate([
            'svc.quantity' => 'required|integer|min:1',
            'svc.price' => 'required|numeric|min:0',
            'svc.status' => 'required|in:pending,active,suspended,cancelled',
            'svc.productId' => 'required|exists:products,id',
            'svc.planId' => 'required|exists:plans,id',
        ], attributes: ['svc.quantity' => 'quantity', 'svc.price' => 'first payment amount', 'svc.status' => 'status', 'svc.productId' => 'product', 'svc.planId' => 'billing cycle']);

        $parseDay = function (string $text): ?\Carbon\Carbon {
            foreach (['m/d/Y', 'Y-m-d'] as $format) {
                try {
                    return \Carbon\Carbon::createFromFormat($format, trim($text));
                } catch (\Throwable $e) {
                }
            }

            return null;
        };

        $nextDue = $parseDay((string) $this->svc['nextDue']);
        $regDate = $parseDay((string) ($this->svc['regDate'] ?? ''));
        $termDate = $parseDay((string) ($this->svc['terminationDate'] ?? ''));
        $noSuspendUntil = $parseDay((string) ($this->svc['noSuspendUntil'] ?? ''));

        DB::transaction(function () use ($service, $nextDue, $regDate, $termDate, $noSuspendUntil): void {
            $service->update([
                'product_id' => (int) $this->svc['productId'],
                'plan_id' => (int) $this->svc['planId'],
                'quantity' => max(1, (int) $this->svc['quantity']),
                'price' => (float) $this->svc['price'],
                'status' => $this->svc['status'],
                'subscription_id' => trim((string) $this->svc['subscriptionId']) ?: null,
                'expires_at' => $nextDue,
            ]);

            // The reference's editable Registration Date — the same column, corrected.
            if ($regDate && !$regDate->isSameDay($service->created_at ?? now())) {
                $service->created_at = $regDate->setTimeFrom($service->created_at ?? now());
                $service->save();
            }

            $namedProps = [
                'domain' => ['Domain', trim((string) $this->svc['domain'])],
                'dedicated_ip' => ['Dedicated IP', trim((string) $this->svc['dedicatedIp'])],
                'admin_notes' => ['Admin Notes', trim((string) $this->svc['svcNotes'])],
                'proxy_username' => ['Username', trim((string) $this->svc['username'])],
                'proxy_password' => ['Password', trim((string) $this->svc['password'])],
                // The reference's Termination Date — enforced by ServiceOverrides' hourly
                // sweep, which terminates the service once the date passes.
                'termination_date' => ['Termination Date', $termDate?->format('Y-m-d') ?? ''],
                // The reference's Override Auto-Suspend — the same sweep un-suspends a
                // service the overdue ladder caught while this date is still ahead.
                'no_suspend_until' => ['Do Not Suspend Until', ($this->svc['noSuspend'] ?? false) ? ($noSuspendUntil?->format('Y-m-d') ?? '') : ''],
            ];

            foreach ($namedProps as $key => [$name, $value]) {
                if ($value !== '') {
                    $service->properties()->updateOrCreate(['key' => $key], ['name' => $name, 'value' => $value]);
                } else {
                    $service->properties()->where('key', $key)->delete();
                }
            }

            // Service ID and api-key — editable corrections, but an emptied box keeps
            // its row: these are the module's provisioning references, and blanking one
            // by accident must not sever the panel link (same rule as the props loop).
            foreach (['proxypanel_service_id' => ['Service ID', trim((string) ($this->svc['serviceId'] ?? ''))],
                'proxy_api_key' => ['api-key', trim((string) ($this->svc['apiKey'] ?? ''))]] as $key => [$name, $value]) {
                if ($value !== '') {
                    $service->properties()->updateOrCreate(['key' => $key], ['name' => $name, 'value' => $value]);
                }
            }

            // The custom-field rows: whatever the module stored, edited in place. An
            // emptied field keeps its row — clearing a provisioning reference by accident
            // should not delete the record of it having existed.
            foreach ($this->svc['props'] ?? [] as $row) {
                $service->properties()->where('key', $row['key'])->update(['value' => (string) $row['value']]);
            }

            // The reference's Auto-Terminate End of Cycle: written as the real
            // end-of-period ServiceCancellation core already reads (it stops the next
            // invoice) and the Cancellations extension terminates when due. Unticking it
            // deletes the row, which is core's own definition of un-cancelling.
            if ($this->svc['autoTerminate'] ?? false) {
                $service->cancellation()->updateOrCreate([], [
                    'type' => 'end_of_period',
                    'reason' => trim((string) ($this->svc['autoTerminateReason'] ?? '')) ?: null,
                ]);
            } else {
                $service->cancellation()->delete();
            }

            // The reference's Region (and any other config option): the picked value must
            // be one of that option's own children, or the row stays as it was.
            foreach ($this->svc['configs'] ?? [] as $optionId => $valueId) {
                if (!ctype_digit((string) $valueId)) {
                    continue;
                }

                $valid = \App\Models\ConfigOption::where('parent_id', (int) $optionId)
                    ->where('id', (int) $valueId)->exists();

                if ($valid) {
                    $service->configs()->where('config_option_id', (int) $optionId)
                        ->update(['config_value_id' => (int) $valueId]);
                }
            }
        });

        $this->loadSvc();
        Notification::make()->title('Service saved')->success()->send();
    }

    /**
     * The reference's Module Commands, over core's own provisioning jobs — the same
     * dispatches checkout and the daily run make. Each is refused, not faked, when the
     * service's state cannot take it.
     */
    public function runModule(string $command): void
    {
        $service = $this->customer->services()->with('product')->find($this->service);

        if (!$service) {
            return;
        }

        if (!$service->product?->server) {
            Notification::make()->title('No server module')
                ->body('This product has no server attached, so there is nothing to command.')->danger()->send();

            return;
        }

        try {
            $ran = match (true) {
            $command === 'create' && $service->status === 'pending' => (function () use ($service): string {
                \App\Jobs\Server\CreateJob::dispatch($service);
                $service->status = 'active';
                $service->expires_at = $service->calculateNextDueDate();
                $service->save();

                return 'Create dispatched — the service is provisioning and now reads Active.';
            })(),
            $command === 'suspend' && $service->status === 'active' => (function () use ($service): string {
                \App\Jobs\Server\SuspendJob::dispatch($service);
                $service->update(['status' => 'suspended']);

                return 'Suspend dispatched.';
            })(),
            $command === 'unsuspend' && $service->status === 'suspended' => (function () use ($service): string {
                \App\Jobs\Server\UnsuspendJob::dispatch($service);
                $service->update(['status' => 'active']);

                return 'Unsuspend dispatched.';
            })(),
            $command === 'terminate' && in_array($service->status, ['active', 'suspended'], true) => (function () use ($service): string {
                \App\Jobs\Server\TerminateJob::dispatch($service);
                $service->update(['status' => 'cancelled']);

                return 'Terminate dispatched.';
            })(),
            // WHMCS's ChangePackage module command: push the currently-saved product/plan
            // to the panel — the same UpgradeJob checkout's paid upgrade path dispatches.
            // Save Changes with a different Product/Service picked, then this.
            $command === 'change_package' && $service->status === 'active' => (function () use ($service): string {
                \App\Jobs\Server\UpgradeJob::dispatch($service);

                return 'Change Package dispatched — the panel is updating to the saved product and plan.';
            })(),
            // WHMCS's ChangePassword module command, where the module supports it. The
            // proxy panel does (ProxyPanel::changePassword(Service, string) — called via
            // ExtensionHelper::call, not callService, whose settings/properties prelude
            // would land in the password parameter). A module without the method throws
            // "Function not found", which the catch turns into an honest refusal.
            $command === 'change_password' && in_array($service->status, ['active', 'suspended'], true) => (function () use ($service): string {
                $password = str()->random(12);

                try {
                    \App\Helpers\ExtensionHelper::call($service->product->server, 'changePassword', [$service, $password]);
                } catch (\Throwable $e) {
                    throw new \RuntimeException(str_contains($e->getMessage(), 'not found')
                        ? 'This server module has no password command.'
                        : 'The panel refused the password change: ' . $e->getMessage());
                }

                $service->properties()->updateOrCreate(['key' => 'proxy_password'], ['name' => 'Password', 'value' => $password]);

                return 'Password changed on the panel. The new one is in the Password field.';
            })(),
            default => null,
            };
        } catch (\Throwable $e) {
            Notification::make()->title('Command failed')->body($e->getMessage())->danger()->send();

            return;
        }

        if ($ran === null) {
            Notification::make()->title('Nothing to do')
                ->body(str_replace('_', ' ', ucfirst($command)) . ' does not apply to a ' . $service->status . ' service.')->warning()->send();

            return;
        }

        $this->loadSvc();
        Notification::make()->title($ran)->success()->send();
    }

    /**
     * Everything the editor's blade reads for the selected service.
     *
     * @return array<string, mixed>
     */
    private function serviceEditorData(): array
    {
        $service = $this->customer->services()
            ->with(['product.category', 'product.server', 'plan', 'properties', 'configs.configOption',
                'coupon', 'invoices.transactions.gateway'])
            ->find($this->service);

        if (!$service) {
            return ['svcModel' => null];
        }

        return [
            'svcModel' => $service,
            'svcProducts' => \App\Models\Product::with('category')->orderBy('name')->get(['id', 'name', 'category_id']),
            'svcPlans' => \App\Models\Plan::where('priceable_type', \App\Models\Product::class)
                ->where('priceable_id', $this->svc['productId'] ?? $service->product_id)->get(),
            'svcAddons' => class_exists(\Paymenter\Extensions\Others\AdminOps\Models\ServiceAddon::class)
                ? \Paymenter\Extensions\Others\AdminOps\Models\ServiceAddon::with('service.product')
                    ->where('parent_service_id', $service->id)->get()
                : collect(),
            // The inline Add New Addon form's catalogue — the Service Addons category,
            // the same source the Service Addons page attaches from.
            'addonCatalogue' => \App\Models\Product::whereIn('category_id',
                \App\Models\Category::where('name', ServiceAddons::CATEGORY)->pluck('id'))
                ->orderBy('name')->get(['id', 'name']),
            'svcPayment' => $service->invoices->flatMap->transactions->first()?->gateway?->name ?? '—',
            // Each config option with its child values, for the editable Region-style selects.
            'svcConfigChoices' => $service->configs->mapWithKeys(fn ($config) => [
                (string) $config->config_option_id => [
                    'label' => $config->configOption?->name ?? 'Option',
                    'values' => \App\Models\ConfigOption::where('parent_id', $config->config_option_id)
                        ->orderBy('sort')->orderBy('name')->get(['id', 'name']),
                ],
            ])->all(),
        ];
    }

    /** The Profile tab's Save Changes. Everything it writes is readable back on this page. */
    public function saveProfile(): void
    {
        $this->validate([
            'pf.first_name' => ['required', 'string', 'max:255'],
            'pf.last_name' => ['required', 'string', 'max:255'],
            'pf.email' => ['required', 'email', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($this->customer->id)],
        ]);

        DB::transaction(function (): void {
            $this->customer->update([
                'first_name' => $this->pf['first_name'],
                'last_name' => $this->pf['last_name'],
                'email' => $this->pf['email'],
            ]);

            foreach (self::PF_PROPS as $key) {
                $value = trim((string) ($this->pf[$key] ?? ''));

                if ($value !== '') {
                    $this->customer->properties()->updateOrCreate(['key' => $key], ['value' => $value]);
                }
            }

            foreach ($this->pfPrefs as $key => $on) {
                $this->customer->properties()->updateOrCreate(['key' => 'email_pref_' . $key], ['value' => $on ? '1' : '0']);
            }

            foreach ($this->pfSettings as $key => $on) {
                $this->customer->properties()->updateOrCreate(['key' => 'setting_' . $key], ['value' => $on ? '1' : '0']);
            }

            // Ungrouped is the absence of the row, not a row saying "0" — the sweep and
            // the discount both test for membership by the property existing.
            if ($this->pfGroup === '') {
                $this->customer->properties()->where('key', 'client_group_id')->delete();
            } else {
                $this->customer->properties()->updateOrCreate(
                    ['key' => 'client_group_id'], ['value' => $this->pfGroup],
                );
            }
        });

        $this->customer->refresh()->load('properties.parent_property');

        Notification::make()->title('Profile saved')->success()->send();
    }

    /** The Admin Notes Submit button. Notes live on the customer, as the reference keeps them. */
    public function saveNotes(): void
    {
        $this->customer->properties()->updateOrCreate(
            ['key' => 'admin_notes'],
            ['value' => $this->adminNotes],
        );

        Notification::make()
            ->title('Notes saved')
            ->success()
            ->send();
    }

    public static function authorizeResourceAccess(): void
    {
        abort_unless(UserResource::canViewAny(), 403);
    }

    /**
     * The one URL a "edit this service" link should carry: the Client Profile's
     * Products/Services editor with the service open (user request, 2026-09-04 — core's
     * raw resource edit is not the WHMCS screen, and cannot be reskinned from an
     * extension). Accepts the model when the caller has it; an id costs one lookup.
     */
    public static function serviceUrl(int|\App\Models\Service $service): string
    {
        if (!$service instanceof \App\Models\Service) {
            $service = \App\Models\Service::findOrFail($service);
        }

        return static::getUrl(['record' => $service->user_id, 'tab' => 'services', 'service' => $service->id]);
    }

    /**
     * The reference's h1 is the page's name, not the client's — the client is named by the
     * picker under it and the "#id - name" heading. Repeating them in the header was
     * Leandro's circled duplicate.
     */
    public function getTitle(): string
    {
        return 'Client Profile';
    }

    protected function getHeaderActions(): array
    {
        return [
            // The supported way for an administrator to see a customer's account. Kept
            // identical to core's action on the user edit page — same session key, same
            // landing page — so there is one impersonation mechanism, not two.
            Action::make('impersonate')
                ->label('Log in as customer')
                ->icon(Heroicon::ArrowRightOnRectangle)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Log in as this customer?')
                ->modalDescription('You will browse the client area as them until you return to the admin panel.')
                ->action(function () {
                    session()->put('impersonating', $this->customer->id);

                    $this->redirect('/dashboard');
                })
                ->visible(fn (): bool => Auth::user()->hasPermission('admin.users.impersonate')
                    && Auth::id() !== $this->customer->id),

            Action::make('edit')
                ->label('Edit customer')
                ->icon(Heroicon::PencilSquare)
                ->color('gray')
                ->url(fn (): string => UserResource::getUrl('edit', ['record' => $this->customer]))
                ->visible(fn (): bool => UserResource::canEdit($this->customer)),

            Action::make('newInvoice')
                ->label('New invoice')
                ->icon(Heroicon::DocumentPlus)
                ->color('gray')
                ->url(fn (): string => InvoiceResource::getUrl('create'))
                ->visible(fn (): bool => InvoiceResource::canCreate()),
        ];
    }

    /**
     * Only the showing tab's data.
     *
     * The Summary tab keeps its eight-row previews of everything; every other tab is a list
     * of one thing, longer, and costs a query only when opened.
     */
    protected function getViewData(): array
    {
        return [
            'user' => $this->customer,
            'tabs' => $this->tabLabels(),
            'clientGroup' => \Paymenter\Extensions\Others\AdminOps\Support\ClientGroup::forUser($this->customer->id),
            'clientGroups' => Schema::hasTable('ext_client_groups')
                ? DB::table('ext_client_groups')->orderBy('name')->get()
                : collect(),
            'tab' => array_key_exists($this->tab, self::TABS) ? $this->tab : 'summary',
            'urls' => $this->urls(),
            'countries' => (function (): array {
                $countries = config('app.countries');
                unset($countries['']);

                return $countries;
            })(),
            'hasContacts' => $this->hasContacts(),
            // The Summary panel lists them; the Contacts tab edits them.
            'summaryContacts' => $this->contactRows()->take(5),
            // Filled by paged()/pagedCollection() while the tab above loaded its rows.
            'rowTotal' => fn () => $this->rowTotal,
            'perPage' => self::TAB_ROWS,
            'page' => max(1, $this->page),
            'clientsList' => User::query()
                ->whereNull('role_id')
                ->orderBy('first_name')
                ->limit(200)
                // The reference names each option "name (company) - #id"; one eager load
                // beats a company query per option.
                ->with(['properties' => fn ($q) => $q->where('key', 'company_name')])
                ->get(['id', 'first_name', 'last_name', 'email']),
            ...match (array_key_exists($this->tab, self::TABS) ? $this->tab : 'summary') {
                'services' => (function (): array {
                    $rows = $this->customer->services()->with('product')->latest()->limit(self::TAB_ROWS)->get();

                    // The tab always opens ON a service, as the reference does — landing
                    // with none picked showed a bare list in a different shape from the
                    // editor one click later (user feedback, 2026-09-04). First service
                    // by default; a client with none keeps the honest empty list.
                    if (!$this->service && $rows->isNotEmpty()) {
                        $this->service = $rows->first()->id;
                        $this->loadSvc();
                    }

                    return [
                        'rows' => $rows,
                        ...($this->service ? $this->serviceEditorData() : ['svcModel' => null]),
                    ];
                })(),
                'billable' => ['rows' => $this->billableItems()],
                'invoices' => ['rows' => $this->paged($this->customer->invoices()->with(['items', 'transactions'])->latest())],
                // The reference heads both of these with a band of four figures.
                'transactions' => (function (): array {
                    $rows = $this->transactionRows();

                    return [
                        'rows' => $rows,
                        'totals' => [
                            'in' => $rows->sum(fn (array $row): float => (float) $row['in']),
                            // The reference's fourth figure, and a real one: core records
                            // what the gateway took on `invoice_transactions.fee`. It was
                            // drawn inert on the belief that nothing stored it.
                            'fees' => $rows->sum(fn (array $row): float => (float) ($row['fee'] ?? 0)),
                            'out' => $rows->sum(fn (array $row): float => (float) $row['out']),
                        ],
                    ];
                })(),
                'tickets' => (function (): array {
                    $opened = fn (Carbon $from, Carbon $to): int => $this->customer->tickets()
                        ->whereBetween('created_at', [$from, $to])->count();

                    return [
                        'rows' => $this->paged($this->customer->tickets()->latest()),
                        'ticketStats' => [
                            'Opened This Month' => $opened(now()->startOfMonth(), now()->endOfMonth()),
                            'Opened Last Month' => $opened(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()),
                            'Opened This Year' => $opened(now()->startOfYear(), now()->endOfYear()),
                            'Opened Last Year' => $opened(now()->subYear()->startOfYear(), now()->subYear()->endOfYear()),
                        ],
                    ];
                })(),
                'emails' => ['rows' => $this->emailRows()],
                'log' => (function (): array {
                    $rows = $this->logRows();

                    return [
                        'rows' => $rows,
                        // The Username select offers who has actually touched this account,
                        // rather than every admin on the install.
                        'logUsers' => User::whereIn(
                            'id',
                            DB::table('audits')->whereNotNull('user_id')
                                ->where(fn ($q) => $q->where(fn ($s) => $s->where('auditable_type', User::class)
                                    ->where('auditable_id', $this->customer->id))
                                    ->orWhere('user_id', $this->customer->id))
                                ->distinct()->pluck('user_id'),
                        )->get(['id', 'first_name', 'last_name', 'email']),
                    ];
                })(),
                // The reference's Domains tab. Domains are switched off on this store
                // (§10 of the brief), so it lists nothing — but the reference shows the tab
                // with an empty table, and a missing tab reads as a missing feature.
                'domains' => ['rows' => collect()],
                'users' => ['rows' => $this->contactRows()],
                'contacts' => ['rows' => $this->contactRows()],
                // The reference's Profile tab is the client's stored details; ours reads the
                // same properties the Summary panel does, in full.
                'profile' => ['rows' => []],
                'quotes' => ['rows' => $this->quoteRows()],
                'notes' => ['rows' => $this->noteRows()],
                default => $this->summaryData(),
            },
        ];
    }

    /**
     * Everything the Summary tab shows — the page as it was before there were tabs.
     *
     * @return array<string, mixed>
     */
    private function summaryData(): array
    {
        $user = $this->customer;

        return [
            'user' => $user,
            'credits' => $user->credits->mapWithKeys(
                fn ($credit) => [$credit->currency_code => (float) $credit->amount]
            )->all(),
            'lifetime' => $this->lifetimeSpend(),
            'outstanding' => $this->outstanding(),
            // The reference's fixed rows, present whether filled or not, in its order —
            // plus any other filled store property (CPF/CNPJ and friends) after them.
            'properties' => (function () use ($user) {
                $prop = fn (string $key): string => (string) ($user->properties->firstWhere('key', $key)?->value ?? '');
                $fixed = [
                    'Company Name' => $prop('company_name'),
                    'Address 1' => $prop('address'),
                    'Address 2' => $prop('address2'),
                    'City' => $prop('city'),
                    'State/Region' => $prop('state'),
                    'Postcode' => $prop('zip'),
                    'Country' => $prop('country'),
                    'Phone Number' => $prop('phone'),
                ];
                $extra = $user->properties
                    ->filter(fn ($property) => filled($property->value)
                        && $property->parent_property
                        && !in_array($property->key, ['company_name', 'address', 'address2', 'city', 'state', 'zip', 'country', 'phone'], true))
                    ->mapWithKeys(fn ($property) => [$property->parent_property->name => $property->value])
                    ->all();

                return [...$fixed, ...$extra];
            })(),
            'services' => $user->services()
                ->with('product')
                ->latest()
                ->limit(self::ROWS)
                ->get(),
            'serviceCount' => $user->services()->count(),
            'invoices' => $user->invoices()
                ->with(['items', 'transactions'])
                ->latest()
                ->limit(self::ROWS)
                ->get(),
            'invoiceCount' => $user->invoices()->count(),
            'tickets' => $user->tickets()
                ->latest()
                ->limit(self::ROWS)
                ->get(),
            'ticketCount' => $user->tickets()->count(),
            // ── The reference's Summary panels ───────────────────────────────────────────
            'invoiceStats' => $this->invoiceStats(),
            'categoryCounts' => $this->categoryCounts(),
            'lastSeen' => $user->sessions()->orderByDesc('last_activity')->first(),
            'recentEmails' => $this->emailRows()->take(5),
            'isActive' => $user->services()->whereIn('status', ['pending', 'active', 'suspended'])->exists(),
            'quoteRows' => $this->quoteRows(),
            'acceptedQuotes' => Schema::hasTable('ext_quotes')
                ? DB::table('ext_quotes')->where('user_id', $user->id)->where('status', 'accepted')->count()
                : 0,
            'affiliateSignups' => Schema::hasTable('ext_affiliates')
                ? (int) DB::table('ext_affiliates')->where('user_id', $user->id)->value('signups')
                : 0,
        ];
    }

    /**
     * The reference's Invoices/Billing panel: a count and a sum per state.
     *
     * @return array<string, array{count: int, total: float, code: string}>
     */
    private function invoiceStats(): array
    {
        $stats = [];

        // Draft and refunded are extension-written statuses; a store without them simply
        // counts zero, which is what the reference shows for an empty state too.
        foreach (['paid' => Invoice::STATUS_PAID, 'draft' => 'draft', 'unpaid' => Invoice::STATUS_PENDING, 'cancelled' => Invoice::STATUS_CANCELLED, 'refunded' => 'refunded'] as $label => $status) {
            $invoices = $this->customer->invoices()->where('status', $status)->with(['items', 'transactions'])->get();

            $stats[$label] = [
                'count' => $invoices->count(),
                'total' => $invoices->sum(fn (Invoice $invoice) => (float) $invoice->total),
                'code' => $invoices->first()?->currency_code ?? 'USD',
            ];
        }

        return $stats;
    }

    /**
     * The reference's Products/Services panel: "Group  n (m Total)" per product group.
     *
     * @return array<string, array{open: int, total: int}>
     */
    private function categoryCounts(): array
    {
        $services = $this->customer->services()->with('product')->get();

        $held = $services
            ->groupBy(fn ($service) => $service->product?->category?->name ?? 'Product/Service')
            ->map(fn ($group) => [
                'open' => $group->whereIn('status', ['pending', 'active', 'suspended'])->count(),
                'total' => $group->count(),
            ]);

        // Every product group, zeroes included — the reference lists "Shared Hosting
        // 0 (0 Total)" for groups the client has nothing in, and so do we.
        $all = [];

        foreach (\App\Models\Category::query()->whereNull('parent_id')->orderBy('sort')->orderBy('name')->pluck('name') as $name) {
            $all[$name] = $held[$name] ?? ['open' => 0, 'total' => 0];
        }

        foreach ($held as $name => $counts) {
            $all[$name] ??= $counts;
        }

        return $all;
    }

    /**
     * The reference's Current Quotes table — empty when the quoting extension is absent,
     * exactly as the reference shows an installation that has never raised one.
     *
     * @return Collection<int, object>
     */
    // ── Contacts ────────────────────────────────────────────────────────────────

    /** Whether the contacts feature is installed — ClientTools may be disabled. */
    private function hasContacts(): bool
    {
        return class_exists(Contact::class) && Schema::hasTable('ext_ct_contacts');
    }

    /** @return \Illuminate\Support\Collection<int, Contact> */
    private function contactRows()
    {
        if (!$this->hasContacts()) {
            return collect();
        }

        return Contact::where('user_id', $this->customer->id)->orderBy('first_name')->get();
    }

    /** The reference's Contacts select: choosing one loads it into the form. */
    public function updatedContact(): void
    {
        $this->resetValidation();
        $this->confirmingContactDelete = false;

        $row = $this->contact !== '' ? Contact::find((int) $this->contact) : null;

        if (!$row || $row->user_id !== $this->customer->id) {
            // "Add New", or a contact that has gone: an empty form either way.
            $this->contact = $row ? '' : $this->contact;
            $this->contactForm = array_fill_keys(array_keys($this->contactForm), '');
            $this->contactPrefs = [];

            return;
        }

        foreach (array_keys($this->contactForm) as $field) {
            $this->contactForm[$field] = (string) ($row->{$field} ?? '');
        }

        $this->contactPrefs = array_values((array) ($row->email_preferences ?? []));
    }

    public function saveContact(): void
    {
        abort_unless($this->hasContacts(), 404);

        $this->validate([
            'contactForm.first_name' => 'required|string|max:255',
            'contactForm.last_name' => 'required|string|max:255',
            'contactForm.email' => 'required|email|max:255',
            'contactForm.company_name' => 'nullable|string|max:255',
            'contactForm.address' => 'nullable|string|max:255',
            'contactForm.address2' => 'nullable|string|max:255',
            'contactForm.city' => 'nullable|string|max:255',
            'contactForm.state' => 'nullable|string|max:255',
            'contactForm.zip' => 'nullable|string|max:32',
            'contactForm.country' => 'nullable|string|max:2',
            'contactForm.phone' => 'nullable|string|max:64',
        ], attributes: [
            'contactForm.first_name' => 'first name', 'contactForm.last_name' => 'last name',
            'contactForm.email' => 'email address',
        ]);

        $prefs = array_values(array_intersect($this->contactPrefs, Contact::EMAIL_PREFERENCES));

        $existing = $this->contact !== '' ? Contact::find((int) $this->contact) : null;

        if ($existing && $existing->user_id !== $this->customer->id) {
            abort(403);
        }

        if ($existing) {
            $existing->update([...$this->contactForm, 'email_preferences' => $prefs]);
        } else {
            $existing = Contact::create([
                ...$this->contactForm,
                'user_id' => $this->customer->id,
                'email_preferences' => $prefs,
            ]);

            // Land on the contact just made, as the reference does, rather than back on a
            // blank Add New that would invite a duplicate.
            $this->contact = (string) $existing->id;
        }

        Notification::make()->title('Contact saved')->success()->send();
    }

    public function deleteContact(): void
    {
        $this->confirmingContactDelete = false;

        $row = $this->contact !== '' ? Contact::find((int) $this->contact) : null;

        if (!$row || $row->user_id !== $this->customer->id) {
            return;
        }

        $row->delete();

        $this->contact = '';
        $this->updatedContact();

        Notification::make()->title('Contact deleted')->success()->send();
    }

    // ── Users ───────────────────────────────────────────────────────────────────

    /**
     * The reference's Associate User: promote a contact on this account to a sub-account
     * with its own permissions.
     *
     * A person has to exist before they can be given access, which is why this picks from
     * the account's contacts rather than offering a free-text email — the reference invites
     * a stranger by email, and an invitation this platform has no way to send would be a
     * button that quietly did nothing.
     */
    public function associate(): void
    {
        abort_unless($this->hasContacts(), 404);

        $this->validate(
            ['associateContact' => 'required|integer'],
            attributes: ['associateContact' => 'contact'],
        );

        $row = Contact::find((int) $this->associateContact);

        if (!$row || $row->user_id !== $this->customer->id) {
            abort(403);
        }

        $row->update([
            'is_sub_account' => true,
            'permissions' => array_values(array_intersect($this->associatePermissions, Contact::PERMISSIONS)),
        ]);

        $this->reset(['associating', 'associateContact', 'associatePermissions']);

        Notification::make()->title('User associated')
            ->body($row->name . ' can now sign in to this account.')->success()->send();
    }

    /** The reference's Remove: the person stays a contact, the sign-in goes. */
    public function removeUser(int $id): void
    {
        $row = Contact::find($id);

        if (!$row || $row->user_id !== $this->customer->id) {
            return;
        }

        $row->update(['is_sub_account' => false, 'permissions' => []]);

        Notification::make()->title('Access removed')
            ->body($row->name . ' is still a contact on the account.')->success()->send();
    }

    // ── Notes ───────────────────────────────────────────────────────────────────

    /** @return \Illuminate\Support\Collection<int, ClientNote> */
    private function noteRows()
    {
        if (!Schema::hasTable('ext_ao_client_notes')) {
            return collect();
        }

        return ClientNote::where('user_id', $this->customer->id)
            ->with('admin:id,first_name,last_name,email')
            // Sticky first, then newest — the reference pins its Important notes.
            ->orderByDesc('sticky')->orderByDesc('id')
            ->limit(self::TAB_ROWS)
            ->get();
    }

    public function addNote(): void
    {
        abort_unless(Schema::hasTable('ext_ao_client_notes'), 404);

        $this->validate(['newNote' => 'required|string|max:65535'], attributes: ['newNote' => 'note']);

        ClientNote::create([
            'user_id' => $this->customer->id,
            'admin_id' => auth()->id(),
            'note' => $this->newNote,
            'sticky' => $this->newNoteSticky,
        ]);

        $this->reset(['newNote', 'newNoteSticky']);

        Notification::make()->title('Note added')->success()->send();
    }

    public function deleteNote(): void
    {
        $id = $this->confirmingNote;
        $this->reset('confirmingNote');

        $note = ClientNote::find($id);

        if ($note && $note->user_id === $this->customer->id) {
            $note->delete();

            Notification::make()->title('Note deleted')->success()->send();
        }
    }

    private function quoteRows()
    {
        if (!Schema::hasTable('ext_quotes')) {
            return collect();
        }

        return $this->paged(
            DB::table('ext_quotes')
                ->where('user_id', $this->customer->id)
                ->whereNotIn('status', ['draft'])
                ->orderByDesc('id')
        );
    }

    /**
     * Links out to the core screens that own each record.
     *
     * Built once for every tab rather than per tab, because the tab bar is on every one of
     * them and half of these are what the rows link to.
     *
     * @return array<string, mixed>
     */
    private function urls(): array
    {
        $user = $this->customer;

        return [
            // Core's per-client sub-pages now redirect back to these very tabs, so a
            // "See all" pointing at them would bounce the reader in a circle. These go to
            // the full lists instead, which is what "see all" should mean.
            'services' => ProductsServices::getUrl(),
            'invoices' => ManageInvoices::getUrl(),
            'tickets' => SupportTickets::getUrl(),
            'credits' => static::getUrl(['record' => $user->id, 'tab' => 'summary']),
            // This page's own editor — every service on these tabs is the customer's.
            'service' => fn ($id) => static::getUrl(['record' => $user->id, 'tab' => 'services', 'service' => $id]),
            'invoice' => fn ($id) => EditInvoice::getUrl(['record' => $id]),
            'ticket' => fn ($id) => EditTicket::getUrl(['record' => $id]),
            'quote' => fn ($id) => class_exists(CreateQuote::class)
                ? CreateQuote::getUrl(['record' => $id])
                : static::getUrl(['record' => $user->id, 'tab' => 'quotes']),
            // The reference's per-tab buttons. Each opens the screen that genuinely does
            // the thing, with this client already chosen where the screen accepts one.
            'edit' => UserResource::getUrl('edit', ['record' => $user]),
            'newInvoice' => ManageInvoices::getUrl(),
            'newQuote' => class_exists(CreateQuote::class) ? CreateQuote::getUrl() : ManageInvoices::getUrl(),
            'newTransaction' => AddTransaction::getUrl(),
            'newTicket' => OpenNewTicket::getUrl(),
            'billable' => BillableItemsList::getUrl(),
        ];
    }

    /**
     * Everything this customer has actually paid, by currency.
     *
     * Credit transactions are left out: settling an invoice from account credit spends
     * money that was already counted when the credit was bought, so including both would
     * report the customer as having paid twice.
     *
     * @return array<string, float>
     */
    private function lifetimeSpend(): array
    {
        return $this->customer->transactions()
            ->where('invoice_transactions.status', InvoiceTransactionStatus::Succeeded)
            ->where('invoice_transactions.is_credit_transaction', false)
            ->join('invoices as currency_source', 'currency_source.id', '=', 'invoice_transactions.invoice_id')
            ->groupBy('currency_source.currency_code')
            ->selectRaw('currency_source.currency_code as code, SUM(invoice_transactions.amount) as amount_sum')
            ->pluck('amount_sum', 'code')
            ->map(fn ($amount) => (float) $amount)
            ->all();
    }

    /**
     * What this customer still owes, by currency.
     *
     * Summed from the loaded invoices rather than in SQL, because an invoice total lives in
     * its items and `Invoice::$remaining` already nets off part payments.
     *
     * @return array<string, float>
     */
    private function outstanding(): array
    {
        $totals = [];

        $this->customer->invoices()
            ->where('status', Invoice::STATUS_PENDING)
            ->with(['items', 'transactions'])
            ->get()
            ->each(function (Invoice $invoice) use (&$totals) {
                $totals[$invoice->currency_code] = ($totals[$invoice->currency_code] ?? 0) + (float) $invoice->remaining;
            });

        return $totals;
    }

    // ── The tabs that read tables this page does not own ─────────────────────────────────
    // Each is guarded by a table check rather than a class check: an extension can be
    // present in the filesystem and not installed, and a tab that fatals is worse than a
    // tab that is empty.

    /**
     * @return Collection<int, object>
     */
    private function billableItems()
    {
        if (!Schema::hasTable('ext_billable_items')) {
            return collect();
        }

        return $this->paged(
            DB::table('ext_billable_items')
                ->where('user_id', $this->customer->id)
                ->orderByDesc('id')
        );
    }

    /**
     * Payments and refunds for this customer, newest first.
     *
     * The same interleaving as the Transactions report, and for the same reason: a payment
     * and the refund that partly undid it belong next to each other.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function transactionRows()
    {
        $rows = InvoiceTransaction::query()
            ->whereIn('invoice_id', $this->customer->invoices()->select('id'))
            ->with('gateway')
            ->latest('id')
            ->limit(self::TAB_ROWS)
            ->get()
            ->map(fn ($transaction): array => [
                'at' => $transaction->created_at,
                'method' => $transaction->is_credit_transaction
                    ? 'Account credit'
                    : ($transaction->gateway?->name ?? 'Unknown'),
                'description' => 'Invoice #' . $transaction->invoice_id
                    . ($transaction->transaction_id ? ' · ' . $transaction->transaction_id : ''),
                'in' => (float) $transaction->amount,
                'fee' => (float) $transaction->fee,
                'out' => 0.0,
            ]);

        if (Schema::hasTable('ext_invoice_refunds')) {
            $rows = $rows->concat(
                DB::table('ext_invoice_refunds')
                    ->whereIn('invoice_id', $this->customer->invoices()->select('id'))
                    ->orderByDesc('id')
                    ->limit(self::TAB_ROWS)
                    ->get()
                    ->map(fn ($refund): array => [
                        'at' => Carbon::parse($refund->created_at),
                        'method' => 'Refund',
                        'description' => 'Invoice #' . $refund->invoice_id
                            . ($refund->reason ? ' · ' . str($refund->reason)->limit(50) : ''),
                        'in' => 0.0,
                        'fee' => 0.0,
                        'out' => (float) $refund->amount,
                    ])
            );
        }

        // Add Transaction's client-attached rows — credit top-ups and unapplied payments.
        // These have no invoice, so the InvoiceTransaction query above never sees them;
        // without this branch a recorded top-up moved real money invisibly (user report,
        // 2026-09-04).
        if (Schema::hasTable('ext_unapplied_transactions')) {
            $rows = $rows->concat(
                DB::table('ext_unapplied_transactions')
                    // Gateways live in core's `extensions` table (Gateway extends Extension).
                    ->leftJoin('extensions', 'extensions.id', '=', 'ext_unapplied_transactions.gateway_id')
                    ->where('ext_unapplied_transactions.user_id', $this->customer->id)
                    ->orderByDesc('ext_unapplied_transactions.id')
                    ->limit(self::TAB_ROWS)
                    ->select('ext_unapplied_transactions.*', 'extensions.name as gateway_name')
                    ->get()
                    ->map(fn ($row): array => [
                        'at' => Carbon::parse($row->created_at),
                        'method' => $row->gateway_name ?? 'Manual',
                        'description' => ($row->description ?: 'Unapplied Payment')
                            . ($row->transaction_id ? ' · ' . $row->transaction_id : ''),
                        'in' => (float) $row->amount,
                        'out' => 0.0,
                    ])
            );
        }

        return $this->pagedCollection($rows->sortByDesc('at')->values());
    }

    /**
     * What has been sent to this customer — the reference's Emails tab.
     *
     * Read from core's `notifications`, which is where every message it sends is logged.
     *
     * @return Collection<int, object>
     */
    private function emailRows()
    {
        if (!Schema::hasTable('notifications')) {
            return collect();
        }

        return $this->paged(
            DB::table('notifications')
                ->where('user_id', $this->customer->id)
                ->orderByDesc('id')
        );
    }

    /**
     * The reference's Log tab: what has been done to this account, and by whom.
     *
     * Core already ships `owen-it/laravel-auditing` and audits the models that matter, so
     * this is a view of something already being recorded rather than new bookkeeping.
     *
     * @return Collection<int, object>
     */
    private function logRows()
    {
        if (!Schema::hasTable('audits')) {
            return collect();
        }

        $query = DB::table('audits')
            ->where(function ($query): void {
                $query->where(function ($subject): void {
                    $subject->where('auditable_type', User::class)
                        ->where('auditable_id', $this->customer->id);
                })->orWhere('user_id', $this->customer->id);
            });

        // The reference's Filter Log band. Each is applied only when filled, so an empty
        // band is the unfiltered list rather than one that matches nothing.
        if ($this->logFilter['date'] !== '') {
            $query->whereDate('created_at', $this->logFilter['date']);
        }

        if ($this->logFilter['description'] !== '') {
            $term = '%' . $this->logFilter['description'] . '%';

            $query->where(fn ($q) => $q->where('event', 'like', $term)
                ->orWhere('auditable_type', 'like', $term)
                ->orWhere('new_values', 'like', $term));
        }

        if ($this->logFilter['user'] !== '') {
            $query->where('user_id', (int) $this->logFilter['user']);
        }

        if ($this->logFilter['ip'] !== '') {
            $query->where('ip_address', 'like', '%' . $this->logFilter['ip'] . '%');
        }

        return $this->paged($query->orderByDesc('id'));
    }

    public function formatMoney(float $amount, ?string $currency): string
    {
        return Money::format($amount, $currency);
    }

    public function formatTotals(array $totals): string
    {
        return Money::formatTotals($totals);
    }
}
