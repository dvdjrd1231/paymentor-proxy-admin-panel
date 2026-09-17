<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\InvoiceResource;
use App\Enums\InvoiceTransactionStatus;
use App\Helpers\ExtensionHelper;
use App\Helpers\NotificationHelper;
use App\Models\Gateway;
use App\Models\Invoice;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Paymenter\Extensions\Others\AdminOps\Models\Refund;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's invoice screen, to Leandro's screenshots of `invoices.php` (2026-09-07):
 * the tab strip — Summary, Add Payment, Options, Credit, Notes — over the shared Invoice
 * Items ladder, then Transactions and Transaction History.
 */
class EditInvoice extends Page
{
    protected string $view = 'adminops::pages.edit-invoice';

    protected static ?string $slug = 'edit-invoice';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Same reasoning as {@see ClientSummary::$customer} — not `$record`. */
    public Invoice $invoice;

    /** Which tab is showing: summary | payment | options | credit | notes. */
    public string $tab = 'summary';

    /** The editable line items: [{id, description, price, quantity}]. id is null for a new row. */
    public array $items = [];

    /** Line indexes ticked for the reference's "- With Selected -" menu. */
    public array $selected = [];

    /** The Options tab. */
    /** The Options tab. taxRate is the invoice snapshot's own rate. */
    public array $options = ['invoiceDate' => '', 'dueAt' => '', 'number' => '', 'status' => '', 'taxRate' => '', 'paymentMethod' => ''];

    /** Where the Options tab's Payment Method is kept — a property on the invoice row. */
    public const METHOD_KEY = 'adminops_payment_method';

    /** The Add Payment tab. */
    public array $pay = ['date' => '', 'amount' => '', 'fee' => '', 'transactionId' => '', 'gateway' => '', 'sendEmail' => true];

    /** The Credit tab's two boxes. */
    public array $credit = ['add' => '', 'remove' => ''];

    /**
     * The Refund tab — the reference's form, field for field: which transaction, how much, whether to
     * undo what the payment set in motion, and whether to tell the client.
     */
    public array $refund = ['transaction' => '', 'type' => 'credit', 'amount' => '', 'reason' => '', 'reverse' => false, 'sendEmail' => false];

    /**
     * The reference's Refund Type, its three kinds.
     *
     * Credit is the default rather than the gateway, which is what the reference defaults
     * to: no gateway here implements a refund hook, so defaulting to it would put the one
     * choice that cannot work in front of every refund. It is still offered — and checked
     * for real against the gateway at the moment of use, rather than assumed either way.
     */
    public const REFUND_TYPES = [
        'gateway' => 'Refund through Gateway (If supported by module)',
        'external' => 'Manual Refund Processed Externally',
        'credit' => "Add to Client's Credit Balance",
    ];

    /** The Notes tab, stored as a property on the invoice. */
    public string $note = '';

    /** Which notification the Summary tab's Send Email button would send. */
    public string $emailTemplate = 'new_invoice_created';

    /**
     * The emails this screen can actually send, in the reference's own order.
     *
     * Every key here is a real notification template — the picker names templates rather
     * than being a list of words, so one without a template would fail on send. The six
     * after the first three were written for this
     * ({@see database/migrations/2026_09_16_000000_add_invoice_notification_templates.php}).
     *
     * The reference's card and direct-debit entries are absent on purpose: there is no card
     * vault and no direct debit here, so they would offer to tell a client something that
     * cannot happen on their account.
     */
    public const EMAILS = [
        'new_invoice_created' => 'Invoice Created',
        'invoice_payment_reminder' => 'Invoice Payment Reminder',
        'invoice_overdue_first' => 'First Invoice Overdue Notice',
        'invoice_overdue_second' => 'Second Invoice Overdue Notice',
        'invoice_overdue_third' => 'Third Invoice Overdue Notice',
        'invoice_paid' => 'Invoice Payment Confirmation',
        'invoice_refund_confirmation' => 'Invoice Refund Confirmation',
        'invoice_payment_failed' => 'Invoice Payment Failed',
        'invoice_modified' => 'Invoice Modified',
    ];

