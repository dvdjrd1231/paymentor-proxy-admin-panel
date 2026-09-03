<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Invoices, to its screenshot: the Paid / Unpaid / Overdue totals bar, the navy
 * grid — Invoice #, Client Name, Invoice Date, Due Date, Last Capture Attempt, Total,
 * Payment Method, Status — and one page for every sidebar filter, told apart by `?status=`.
 *
 * The filters map to what Paymenter records: Unpaid is core's `pending`, Overdue is pending
 * past its due date, Payment Pending is an invoice with a transaction still processing.
 * Draft and Refunded exist where the InvoiceOps extension writes those statuses;
 * Collections has no process behind it, so that filter honestly lists nothing.
 */
class ManageInvoices extends Page
{
    protected string $view = 'adminops::pages.manage-invoices';

    protected static ?string $slug = 'manage-invoices';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 100;

    /** '' | paid | draft | unpaid | overdue | cancelled | refunded | collections | payment_pending */
    #[Url]
    public string $status = '';

    #[Url]
    public string $q = '';

    // The reference's Search/Filter panel, field for field. Every one is a URL.

    #[Url]
    public string $client = '';

    #[Url]
    public string $inum = '';

    #[Url]
    public string $item = '';

    /** Gateway name — derived off the invoice's transactions, filtered in PHP. */
    #[Url]
    public string $method = '';

    /** Total Due From/To — the invoice total is an accessor, filtered in PHP. */
    #[Url]
    public string $totalFrom = '';

    #[Url]
    public string $totalTo = '';

    /** Single dates, 'MM/DD/YYYY', each from the shared calendar. */
    #[Url]
    public string $dInvoice = '';

    #[Url]
    public string $dDue = '';

    #[Url]
    public string $dPaid = '';

    #[Url]
    public string $dRefunded = '';

    #[Url]
    public string $dCancelled = '';

    #[Url]
    public int $page = 1;

    #[Url]
    public bool $filter = false;

    public static function canAccess(): bool
    {
        return InvoiceResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Invoices';
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;
    }

