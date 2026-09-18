<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Helpers\ExtensionHelper;
use App\Models\Gateway;
use App\Models\Invoice;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #15 — WHMCS's Add Transaction / Offline CC Processing: record a payment taken
 * outside the gateways (a bank transfer, cash, a card processed on a terminal).
 */
class AddTransaction extends Page
{
    protected string $view = 'adminops::pages.add-transaction';

    protected static ?string $slug = 'add-transaction';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Bound to the URL so Offline CC Processing can land here with the invoice preselected. */
    #[Url]
    public ?int $invoiceId = null;

    /** Reached from a client's profile, so the invoice list is theirs alone. */
    #[Url]
    public ?int $forUser = null;

    public string $gateway = '';

    public string $transactionId = '';

    /** The reference's Amount In / Amount Out: money arriving, or money going back out. */
    public string $amount = '';

    public string $amountOut = '';

    public string $fee = '';

    public string $description = '';

    /** Its Date — the transaction is recorded as having happened then. */
    public string $date = '';

    /**
     * Its "Add to Client's Credit Balance".
     *
     * The reference can record a transaction against no invoice at all. Here it cannot:
     * InvoiceTransaction's own accessors read `$this->invoice->currency`, so an invoice-less
     * row fatals wherever it is rendered. Money belonging to the client rather than to an
     * invoice is their credit balance, which is what this writes.
     */
    public bool $toCredit = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Add Transaction';
    }

    public function mount(): void
    {
        $this->forUser = request()->integer('for') ?: $this->forUser;
        $this->date = now()->format('m/d/Y');

        // #[Url] hydrates the property before mount() runs but does not fire updatedInvoiceId
        // — that only fires on a later, interactive change — so a deep link with ?invoiceId=
        // would otherwise show the picker filled in with no amount to match.
        $this->updatedInvoiceId();
    }

    /** Prefill the remaining balance when an invoice is picked. */
    public function updatedInvoiceId(): void
    {
        if ($this->invoiceId) {
            $invoice = Invoice::with(['items', 'transactions'])->find($this->invoiceId);
            $this->amount = number_format((float) ($invoice?->remaining ?? 0), 2, '.', '');
        }
    }

    public function create(): void
    {
        $this->validate([
            'invoiceId' => ($this->toCredit ? 'nullable' : 'required') . '|exists:invoices,id',
            'amount' => 'nullable|numeric|min:0',
            'amountOut' => 'nullable|numeric|min:0',
            'fee' => 'nullable|numeric|min:0',
            'transactionId' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'date' => 'nullable|date_format:m/d/Y',
            'forUser' => 'nullable|exists:users,id',
        ], attributes: ['invoiceId' => 'invoice', 'amountOut' => 'amount out']);

        $in = (float) ($this->amount ?: 0);
        $out = (float) ($this->amountOut ?: 0);

        if ($in <= 0 && $out <= 0) {
            Notification::make()->title('Nothing to record')
                ->body('Enter an amount in or an amount out.')->warning()->send();

            return;
        }

        // Credit is money against the client, not an invoice. {@see self::$toCredit}
        if ($this->toCredit) {
            $this->creditTheClient($in - $out);

            return;
        }

        try {
            ExtensionHelper::addPayment(
                $this->invoiceId,
                $this->gateway ?: null,
                // Out is the same movement with the sign reversed — a refund recorded by hand.
                $in > 0 ? $in : -$out,
                $this->fee !== '' ? (float) $this->fee : null,
                $this->transactionId ?: null,
            );

            $this->stampTransaction();
        } catch (\Throwable $e) {
            Notification::make()->title('Payment not recorded')->body($e->getMessage())->danger()->send();

            return;
        }

        $invoice = Invoice::find($this->invoiceId);
        Notification::make()->title('Transaction recorded')
            ->body('Invoice ' . ($invoice->number ?? $invoice->id) . ' is now ' . $invoice->status . '.')
            ->success()->send();
        $this->reset(['invoiceId', 'transactionId', 'amount', 'amountOut', 'fee', 'description', 'toCredit']);
        $this->date = now()->format('m/d/Y');
    }

    /**
     * The reference's Description and Date land on the row ExtensionHelper just wrote.
     *
     * The helper takes neither, and it is the only idempotent way in — it is what stops a
     * repeated transaction id being recorded twice — so the row is found and finished here
     * rather than written directly.
     */
    private function stampTransaction(): void
    {
        $row = \App\Models\InvoiceTransaction::where('invoice_id', $this->invoiceId)
            ->latest('id')->first();

        if (!$row) {
            return;
        }

        if (trim($this->description) !== ''
            && \Illuminate\Support\Facades\Schema::hasColumn('invoice_transactions', 'description')) {
            $row->description = trim($this->description);
        }

        if ($on = $this->parseDate($this->date)) {
            $row->created_at = $on;
        }

        $row->save();
    }

    /** Money recorded against the client rather than an invoice. */
    private function creditTheClient(float $amount): void
    {
        $user = \App\Models\User::find($this->forUser);

        if (!$user) {
            Notification::make()->title('No client')
                ->body('Open this from a client profile to add to their credit balance.')
                ->warning()->send();

            return;
        }

        $currency = config('settings.default_currency', 'USD');

        $credit = \App\Models\Credit::firstOrNew(['user_id' => $user->id, 'currency_code' => $currency]);
        $credit->amount = (float) ($credit->amount ?? 0) + $amount;
        $credit->save();

        Notification::make()->title('Credit balance updated')
            ->body($user->email . ' now holds ' . number_format((float) $credit->amount, 2) . ' ' . $currency . '.')
            ->success()->send();

        $this->reset(['invoiceId', 'transactionId', 'amount', 'amountOut', 'fee', 'description', 'toCredit']);
        $this->date = now()->format('m/d/Y');
    }

    /** MM/DD/YYYY, as the reference writes it. */
    private function parseDate(string $value): ?\Carbon\Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('m/d/Y', trim($value));
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getViewData(): array
    {
        return [
            'invoices' => Invoice::with('user')->where('status', 'pending')
                ->when($this->forUser, fn ($q) => $q->where('user_id', $this->forUser))
                ->latest('id')->limit(300)->get(),
            'gateways' => Gateway::pluck('extension', 'extension')->all(),
        ];
    }
}
