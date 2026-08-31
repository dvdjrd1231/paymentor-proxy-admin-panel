<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Helpers\ExtensionHelper;
use App\Models\Gateway;
use App\Models\Invoice;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #15 — WHMCS's Add Transaction / Offline CC Processing: record a payment taken
 * outside the gateways (a bank transfer, cash, a card processed on a terminal).
 *
 * The write goes through core's own {@see ExtensionHelper::addPayment} — the same
 * idempotent path every gateway webhook uses — so the invoice flips to paid when covered,
 * the client is notified, and a repeated transaction id cannot double-pay. Nothing here
 * touches money logic; it only speaks core's language.
 */
class AddTransaction extends Page
{
    protected string $view = 'adminops::pages.add-transaction';

    protected static ?string $slug = 'add-transaction';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public ?int $invoiceId = null;

    public string $gateway = '';

    public string $transactionId = '';

    public string $amount = '';

    public string $fee = '';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Add Transaction';
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
            'invoiceId' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'fee' => 'nullable|numeric|min:0',
            'transactionId' => 'nullable|string|max:255',
        ], attributes: ['invoiceId' => 'invoice']);

        try {
            ExtensionHelper::addPayment(
                $this->invoiceId,
                $this->gateway ?: null,
                (float) $this->amount,
                $this->fee !== '' ? (float) $this->fee : null,
                $this->transactionId ?: null,
            );
        } catch (\Throwable $e) {
            Notification::make()->title('Payment not recorded')->body($e->getMessage())->danger()->send();

            return;
        }

        $invoice = Invoice::find($this->invoiceId);
        Notification::make()->title('Transaction recorded')
            ->body('Invoice ' . ($invoice->number ?? $invoice->id) . ' is now ' . $invoice->status . '.')
            ->success()->send();
        $this->reset(['invoiceId', 'transactionId', 'amount', 'fee']);
    }

    protected function getViewData(): array
    {
        return [
            'invoices' => Invoice::with('user')->where('status', 'pending')->latest('id')->limit(300)->get(),
            'gateways' => Gateway::pluck('extension', 'extension')->all(),
        ];
    }
}