    public function search(): void
    {
        $this->page = 1;
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    /** The row awaiting the "Are you sure?" before deletion. */
    public ?int $confirmingDelete = null;

    /** @var array<int, string> The ticked rows, for the reference's With Selected bar. */
    public array $selected = [];

    /**
     * The reference's six bulk buttons. Status moves go through the model so core's
     * observers still fire; money that has moved is never quietly rewritten — an invoice
     * with transactions cannot be marked unpaid or deleted, and says so.
     *
     * @return array<int, \App\Models\Invoice>
     */
    private function picked(): array
    {
        return Invoice::with(['items', 'transactions', 'user'])
            ->whereIn('id', array_map('intval', array_filter($this->selected)))
            ->get()->all();
    }

    public function markSelected(string $status): void
    {
        $done = 0;
        $kept = 0;

        foreach ($this->picked() as $invoice) {
            // Unpaid and cancelled would contradict a recorded payment.
            if ($status !== 'paid' && $invoice->transactions->isNotEmpty()) {
                $kept++;

                continue;
            }

            $invoice->update(['status' => $status]);
            $done++;
        }

        $this->selected = [];
        Notification::make()
            ->title($done . ' invoice(s) marked ' . $status . ($kept ? ', ' . $kept . ' kept' : ''))
            ->body($kept ? 'Invoices with recorded transactions keep their status.' : null)
            ->{$done ? 'success' : 'warning'}()->send();
    }

    /** The reference's Duplicate Invoice: same lines, same client, a fresh pending copy. */
    public function duplicateSelected(): void
    {
        $made = 0;

        DB::transaction(function () use (&$made): void {
            foreach ($this->picked() as $invoice) {
                $copy = Invoice::create([
                    'user_id' => $invoice->user_id,
                    'status' => Invoice::STATUS_PENDING,
                    'currency_code' => $invoice->currency_code,
                    'due_at' => now()->addDays((int) config('settings.cronjob_invoice', 7)),
                ]);

                foreach ($invoice->items as $item) {
                    $copy->items()->create($item->only(['price', 'quantity', 'description', 'reference_id', 'reference_type']));
                }

                $made++;
            }
        });

        $this->selected = [];
        Notification::make()->title($made . ' invoice(s) duplicated')->success()->send();
    }

    /**
     * The reference's Send Reminder. Core ships no overdue template, so the reminder is
     * the invoice notice itself — same mail, same PDF attached, which is what a customer
     * being reminded actually needs to see.
     */
    public function remindSelected(): void
    {
        $sent = 0;

        foreach ($this->picked() as $invoice) {
            if ($invoice->status !== 'pending' || !$invoice->user) {
                continue;
            }

            try {
                \App\Helpers\NotificationHelper::invoiceNotification($invoice->user, $invoice, 'new_invoice_created');
                $sent++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ManageInvoices: reminder failed', ['invoice' => $invoice->id, 'error' => $e->getMessage()]);
            }
        }

        $this->selected = [];
        Notification::make()->title($sent . ' reminder(s) sent')
            ->{$sent ? 'success' : 'warning'}()->send();
    }

    public function deleteSelected(): void
    {
        $deleted = 0;
        $kept = 0;

        DB::transaction(function () use (&$deleted, &$kept): void {
            foreach ($this->picked() as $invoice) {
                if ($invoice->status === 'paid' || $invoice->transactions->isNotEmpty()) {
                    $kept++;

                    continue;
                }

                $invoice->items()->delete();
                $invoice->delete();
                $deleted++;
            }
        });

        $this->selected = [];
        Notification::make()
            ->title($deleted . ' invoice(s) deleted' . ($kept ? ', ' . $kept . ' kept' : ''))
            ->body($kept ? 'Paid invoices and invoices with transactions keep their paperwork.' : null)
            ->{$deleted ? 'success' : 'warning'}()->send();
    }

    public function askDelete(int $id): void
    {
        $this->confirmingDelete = $id;
    }

    public function deleteInvoice(): void
    {
        if (!$this->confirmingDelete) {
            return;
        }

        $invoice = Invoice::with('transactions')->findOrFail($this->confirmingDelete);
        $this->confirmingDelete = null;

        // Money that moved must keep its paperwork: a paid invoice, or one a gateway has
        // already touched, stays. The reference refuses the same rows.
        if ($invoice->status === 'paid' || $invoice->transactions->isNotEmpty()) {
            \Filament\Notifications\Notification::make()->title('Cannot delete')
                ->body('This invoice is paid or has transactions — cancel it instead.')
                ->danger()->send();

            return;
        }

        DB::transaction(function () use ($invoice): void {
            $invoice->items()->delete();
            $invoice->delete();
        });

        \Filament\Notifications\Notification::make()->title('Invoice deleted')->success()->send();
    }

    /** The reference's status word and colour class for a row. */
    public static function statusOf(Invoice $invoice): array
    {
        if ($invoice->status === 'pending' && $invoice->due_at?->isPast()) {
            return ['Overdue', 'ao-inv-overdue'];
        }

        return match ($invoice->status) {
            'paid' => ['Paid', 'ao-inv-paid'],
            'pending' => ['Unpaid', 'ao-inv-unpaid'],
            'cancelled' => ['Cancelled', 'ao-inv-cancelled'],
            'draft' => ['Draft', 'ao-inv-draft'],
            'refunded' => ['Refunded', 'ao-inv-refunded'],
            default => [ucfirst((string) $invoice->status), ''],
        };
    }

    protected function getViewData(): array
    {
        $invoices = $this->paginated();

        if ($this->page > 1 && $invoices->isEmpty()) {
            $this->page = max(1, $invoices->lastPage());
            $invoices = $this->paginated();
        }

        return [
            'invoices' => $invoices,
            'bar' => $this->totalsBar(),
            'gateways' => \App\Models\Gateway::orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Payment Method and Total Due are derived — the gateway off the transactions, the
     * total an accessor over items — so when either is set the SQL-narrowed set is
     * filtered here and paged by hand, the same pattern Manage Orders uses.
     */
    private function paginated()
    {
        $derived = $this->method !== ''
            || (trim($this->totalFrom) !== '' && is_numeric(trim($this->totalFrom)))
            || (trim($this->totalTo) !== '' && is_numeric(trim($this->totalTo)));

        if (!$derived) {
            return $this->query()->paginate(self::PER_PAGE, page: $this->page);
        }

        $all = $this->query()->get()
            ->filter(function (Invoice $invoice): bool {
                if ($this->method !== ''
                    && $invoice->transactions->first()?->gateway?->name !== $this->method) {
                    return false;
                }

                $total = (float) $invoice->total;

                if (trim($this->totalFrom) !== '' && is_numeric(trim($this->totalFrom)) && $total < (float) trim($this->totalFrom)) {
                    return false;
                }

                if (trim($this->totalTo) !== '' && is_numeric(trim($this->totalTo)) && $total > (float) trim($this->totalTo)) {
                    return false;
                }

                return true;
            })
            ->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $all->forPage($this->page, self::PER_PAGE)->values(),
            $all->count(),
            self::PER_PAGE,
            $this->page,
        );
    }

    /**
     * The reference's headline bar, per currency: Paid / Unpaid / Overdue sums. One grouped
     * query over invoice items, not a walk over every invoice.
     */
    private function totalsBar(): array
    {
        // DB::table, not Invoice::query(): hydrating these rows into Invoice models let
        // the model's `total` accessor shadow the SQL alias — it summed the (unloaded)
        // items relation and every bucket read as zero.
        $rows = DB::table('invoices')
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereIn('invoices.status', ['paid', 'pending'])
            ->groupBy('invoices.currency_code', 'invoices.status', DB::raw('invoices.due_at < NOW()'))
            ->select(
                'invoices.currency_code',
                'invoices.status',
                DB::raw('invoices.due_at < NOW() as past_due'),
                DB::raw('SUM(invoice_items.price * invoice_items.quantity) as bucket_total'),
            )
            ->get();

        $bar = [];
        foreach ($rows as $row) {
            $bucket = $row->status === 'paid' ? 'paid' : ($row->past_due ? 'overdue' : 'unpaid');
            $bar[$row->currency_code][$bucket] = ($bar[$row->currency_code][$bucket] ?? 0) + (float) $row->bucket_total;
        }

        return $bar;
    }

    private function query()
    {
        $query = Invoice::query()
            ->with(['user', 'items', 'transactions.gateway', 'currency'])
            ->orderByDesc('id');

        match ($this->status) {
            'paid' => $query->where('status', 'paid'),
            'draft' => $query->where('status', 'draft'),
            'unpaid' => $query->where('status', 'pending'),
            'overdue' => $query->where('status', 'pending')->where('due_at', '<', now()),
            'cancelled' => $query->where('status', 'cancelled'),
            'refunded' => $query->where('status', 'refunded'),
            // No collections process exists; an empty list is the truth.
            'collections' => $query->whereRaw('1 = 0'),
            'payment_pending' => $query->whereHas('transactions', fn ($q) => $q->where('status', 'pending')),
            default => null,
        };

        if ($this->q !== '') {
            $query->where(function ($outer): void {
                $outer->whereHas('user', function ($q): void {
                    $q->where('first_name', 'like', '%' . $this->q . '%')
                        ->orWhere('last_name', 'like', '%' . $this->q . '%')
                        ->orWhere('email', 'like', '%' . $this->q . '%');
                });

                if (ctype_digit($this->q)) {
                    $outer->orWhere('invoices.id', (int) $this->q);
                }
            });
        }

        if (trim($this->client) !== '') {
            $needle = trim($this->client);
            $query->whereHas('user', fn ($q) => $q->where('first_name', 'like', "%{$needle}%")
                ->orWhere('last_name', 'like', "%{$needle}%")
                ->orWhere('email', 'like', "%{$needle}%"));
        }

        if (trim($this->inum) !== '') {
            $needle = trim($this->inum);
            $query->where(fn ($w) => $w->where('number', 'like', "%{$needle}%")
                ->when(ctype_digit($needle), fn ($q) => $q->orWhere('invoices.id', (int) $needle)));
        }

        if (trim($this->item) !== '') {
            $query->whereHas('items', fn ($q) => $q->where('description', 'like', '%' . trim($this->item) . '%'));
        }

        if ($day = $this->day($this->dInvoice)) {
            $query->whereDate('created_at', $day);
        }

        if ($day = $this->day($this->dDue)) {
            $query->whereDate('due_at', $day);
        }

        // Date Paid: the day a settling transaction landed — the honest record of payment.
        if ($day = $this->day($this->dPaid)) {
            $query->whereHas('transactions', fn ($q) => $q->whereDate('created_at', $day));
        }

        // Date Refunded, from the refund ledger when the InvoiceOps extension carries one.
        if (($day = $this->day($this->dRefunded)) !== null) {
            $refunded = class_exists(\Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund::class)
                ? \Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund::whereDate('created_at', $day)->pluck('invoice_id')
                : collect();
            $query->whereIn('invoices.id', $refunded);
        }

        // Date Cancelled, from the audit trail: the update that wrote status = cancelled.
        if (($day = $this->day($this->dCancelled)) !== null) {
            $query->whereIn('invoices.id', \Illuminate\Support\Facades\DB::table('audits')
                ->where('auditable_type', Invoice::class)
                ->where('new_values', 'like', '%"status":"cancelled"%')
                ->whereDate('created_at', $day)
                ->pluck('auditable_id'));
        }

        return $query;
    }

    /** 'MM/DD/YYYY' (or blank) from the shared calendar, as a query-ready date. */
    private function day(string $text): ?string
    {
        foreach (['m/d/Y', 'Y-m-d'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, trim($text))->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        return null;
    }
}
