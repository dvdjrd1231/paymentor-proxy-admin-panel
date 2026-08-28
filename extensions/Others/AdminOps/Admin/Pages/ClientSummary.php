<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\InvoiceResource;
use App\Admin\Resources\ServiceResource;
use App\Admin\Resources\TicketResource;
use App\Admin\Resources\UserResource;
use App\Enums\InvoiceTransactionStatus;
use App\Models\Invoice;
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
use Paymenter\Extensions\Others\AdminOps\Support\Money;

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

    /** The property keys the Profile form edits besides the user's own columns. */
    private const PF_PROPS = ['company_name', 'address', 'address2', 'city', 'state', 'zip', 'country', 'phone', 'currency'];

    private const PREF_KEYS = ['general', 'invoice', 'support', 'product', 'domain', 'affiliate'];

    private const SETTING_KEYS = ['late_fees', 'overdue_notices', 'tax_exempt', 'separate_invoices', 'disable_cc', 'marketing_optin', 'status_update', 'single_sign_on'];

    /**
     * The reference's tabs, less the ones Paymenter has nothing behind.
     *
     * Dropped deliberately rather than shown empty: **Domains** (removed from this store
     * entirely, §10 of the brief), **Users** and **Contacts** (WHMCS's sub-account model,
     * which Paymenter does not have), **Quotes** (nothing to list yet — the tab appears with
     * the feature). A tab that always says "none" teaches people to stop opening tabs.
     *
     * @var array<string, string>
     */
    private const TABS = [
        'summary' => 'Summary',
        'profile' => 'Profile',
        'services' => 'Products/Services',
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
     * The tab labels, with the reference's live "Notes (n)" count.
     *
     * @return array<string, string>
     */
    private function tabLabels(): array
    {
        $labels = self::TABS;
        $labels['notes'] = 'Notes (' . (trim($this->adminNotes) !== '' ? 1 : 0) . ')';

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
            'tab' => array_key_exists($this->tab, self::TABS) ? $this->tab : 'summary',
            'urls' => $this->urls(),
            'clientsList' => User::query()
                ->whereNull('role_id')
                ->orderBy('first_name')
                ->limit(200)
                // The reference names each option "name (company) - #id"; one eager load
                // beats a company query per option.
                ->with(['properties' => fn ($q) => $q->where('key', 'company_name')])
                ->get(['id', 'first_name', 'last_name', 'email']),
            ...match (array_key_exists($this->tab, self::TABS) ? $this->tab : 'summary') {
                'services' => ['rows' => $this->customer->services()->with('product')->latest()->limit(self::TAB_ROWS)->get()],
                'billable' => ['rows' => $this->billableItems()],
                'invoices' => ['rows' => $this->customer->invoices()->with(['items', 'transactions'])->latest()->limit(self::TAB_ROWS)->get()],
                'transactions' => ['rows' => $this->transactionRows()],
                'tickets' => ['rows' => $this->customer->tickets()->latest()->limit(self::TAB_ROWS)->get()],
                'emails' => ['rows' => $this->emailRows()],
                'log' => ['rows' => $this->logRows()],
                // The reference's Profile tab is the client's stored details; ours reads the
                // same properties the Summary panel does, in full.
                'profile' => ['rows' => []],
                'quotes' => ['rows' => $this->quoteRows()],
                'notes' => ['rows' => []],
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
    private function quoteRows()
    {
        if (!Schema::hasTable('ext_quotes')) {
            return collect();
        }

        return DB::table('ext_quotes')
            ->where('user_id', $this->customer->id)
            ->whereNotIn('status', ['draft'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();
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
            'services' => UserResource::getUrl('services', ['record' => $user]),
            'invoices' => UserResource::getUrl('invoices', ['record' => $user]),
            'tickets' => UserResource::getUrl('tickets', ['record' => $user]),
            'credits' => UserResource::getUrl('credits', ['record' => $user]),
            'service' => fn ($id) => ServiceResource::getUrl('edit', ['record' => $id]),
            'invoice' => fn ($id) => InvoiceResource::getUrl('edit', ['record' => $id]),
            'ticket' => fn ($id) => TicketResource::getUrl('edit', ['record' => $id]),
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

        return DB::table('ext_billable_items')
            ->where('user_id', $this->customer->id)
            ->orderByDesc('id')
            ->limit(self::TAB_ROWS)
            ->get();
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
                        'out' => (float) $refund->amount,
                    ])
            );
        }

        return $rows->sortByDesc('at')->values()->take(self::TAB_ROWS);
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

        return DB::table('notifications')
            ->where('user_id', $this->customer->id)
            ->orderByDesc('id')
            ->limit(self::TAB_ROWS)
            ->get();
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

        return DB::table('audits')
            ->where(function ($query): void {
                $query->where(function ($subject): void {
                    $subject->where('auditable_type', User::class)
                        ->where('auditable_id', $this->customer->id);
                })->orWhere('user_id', $this->customer->id);
            })
            ->orderByDesc('id')
            ->limit(self::TAB_ROWS)
            ->get();
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
