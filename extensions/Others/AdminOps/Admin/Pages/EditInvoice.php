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
    public array $options = ['invoiceDate' => '', 'dueAt' => '', 'number' => '', 'status' => ''];

    /** The Add Payment tab. */
    public array $pay = ['date' => '', 'amount' => '', 'fee' => '', 'transactionId' => '', 'gateway' => '', 'sendEmail' => true];

    /** The Credit tab's two boxes. */
    public array $credit = ['add' => '', 'remove' => ''];

    /** The Refund tab. */
    public array $refund = ['amount' => '', 'reason' => '', 'sendEmail' => false];

    /** The Notes tab, stored as a property on the invoice. */
    public string $note = '';

    /** Which notification the Summary tab's Send Email button would send. */
    public string $emailTemplate = 'new_invoice_created';

    public ?string $confirming = null;

    /** The emails this screen can actually send. */
    public const EMAILS = [
        'new_invoice_created' => 'Invoice Created',
        'invoice_paid' => 'Invoice Payment Confirmation',
        'invoice_payment_failed' => 'Invoice Payment Failed',
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
        $this->items = $this->invoice->items->map(fn ($item): array => [
            'id' => $item->id,
            'description' => (string) $item->description,
            'price' => number_format((float) $item->price, 2, '.', ''),
            'quantity' => (int) $item->quantity,
        ])->values()->all();

        $this->selected = [];

        $this->options = [
            'invoiceDate' => $this->invoice->created_at?->format('m/d/Y') ?? '',
            'dueAt' => $this->invoice->due_at?->format('m/d/Y') ?? '',
            'number' => (string) ($this->invoice->number ?? ''),
            'status' => $this->invoice->status,
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
        $this->refreshInvoice();

        Notification::make()->title('Line removed')->success()->send();
    }

    /** The reference's "- With Selected -" menu. Delete is the only entry it can honour. */
    public function withSelected(string $action): void
    {
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

        $this->refreshInvoice();
        Notification::make()->title(count($ids) . ' line(s) removed')->success()->send();
    }

    /** Save Changes under the items table: the lines themselves. */
    public function save(): void
    {
        $this->validate([
            'items.*.description' => 'nullable|string|max:255',
            'items.*.price' => 'required|numeric',
            'items.*.quantity' => 'required|integer|min:1',
        ], attributes: ['items.*.price' => 'amount', 'items.*.quantity' => 'quantity']);

        foreach ($this->items as $row) {
            // A blank new row is the reference's "nothing typed here" — skipped rather
            // than saved as an empty line.
            if (!$row['id'] && trim((string) $row['description']) === '') {
                continue;
            }

            if ($row['id']) {
                $this->invoice->items()->where('id', $row['id'])->update([
                    'description' => (string) $row['description'],
                    'price' => (float) $row['price'],
                    'quantity' => (int) $row['quantity'],
                ]);

                continue;
            }

            $this->invoice->items()->create([
                'description' => (string) $row['description'],
                'price' => (float) $row['price'],
                'quantity' => (int) $row['quantity'],
            ]);
        }

        $this->refreshInvoice();
        Notification::make()->title('Invoice saved')->success()->send();
    }

    // ── Options tab ──────────────────────────────────────────────────────────────────

    public function saveOptions(): void
    {
        $this->validate([
            'options.number' => 'nullable|string|max:255',
            'options.status' => 'required|in:pending,paid,cancelled,refunded',
        ], attributes: ['options.number' => 'invoice number', 'options.status' => 'status']);

        $this->invoice->number = trim($this->options['number']) ?: null;

        foreach ([['invoiceDate', 'created_at'], ['dueAt', 'due_at']] as [$field, $column]) {
            $date = $this->parseDate($this->options[$field]);

            if ($date !== null) {
                $this->invoice->$column = $date;
            }
        }

        // Status is writable here because the reference's Options tab writes it, but paid
        // still is not offered by hand — see the class docblock.
        if (in_array($this->options['status'], ['pending', 'cancelled'], true)) {
            $this->invoice->status = $this->options['status'];
        }

        $this->invoice->save();
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
            'refund.amount' => 'required|numeric|min:0.01',
            'refund.reason' => 'nullable|string|max:1000',
        ], attributes: ['refund.amount' => 'amount', 'refund.reason' => 'reason']);

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

            $given = round(min((float) $this->refund['amount'], $refundable), 2);

            if ($given <= 0) {
                return;
            }

            $credit = \App\Models\Credit::firstOrCreate(
                ['user_id' => $user->id, 'currency_code' => $this->invoice->currency_code],
                ['amount' => 0],
            );
            $credit->increment('amount', $given);

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

        if ($this->refund['sendEmail']) {
            $this->send('invoice_paid');
        }

        $this->resetRefundForm();

        Notification::make()
            ->title('$' . number_format($given, 2) . ' returned to ' . $user->email . '\'s balance')
            ->body('The invoice stays settled — this is credit for the unused part, not a reversal of its payment.')
            ->success()->send();
    }

    /** Reload after a refund; the invoice itself is unchanged but the credit figures are not. */
    private function resetRefundForm(): void
    {
        $this->refund = ['amount' => '', 'reason' => '', 'sendEmail' => false];
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

    public function runDelete(): void
    {
        $this->reset('confirming');

        if (!InvoiceResource::canDelete($this->invoice)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $id = $this->invoice->id;
        $this->invoice->items()->delete();
        $this->invoice->delete();

        Notification::make()->title("Invoice #{$id} deleted")->success()->send();
        $this->redirect(ManageInvoices::getUrl());
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
            'paid' => $succeeded->sum(fn ($transaction) => (float) $transaction->amount),
            'creditApplied' => $creditApplied,
            'availableCredit' => (float) ($this->invoice->user?->credits()
                ->where('currency_code', $this->invoice->currency_code)->value('amount') ?? 0),
            'lastAttempt' => $lastAttempt,
            'refunds' => Refund::with('admin')->where('invoice_id', $this->invoice->id)
                ->orderByDesc('id')->get(),
            'refunded' => Refund::totalFor($this->invoice->id),
            'refundable' => round(
                $succeeded->sum(fn ($t) => (float) $t->amount) - Refund::totalFor($this->invoice->id),
                2,
            ),
            'paymentMethod' => $succeeded->first()?->gateway?->name
                ?? ($creditApplied > 0 ? 'Account credit' : null),
            'clientUrl' => ClientSummary::getUrl(['record' => $this->invoice->user_id]),
            'clientName' => trim(($this->invoice->user->first_name ?? '') . ' ' . ($this->invoice->user->last_name ?? ''))
                ?: ($this->invoice->user->email ?? '—'),
        ];
    }
}