    /** Where the Notes tab's text lives — a property on the invoice row. */
    private const NOTE_KEY = 'adminops_note';

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record}';
    }

    public static function canAccess(): bool
    {
        return InvoiceResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'Invoice #' . ($this->invoice->number ?: $this->invoice->id);
    }

    public function mount(int|string $record): void
    {
        abort_unless(static::canAccess(), 403);

        $this->invoice = Invoice::with(['items', 'transactions.gateway', 'user'])->findOrFail($record);
        $this->loadForm();
    }

    private function loadForm(): void
    {
        // `price` here is the LINE TOTAL, not the unit price: the reference's ladder has a
        // single Amount column and no Quantity, so a line of 3 × 70.00 must read 210.00 or
        // the invoice would appear to be for a third of its value (Leandro, 2026-09-16).
        // The unit price and quantity are kept alongside so an untouched line saves back
        // exactly as it was. {@see save}
        $this->items = $this->invoice->items->map(fn ($item): array => [
            'id' => $item->id,
            'description' => (string) $item->description,
            'price' => number_format((float) $item->price * max(1, (int) $item->quantity), 2, '.', ''),
            'quantity' => (int) $item->quantity,
            'unit' => number_format((float) $item->price, 2, '.', ''),
        ])->values()->all();

        // An invoice with no lines opens on an empty one ready to type, as the reference
        // does — "No Records Found" on an invoice you have just raised to fill in reads as
        // a fault rather than an invitation (Leandro, 2026-09-16). Save skips a row left
        // blank, so nothing is stored by merely opening the screen.
        if ($this->items === []) {
            $this->items[] = ['id' => null, 'description' => '', 'price' => '0.00', 'quantity' => 1];
        }

        $this->selected = [];

        $this->options = [
            'invoiceDate' => $this->invoice->created_at?->format('m/d/Y') ?? '',
            'dueAt' => $this->invoice->due_at?->format('m/d/Y') ?? '',
            'number' => (string) ($this->invoice->number ?? ''),
            'status' => $this->invoice->status,
            // The reference's Tax Rate, which here is the invoice's own snapshot rate.
            'taxRate' => number_format((float) ($this->invoice->snapshot?->tax_rate ?? 0), 2, '.', ''),
            'paymentMethod' => (string) ($this->invoice->properties()
                ->where('key', self::METHOD_KEY)->value('value') ?? ''),
        ];

        $this->pay['date'] = now()->format('m/d/Y');
        $this->pay['amount'] = number_format(max(0, (float) $this->invoice->remaining), 2, '.', '');

        $this->credit = ['add' => '', 'remove' => ''];

        $this->note = (string) ($this->invoice->properties()
            ->where('key', self::NOTE_KEY)->value('value') ?? '');
    }

    private function refreshInvoice(): void
    {
        $this->invoice->refresh()->load(['items', 'transactions.gateway', 'user']);
        $this->loadForm();
    }

    /**
     * The totals and transactions, without rebuilding the item rows.
     *
     * {@see refreshInvoice} reloads the form from the database, which throws away every
     * line added with Add Item and not yet saved — those exist only in this component's
     * state until Save Changes. Removing one line therefore took all the unsaved ones with
     * it (Leandro, 2026-09-16: "after add 6 items and click remove button of any row, then
     * it removes all rows"). Anything that edits the rows in memory refreshes this way.
     */
    private function refreshTotals(): void
    {
        $this->invoice->refresh()->load(['items', 'transactions.gateway', 'user']);
    }

    /**
     * The subtotal of what is on screen, not of what is saved.
     *
     * The ladder totalled `$invoice->total`, so a line typed but not yet saved counted for
     * nothing and the invoice read $0.00 with three amounts sitting in front of you
     * (Leandro, 2026-09-16). Quantity times amount, the same arithmetic the save performs.
     */
    public function liveSubtotal(): float
    {
        // Straight sum: each row's Amount is already the line total. {@see loadForm}
        return round(array_sum(array_map(
            fn (array $row): float => (float) ($row['price'] ?? 0),
            $this->items,
        )), 2);
    }

    // ── Invoice items ────────────────────────────────────────────────────────────────

    /** The reference's empty last row: filling it in and saving adds a line. */
    public function addItem(): void
    {
        $this->items[] = ['id' => null, 'description' => '', 'price' => '0.00', 'quantity' => 1];
    }

    public function removeItem(int $index): void
    {
        if (!isset($this->items[$index])) {
            return;
        }

        if ($this->items[$index]['id']) {
            $this->invoice->items()->where('id', $this->items[$index]['id'])->delete();
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->selected = [];

        if ($this->items === []) {
            $this->items[] = ['id' => null, 'description' => '', 'price' => '0.00', 'quantity' => 1];
        }

        $this->refreshTotals();

        Notification::make()->title('Line removed')->success()->send();
    }

    /** The reference's "- With Selected -" menu. Delete is the only entry it can honour. */
    public function withSelected(string $action): void
    {
        if ($action === 'split') {
            $this->splitToNewInvoice();

            return;
        }

        if ($action !== 'delete' || $this->selected === []) {
            return;
        }

        $ids = [];
        foreach ($this->selected as $index) {
            if (isset($this->items[(int) $index]) && $this->items[(int) $index]['id']) {
                $ids[] = $this->items[(int) $index]['id'];
            }
        }

        if ($ids !== []) {
            $this->invoice->items()->whereIn('id', $ids)->delete();
        }

        // Dropped from the list here rather than by reloading it, so lines typed but not
        // yet saved survive their neighbours being removed. {@see refreshTotals}
        $ticked = array_map('intval', $this->selected);
        $this->items = array_values(array_filter(
            $this->items,
            fn ($row, $index) => !in_array($index, $ticked, true),
            ARRAY_FILTER_USE_BOTH,
        ));
        $this->selected = [];

        if ($this->items === []) {
            $this->items[] = ['id' => null, 'description' => '', 'price' => '0.00', 'quantity' => 1];
        }

        $this->refreshTotals();
        Notification::make()->title(count($ticked) . ' line(s) removed')->success()->send();
    }

    /** Save Changes under the items table: the lines themselves. */
    public function save(): void
    {
        $this->validate([
            'items.*.description' => 'nullable|string|max:255',
            'items.*.price' => 'required|numeric',
                    ], attributes: ['items.*.price' => 'amount']);

        foreach ($this->items as $row) {
            // A blank new row is the reference's "nothing typed here" — skipped rather
            // than saved as an empty line.
            if (!$row['id'] && trim((string) $row['description']) === '') {
                continue;
            }

            // Amount on screen is the line total. A line left alone keeps the unit price
            // and quantity it came in with — 3 × 70.00 stays that way rather than being
            // flattened on every save. One whose total was edited becomes a single line at
            // the figure typed, because that is the only reading of "this line costs 210"
            // the one box can carry.
            $total = (float) $row['price'];
            $untouched = isset($row['unit'])
                && abs($total - (float) $row['unit'] * max(1, (int) $row['quantity'])) < 0.005;

            $price = $untouched ? (float) $row['unit'] : $total;
            $quantity = $untouched ? max(1, (int) $row['quantity']) : 1;

            if ($row['id']) {
                $this->invoice->items()->where('id', $row['id'])->update([
                    'description' => (string) $row['description'],
                    'price' => $price,
                    'quantity' => $quantity,
                ]);

                continue;
            }

            $this->invoice->items()->create([
                'description' => (string) $row['description'],
                'price' => $price,
                'quantity' => $quantity,
            ]);
        }

        $this->refreshInvoice();
        Notification::make()->title('Invoice saved')->success()->send();
    }

    // ── Options tab ──────────────────────────────────────────────────────────────────

    /** Enabled gateways, for the Options tab's Payment Method. */
    public function gatewayOptions(): array
    {
        return \App\Models\Gateway::where('enabled', true)
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function saveOptions(): void
    {
        $this->validate([
            'options.number' => 'nullable|string|max:255',
            'options.status' => 'required|in:draft,pending,paid,cancelled,refunded',
            'options.taxRate' => 'nullable|numeric|min:0|max:100',
            'options.paymentMethod' => 'nullable|exists:gateways,id',
        ], attributes: [
            'options.number' => 'invoice number',
            'options.status' => 'status',
            'options.taxRate' => 'tax rate',
        ]);

        $this->invoice->number = trim($this->options['number']) ?: null;

        foreach ([['invoiceDate', 'created_at'], ['dueAt', 'due_at']] as [$field, $column]) {
            $date = $this->parseDate($this->options[$field]);

            if ($date !== null) {
                $this->invoice->$column = $date;
            }
        }

        // Status is writable here because the reference's Options tab writes it, but paid
        // still is not offered by hand — see the class docblock.
        if (in_array($this->options['status'], ['draft', 'pending', 'cancelled'], true)) {
            $this->invoice->status = $this->options['status'];
        }

        $this->invoice->save();

        // The rate lives on the invoice's snapshot — the row core reads tax from — so it is
        // written there rather than on the invoice itself. A snapshot is only made when
        // core's invoice_snapshot setting is on; with none there is nothing to carry a rate.
        if (($snapshot = $this->invoice->snapshot) && $this->options['taxRate'] !== '') {
            $snapshot->tax_rate = (float) $this->options['taxRate'];
            $snapshot->save();
        }

        // The reference's Payment Method: which gateway this invoice is to be paid through.
        // An invoice row has no gateway of its own — the client picks one at payment time —
        // so it is stored here and used to pre-select theirs.
        // {@see Support\PreselectInvoiceGateway}
        if ($this->options['paymentMethod'] === '') {
            $this->invoice->properties()->where('key', self::METHOD_KEY)->delete();
        } else {
            $this->invoice->properties()->updateOrCreate(
                ['key' => self::METHOD_KEY],
                ['name' => 'Payment Method', 'value' => $this->options['paymentMethod']],
            );
        }
        $this->refreshInvoice();

        Notification::make()->title('Invoice updated')->success()->send();
    }

    private function parseDate(string $value): ?\Carbon\Carbon
    {
        foreach (['m/d/Y', 'Y-m-d'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, trim($value));
            } catch (\Throwable $e) {
            }
        }

        return null;
    }

    /** The Summary tab's Mark Unpaid / Cancel. */
    /**
     * The reference's Publish. An invoice is raised as a draft — the client cannot see it
     * until this is pressed, which is the whole point of the state — and publishing simply
     * makes it the unpaid invoice it was always going to be.
     *
     * {@see AdminOps::hideDraftInvoicesFromClients()} is what enforces the invisibility.
     */
    public function publish(bool $andEmail = false): void
    {
        if ($this->invoice->status !== 'draft') {
            return;
        }

        $this->invoice->update(['status' => 'pending']);
        $this->refreshInvoice();

        if ($andEmail) {
            $this->emailTemplate = 'new_invoice_created';
            $this->sendEmail();

            return;
        }

        Notification::make()->title('Invoice published')
            ->body('The client can see it now.')->success()->send();
    }

    /**
     * The reference's Split to New Invoice: the ticked lines move to an invoice of their
     * own, leaving the rest on this one. Raised as a draft, so nothing reaches the client
     * until someone publishes it — the same rule Create Invoice follows.
     *
     * Only saved lines can move; one typed and not yet saved has no row to carry across,
     * and is left where it is with the reason said out loud rather than silently dropped.
     */
    private function splitToNewInvoice(): void
    {
        $ticked = array_map('intval', $this->selected);
        $ids = [];
        $unsaved = 0;

        foreach ($ticked as $index) {
            if (!isset($this->items[$index])) {
                continue;
            }

            $this->items[$index]['id'] ? $ids[] = $this->items[$index]['id'] : $unsaved++;
        }

        if ($ids === []) {
            Notification::make()->title('Nothing to split')
                ->body($unsaved > 0 ? 'Save the new lines first — they do not exist yet.' : 'Tick the lines to move.')
                ->warning()->send();

            return;
        }

        $new = DB::transaction(function () use ($ids): Invoice {
            $new = Invoice::create([
                'user_id' => $this->invoice->user_id,
                'currency_code' => $this->invoice->currency_code,
                'due_at' => $this->invoice->due_at ?? now()->addDays(14),
                'status' => 'draft',
            ]);

            $this->invoice->items()->whereIn('id', $ids)->update(['invoice_id' => $new->id]);

            return $new;
        });

        Notification::make()->title('Split to invoice #' . $new->id)
            ->body(count($ids) . ' line(s) moved' . ($unsaved > 0 ? ', ' . $unsaved . ' unsaved line(s) left here' : '') . '.')
            ->success()->send();

        $this->selected = [];
        $this->refreshInvoice();
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, ['pending', 'cancelled'], true)) {
            return;
        }

        $this->invoice->update(['status' => $status]);
        $this->refreshInvoice();

        Notification::make()->title($status === 'pending' ? 'Marked unpaid' : 'Invoice cancelled')->success()->send();
    }

    // ── Add Payment tab ──────────────────────────────────────────────────────────────

    public function addPayment(): void
    {
        // Guarded here as well as hidden in the view: a draft has not been issued, and core
        // marks an invoice paid as soon as the balance lands — which would leave it settled
        // and still invisible to the client. {@see AdminOps::hideDraftInvoicesFromClients}
        if ($this->invoice->status === 'draft') {
            Notification::make()->title('Publish the invoice first')
                ->body('A draft takes no payment — the client cannot see it yet.')
                ->warning()->send();

            return;
        }

        $this->validate([
            'pay.amount' => 'required|numeric|min:0.01',
            'pay.fee' => 'nullable|numeric|min:0',
            'pay.transactionId' => 'nullable|string|max:255',
            'pay.gateway' => 'nullable|string',
        ], attributes: ['pay.amount' => 'amount', 'pay.fee' => 'fees', 'pay.transactionId' => 'transaction ID']);

        try {
            ExtensionHelper::addPayment(
                $this->invoice->id,
                $this->pay['gateway'] ?: null,
                (float) $this->pay['amount'],
                $this->pay['fee'] !== '' ? (float) $this->pay['fee'] : null,
                trim($this->pay['transactionId']) ?: null,
            );
        } catch (\Throwable $e) {
            Notification::make()->title('Payment not recorded')->body($e->getMessage())->danger()->send();

            return;
        }

        // The reference lets the date be back-dated; addPayment() always stamps now, so
        // the row is corrected afterwards rather than the helper being bypassed.
        $date = $this->parseDate($this->pay['date']);

        if ($date !== null && !$date->isSameDay(now())) {
            $latest = $this->invoice->transactions()->latest('id')->first();
            $latest?->forceFill(['created_at' => $date])->saveQuietly();
        }

        $paid = $this->invoice->fresh()->status === 'paid';

        if ($this->pay['sendEmail']) {
            $this->send($paid ? 'invoice_paid' : 'new_invoice_created');
        }

        $this->refreshInvoice();

        Notification::make()->title('Payment added')
            ->body($paid ? 'The invoice is now paid.' : 'Recorded — the invoice still shows a balance.')
            ->success()->send();
    }

    // ── Credit tab ───────────────────────────────────────────────────────────────────

    /** Apply account credit to this invoice. */
    public function addCredit(): void
    {
        $this->validate(['credit.add' => 'required|numeric|min:0.01'], attributes: ['credit.add' => 'amount']);

        $applied = 0.0;

        DB::transaction(function () use (&$applied): void {
            $credit = $this->invoice->user?->credits()
                ->where('currency_code', $this->invoice->currency_code)
                ->lockForUpdate()->first();

            $invoice = Invoice::whereKey($this->invoice->id)->lockForUpdate()->first();

            if (!$credit || !$invoice) {
                return;
            }

            $applied = round(min((float) $this->credit['add'], (float) $credit->amount, (float) $invoice->remaining), 2);

            if ($applied <= 0) {
                return;
            }

            $credit->amount -= $applied;
            $credit->save();

            ExtensionHelper::addPayment($invoice->id, null, amount: $applied, isCreditTransaction: true);
        });

        $this->refreshInvoice();

        if ($applied <= 0) {
            Notification::make()->title('Nothing applied')
                ->body('There is no credit available, or the invoice has no balance left.')->warning()->send();

            return;
        }

        Notification::make()->title('$' . number_format($applied, 2) . ' credit applied')->success()->send();
    }

    /** Take credit back off the invoice and return it to the client's balance. */
    public function removeCredit(): void
    {
        $this->validate(['credit.remove' => 'required|numeric|min:0.01'], attributes: ['credit.remove' => 'amount']);

        $returned = 0.0;

        DB::transaction(function () use (&$returned): void {
            $invoice = Invoice::whereKey($this->invoice->id)->lockForUpdate()->first();

            if (!$invoice) {
                return;
            }

            $wanted = round((float) $this->credit['remove'], 2);

            $rows = $invoice->transactions()
                ->where('is_credit_transaction', true)
                ->where('status', InvoiceTransactionStatus::Succeeded)
                ->orderByDesc('id')->lockForUpdate()->get();

            foreach ($rows as $row) {
                if ($wanted <= 0) {
                    break;
                }

                $take = min($wanted, (float) $row->amount);

                if ($take >= (float) $row->amount) {
                    $row->delete();
                } else {
                    $row->amount = (float) $row->amount - $take;
                    $row->save();
                }

                $wanted -= $take;
                $returned += $take;
            }

            if ($returned <= 0) {
                return;
            }

            $credit = $this->invoice->user?->credits()
                ->where('currency_code', $invoice->currency_code)
                ->lockForUpdate()->first();

            if ($credit) {
                $credit->amount += $returned;
                $credit->save();
            }

            // The invoice was only paid because that credit was on it.
            if ($invoice->status === 'paid') {
                $invoice->refresh();

                if ((float) $invoice->remaining > 0) {
                    $invoice->status = 'pending';
                    $invoice->save();
                }
            }
        });

        $this->refreshInvoice();

        if ($returned <= 0) {
            Notification::make()->title('Nothing removed')
                ->body('This invoice has no applied credit to take back.')->warning()->send();

            return;
        }

        Notification::make()->title('$' . number_format($returned, 2) . ' returned to the client\'s balance')->success()->send();
    }

    // ── Refund tab ───────────────────────────────────────────────────────────────────

    /** Return credit to the customer against this invoice. */
    public function issueRefund(): void
    {
        $this->validate([
            'refund.amount' => 'nullable|numeric|min:0.01',
            'refund.reason' => 'nullable|string|max:1000',
            'refund.type' => 'required|in:' . implode(',', array_keys(self::REFUND_TYPES)),
        ], attributes: ['refund.amount' => 'amount', 'refund.reason' => 'reason', 'refund.type' => 'refund type']);

        // Asked of the gateway rather than assumed: if one ever ships a refund hook this
        // starts working on its own, and until then the refusal names the gateway.
        if ($this->refund['type'] === 'gateway') {
            $gateway = $this->invoice->transactions
                ->where('status', InvoiceTransactionStatus::Succeeded)->first()?->gateway;

            if (!$gateway || !ExtensionHelper::hasFunction($gateway, 'refund')) {
                Notification::make()->title('That gateway cannot refund')
                    ->body(($gateway->name ?? 'The gateway on this invoice') . ' does not implement a refund hook. Use a manual refund or credit the balance.')
                    ->danger()->send();

                return;
            }
        }

        $user = $this->invoice->user;

        if (!$user) {
            Notification::make()->title('This invoice has no client to credit')->danger()->send();

            return;
        }

        $given = 0.0;

        DB::transaction(function () use ($user, &$given): void {
            $paid = (float) Invoice::whereKey($this->invoice->id)->first()
                ->transactions()->where('status', InvoiceTransactionStatus::Succeeded)->sum('amount');

            $alreadyRefunded = Refund::totalFor($this->invoice->id);
            $refundable = round($paid - $alreadyRefunded, 2);

            // Blank means all of it, which is what the box says it means.
            $asked = trim((string) $this->refund['amount']) === ''
                ? $refundable
                : (float) $this->refund['amount'];

            $given = round(min($asked, $refundable), 2);

            if ($given <= 0) {
                return;
            }

            // Only crediting the balance puts money back here. A manual refund has already
            // been sent by hand outside the system, so it is recorded and nothing is added
            // — crediting it too would pay the client twice.
            if ($this->refund['type'] === 'credit') {
                $credit = \App\Models\Credit::firstOrCreate(
                    ['user_id' => $user->id, 'currency_code' => $this->invoice->currency_code],
                    ['amount' => 0],
                );
                $credit->increment('amount', $given);
            }

            Refund::create([
                'invoice_id' => $this->invoice->id,
                'user_id' => $user->id,
                'amount' => $given,
                'currency_code' => $this->invoice->currency_code,
                'reason' => $this->refund['reason'] ?: null,
                'admin_id' => Auth::id(),
            ]);
        });

        if ($given <= 0) {
            Notification::make()->title('Nothing refunded')
                ->body('This invoice has already been refunded in full, or it never took a payment.')
                ->warning()->send();

            return;
        }

        // The reference's Reverse Payment: undo what the payment set going. Here that is
        // the services this invoice paid for — a payment activates or unsuspends them, so
        // reversing it suspends them again. Nothing else on this platform is triggered by a
        // transaction, which is why the row says "where possible" (Leandro, 2026-09-16).
        $reversed = 0;

        if ($this->refund['reverse']) {
            foreach ($this->invoice->items as $item) {
                if ($item->reference_type !== \App\Models\Service::class) {
                    continue;
                }

                $service = $item->reference;

                if ($service && $service->status === \App\Models\Service::STATUS_ACTIVE) {
                    $service->update(['status' => \App\Models\Service::STATUS_SUSPENDED]);
                    $reversed++;
                }
            }
        }

        if ($this->refund['sendEmail']) {
            $this->send('invoice_refund_confirmation');
        }

        $this->resetRefundForm();

        Notification::make()
            ->title('$' . number_format($given, 2) . ' returned to ' . $user->email . '\'s balance')
            ->body('The invoice stays settled — this is credit for the unused part, not a reversal of its payment.'
                . ($reversed > 0 ? ' ' . $reversed . ' service(s) suspended.' : ''))
            ->success()->send();
    }

    /** Reload after a refund; the invoice itself is unchanged but the credit figures are not. */
    private function resetRefundForm(): void
    {
        $this->refund = ['transaction' => '', 'type' => 'credit', 'amount' => '', 'reason' => '', 'reverse' => false, 'sendEmail' => false];
        $this->refreshInvoice();
    }

    // ── Notes tab ────────────────────────────────────────────────────────────────────

    public function saveNote(): void
    {
        $this->validate(['note' => 'nullable|string|max:65535']);

        $existing = $this->invoice->properties()->where('key', self::NOTE_KEY)->first();

        if (trim($this->note) === '') {
            $existing?->delete();
        } elseif ($existing) {
            $existing->update(['value' => $this->note]);
        } else {
            $this->invoice->properties()->create([
                'key' => self::NOTE_KEY,
                'name' => 'Admin note',
                'value' => $this->note,
            ]);
        }

        Notification::make()->title('Note saved')->success()->send();
    }

    // ── Email ────────────────────────────────────────────────────────────────────────

    public function sendEmail(): void
    {
        $this->send($this->emailTemplate);
    }

    private function send(string $template): void
    {
        $user = $this->invoice->user;

        if (!$user) {
            Notification::make()->title('No client on this invoice')->danger()->send();

            return;
        }

        if (!array_key_exists($template, self::EMAILS)) {
            return;
        }

        try {
            // Argument order is (User, Invoice) — the helper reads user-first, and passing
            // them the other way round type-errors rather than sending anything.
            NotificationHelper::invoiceNotification($user, $this->invoice, $template);
        } catch (\Throwable $e) {
            Notification::make()->title('Email not sent')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Email sent to ' . $user->email)->success()->send();
    }

    /**
     * The reference's Download button, through core's own invoice PDF generator — the same
     * one that attaches a copy to every invoice email, so what an admin downloads and what
     * the client receives are the same document.
     */
    public function downloadPdf(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $pdf = \App\Classes\PDF::generateInvoice($this->invoice);
        $name = 'invoice-' . ($this->invoice->number ?: $this->invoice->id) . '.pdf';

        return response()->streamDownload(fn () => print($pdf->output()), $name);
    }


    protected function getViewData(): array
    {
        $succeeded = $this->invoice->transactions
            ->where('status', InvoiceTransactionStatus::Succeeded);

        $creditApplied = $succeeded->where('is_credit_transaction', true)
            ->sum(fn ($transaction) => (float) $transaction->amount);

        // The reference's "Last Capture Attempt": the most recent time anything was tried
        // against this invoice, whatever came of it.
        $lastAttempt = $this->invoice->transactions->sortByDesc('created_at')->first();

        return [
            'gateways' => Gateway::orderBy('name')->get(),
            // Handed to the view: the Refund tab's Transactions picker needs it near the
            // top of the page, where the Blade used to define it only further down.
            'succeeded' => $succeeded,
            'paid' => $succeeded->sum(fn ($transaction) => (float) $transaction->amount),
            'creditApplied' => $creditApplied,
            'availableCredit' => (float) ($this->invoice->user?->credits()
                ->where('currency_code', $this->invoice->currency_code)->value('amount') ?? 0),
            'lastAttempt' => $lastAttempt,
            'refunds' => Refund::with('admin')->where('invoice_id', $this->invoice->id)
                ->orderByDesc('id')->get(),
            // Asked once and used twice: this runs on every Livewire round trip, including
            // the one behind Add Item, and the second call bought nothing.
            'refunded' => $refunded = Refund::totalFor($this->invoice->id),
            'refundable' => round($succeeded->sum(fn ($t) => (float) $t->amount) - $refunded, 2),
            'paymentMethod' => $succeeded->first()?->gateway?->name
                ?? ($creditApplied > 0 ? 'Account credit' : null),
            'clientUrl' => ClientSummary::getUrl(['record' => $this->invoice->user_id]),
            'clientName' => trim(($this->invoice->user->first_name ?? '') . ' ' . ($this->invoice->user->last_name ?? ''))
                ?: ($this->invoice->user->email ?? '—'),
        ];
    }
}
