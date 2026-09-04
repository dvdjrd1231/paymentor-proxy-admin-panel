<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\InvoiceResource;
use App\Helpers\ExtensionHelper;
use App\Models\Gateway;
use App\Models\Invoice;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * The reference's invoice screen (user request, 2026-09-04: core's raw resource edit was
 * the last place Manage Invoices sent anyone): the facts band, the line items with the
 * Sub Total / Amount Paid / Balance Due ladder, the transactions list, and Add Payment.
 *
 * Add Payment goes through {@see ExtensionHelper::addPayment} — the same idempotent call
 * every gateway webhook makes, so core's own listener flips the invoice to paid when the
 * balance lands, exactly as a real payment would. Marking unpaid/cancelled writes the
 * status column with a confirm; marking *paid* is deliberately not a dropdown option —
 * paid is what happens when payments cover the total, and Add Payment is the way to say
 * money arrived.
 */
class EditInvoice extends Page
{
    protected string $view = 'adminops::pages.edit-invoice';

    protected static ?string $slug = 'edit-invoice';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Same reasoning as {@see ClientSummary::$customer} — not `$record`. */
    public Invoice $invoice;

    /** The editable line items: [{id, description, price, quantity}]. */
    public array $items = [];

    public string $dueAt = '';

    /** The Add Payment form. */
    public array $pay = ['amount' => '', 'fee' => '', 'transactionId' => '', 'gateway' => ''];

    public ?string $confirming = null;

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

        $this->dueAt = $this->invoice->due_at?->format('m/d/Y') ?? '';
        $this->pay['amount'] = number_format(max(0, (float) $this->invoice->remaining), 2, '.', '');
    }

    /** Save Changes: line items and the due date — the columns this screen owns. */
    public function save(): void
    {
        $this->validate([
            'items.*.description' => 'nullable|string|max:255',
            'items.*.price' => 'required|numeric',
            'items.*.quantity' => 'required|integer|min:1',
        ], attributes: ['items.*.price' => 'amount', 'items.*.quantity' => 'quantity']);

        foreach ($this->items as $row) {
            $this->invoice->items()->where('id', $row['id'])->update([
                'description' => (string) $row['description'],
                'price' => (float) $row['price'],
                'quantity' => (int) $row['quantity'],
            ]);
        }

        foreach (['m/d/Y', 'Y-m-d'] as $format) {
            try {
                $this->invoice->due_at = \Carbon\Carbon::createFromFormat($format, trim($this->dueAt));
                break;
            } catch (\Throwable $e) {
            }
        }
        $this->invoice->save();

        $this->invoice->refresh()->load(['items', 'transactions.gateway']);
        $this->loadForm();
        Notification::make()->title('Invoice saved')->success()->send();
    }

    /** The status select — Unpaid and Cancelled only; Paid arrives via Add Payment. */
    public function setStatus(string $status): void
    {
        if (!in_array($status, ['pending', 'cancelled'], true)) {
            return;
        }

        $this->invoice->update(['status' => $status]);
        Notification::make()->title($status === 'pending' ? 'Marked unpaid' : 'Invoice cancelled')->success()->send();
    }

    /** The reference's Add Payment — the same call a gateway webhook makes. */
    public function addPayment(): void
    {
        $this->validate([
            'pay.amount' => 'required|numeric|min:0.01',
            'pay.fee' => 'nullable|numeric|min:0',
            'pay.transactionId' => 'nullable|string|max:255',
            'pay.gateway' => 'nullable|string',
        ], attributes: ['pay.amount' => 'amount', 'pay.fee' => 'fees', 'pay.transactionId' => 'transaction ID', 'pay.gateway' => 'payment method']);

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

        $this->invoice->refresh()->load(['items', 'transactions.gateway']);
        $this->pay = ['amount' => '', 'fee' => '', 'transactionId' => '', 'gateway' => ''];
        $this->loadForm();

        Notification::make()->title('Payment added')
            ->body($this->invoice->status === 'paid' ? 'The invoice is now paid.' : 'Recorded — the invoice still shows a balance.')
            ->success()->send();
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
        $paid = $this->invoice->transactions
            ->where('status', \App\Enums\InvoiceTransactionStatus::Succeeded)
            ->sum(fn ($transaction) => (float) $transaction->amount);

        return [
            'gateways' => Gateway::orderBy('name')->get(),
            'paid' => $paid,
            'clientUrl' => ClientSummary::getUrl(['record' => $this->invoice->user_id]),
            'clientName' => trim(($this->invoice->user->first_name ?? '') . ' ' . ($this->invoice->user->last_name ?? ''))
                ?: ($this->invoice->user->email ?? '—'),
        ];
    }
}
