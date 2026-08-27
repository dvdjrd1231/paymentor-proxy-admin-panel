<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Admin\Pages;

use App\Models\InvoiceTransaction;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;

/**
 * The reference's **Transactions** page: money in, fees, money out.
 *
 * Its three tiles — Total Income, Total Fees, Total Expenditure — and its columns: Client
 * Name, Date, Payment Method, Description, **Amount In**, **Fees**, **Amount Out**.
 *
 * Core's transaction list has Amount and nothing else, which makes a perfectly ordinary
 * question unanswerable: *what did we actually keep*. A gateway takes its cut before the
 * money arrives, and a refund gives some of it back, so gross receipts are not revenue and
 * a list that only shows receipts cannot say so.
 *
 * ## Where each column comes from
 *
 * - **Amount In** — `invoice_transactions.amount`, which core already records.
 * - **Fees** — `invoice_transactions.fee`. **This column has existed in Paymenter since the
 *   table was created and nothing has ever written to it.** It is populated here for any
 *   gateway that reports a fee; the ones here do not yet, which is why the Fees tile reads
 *   zero and says so rather than implying no fees were charged.
 * - **Amount Out** — refunds, from `Others/InvoiceOps`. This is the half that makes the
 *   reference's Expenditure tile mean anything, and it is why this page lives in this
 *   extension rather than in AdminOps: without the refund record there is nothing to put
 *   in the column.
 *
 * Never summed across currencies, for the reason in `AdminOps\Support\Money`: Paymenter
 * stores a price per currency and no exchange rate, so a single total spanning two would be
 * neither of them.
 */
class Transactions extends Page
{
    protected string $view = 'invoiceops::pages.transactions';

    protected static ?string $slug = 'transactions-report';

    protected static bool $shouldRegisterNavigation = false;

    /** A page of money. Reading it needs the permission that governs reading invoices. */
    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Transactions';
    }

    public function getSubheading(): ?string
    {
        return 'What came in, what the gateways took, and what went back out.';
    }

    protected function getViewData(): array
    {
        $rows = InvoiceTransaction::query()
            ->with(['invoice.user', 'gateway'])
            ->latest('id')
            ->limit(200)
            ->get();

        $refunds = InvoiceRefund::query()
            ->with(['invoice.user', 'admin'])
            ->latest('id')
            ->limit(200)
            ->get();

        return [
            'rows' => $this->merge($rows, $refunds),
            'totals' => $this->totals(),
            'feesRecorded' => InvoiceTransaction::where('fee', '>', 0)->exists(),
        ];
    }

    /**
     * One list, in date order, of payments and refunds.
     *
     * Interleaved rather than shown as two tables, because the reference's ledger reads as
     * one story per customer and because "paid, then half of it refunded a week later" is
     * only obvious when the two lines are next to each other.
     *
     * @return array<int, array<string, mixed>>
     */
    private function merge($transactions, $refunds): array
    {
        $rows = [];

        foreach ($transactions as $transaction) {
            $rows[] = [
                'at' => $transaction->created_at,
                'customer' => $transaction->invoice?->user?->email ?? '—',
                'method' => $transaction->is_credit_transaction
                    ? 'Account credit'
                    : ($transaction->gateway?->name ?? 'Unknown'),
                'description' => 'Invoice #' . ($transaction->invoice?->number ?: $transaction->invoice_id)
                    . ($transaction->transaction_id ? ' · ' . $transaction->transaction_id : ''),
                'in' => (float) $transaction->amount,
                'fee' => (float) ($transaction->fee ?? 0),
                'out' => 0.0,
                'currency' => $transaction->invoice?->currency_code ?? config('settings.default_currency'),
            ];
        }

        foreach ($refunds as $refund) {
            $rows[] = [
                'at' => $refund->created_at,
                'customer' => $refund->invoice?->user?->email ?? '—',
                'method' => 'Refund · ' . ($refund->method === InvoiceRefund::METHOD_GATEWAY ? 'gateway' : 'offline'),
                'description' => 'Invoice #' . ($refund->invoice?->number ?: $refund->invoice_id)
                    . ($refund->reason ? ' · ' . str($refund->reason)->limit(60) : '')
                    . ($refund->admin ? ' · by ' . $refund->admin->name : ''),
                'in' => 0.0,
                'fee' => 0.0,
                'out' => (float) $refund->amount,
                'currency' => $refund->currency_code,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return array_slice($rows, 0, 200);
    }

    /**
     * The reference's three tiles, per currency.
     *
     * Per currency and not one number, because there is no rate to convert with. A store
     * selling only in USD — the normal case — sees exactly what the reference shows.
     *
     * @return array<string, array{in: float, fee: float, out: float}>
     */
    private function totals(): array
    {
        $totals = [];

        InvoiceTransaction::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_transactions.invoice_id')
            ->groupBy('invoices.currency_code')
            ->selectRaw('invoices.currency_code as code, SUM(invoice_transactions.amount) as amount_sum, SUM(invoice_transactions.fee) as fee_sum')
            ->get()
            ->each(function ($row) use (&$totals): void {
                $totals[$row->code]['in'] = (float) $row->amount_sum;
                $totals[$row->code]['fee'] = (float) $row->fee_sum;
            });

        InvoiceRefund::query()
            ->groupBy('currency_code')
            ->selectRaw('currency_code as code, SUM(amount) as amount_sum')
            ->get()
            ->each(function ($row) use (&$totals): void {
                $totals[$row->code]['out'] = (float) $row->amount_sum;
            });

        // Fill the gaps: a currency with refunds and no payments, or the reverse, would
        // otherwise render a missing key rather than a zero.
        foreach ($totals as $code => $figures) {
            $totals[$code] = array_merge(['in' => 0.0, 'fee' => 0.0, 'out' => 0.0], $figures);
        }

        return $totals;
    }
}
