<?php

namespace Paymenter\Extensions\Others\InvoiceOps\Admin\Pages;

use App\Models\Gateway;
use App\Models\InvoiceTransaction;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Paymenter\Extensions\Others\InvoiceOps\Models\InvoiceRefund;
use Paymenter\Extensions\Others\InvoiceOps\Models\TransactionNote;

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

    // ── The reference's Search/Filter panel ──────────────────────────────────
    // Every field is a URL, so a filtered ledger can be pasted into a ticket.

    #[\Livewire\Attributes\Url]
    public bool $filter = false;

    /** '' (All Activity) | in (Payments) | out (Refunds) | credit (Account Credit). */
    #[\Livewire\Attributes\Url]
    public string $show = '';

    #[\Livewire\Attributes\Url]
    public string $q = '';

    #[\Livewire\Attributes\Url]
    public string $tid = '';

    /** "MM/DD/YYYY - MM/DD/YYYY", or a single date. */
    #[\Livewire\Attributes\Url]
    public string $dates = '';

    #[\Livewire\Attributes\Url]
    public string $amount = '';

    #[\Livewire\Attributes\Url]
    public string $method = '';

    // ── The reference's Add Transaction tab ──────────────────────────────────

    #[\Livewire\Attributes\Url]
    public bool $adding = false;

    public string $txDate = '';

    public ?int $txClient = null;

    public string $txId = '';

    /** The reference's own free-text Description — see {@see TransactionNote}. */
    public string $txDescription = '';

    public string $txInvoice = '';

    public string $txMethod = '';

    public string $txCurrency = '';

    public string $txIn = '';

    public string $txFees = '';

    public string $txOut = '';

    public bool $txCredit = false;

    /** A page of money. Reading it needs the permission that governs reading invoices. */
    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Transactions';
    }

    public function mount(): void
    {
        $this->txDate = now()->format('m/d/Y');
        $this->txCurrency = (string) config('settings.default_currency', 'USD');
    }

    public function toggleFilter(): void
    {
        $this->filter = !$this->filter;

        if ($this->filter) {
            $this->adding = false;
        }
    }

    public function toggleAdding(): void
    {
        $this->adding = !$this->adding;

        if ($this->adding) {
            $this->filter = false;
        }
    }

    /**
     * The reference's Add Transaction, over the writes this panel already trusts:
     * money in goes through core's idempotent {@see ExtensionHelper::addPayment} against
     * one invoice; money out records an offline refund; the Credit box tops up the
     * client's real credit balance. Each is refused, not faked, when its target is
     * missing.
     */
    public function addTransaction(): void
    {
        $this->validate([
            'txIn' => 'nullable|numeric|min:0',
            'txFees' => 'nullable|numeric|min:0',
            'txOut' => 'nullable|numeric|min:0',
            'txInvoice' => 'nullable|string|max:64',
            'txId' => 'nullable|string|max:255',
            'txDescription' => 'nullable|string|max:255',
        ], attributes: ['txIn' => 'amount in', 'txFees' => 'fees', 'txOut' => 'amount out', 'txInvoice' => 'invoice ID', 'txDescription' => 'description']);

        $in = (float) ($this->txIn ?: 0);
        $out = (float) ($this->txOut ?: 0);
        $invoiceIds = array_values(array_filter(array_map('trim', explode(',', $this->txInvoice))));

        if (count($invoiceIds) > 1) {
            $this->addError('txInvoice', 'Paymenter records a transaction against one invoice — enter a single ID.');

            return;
        }

        $invoice = $invoiceIds !== [] ? \App\Models\Invoice::find((int) $invoiceIds[0]) : null;

        if ($invoiceIds !== [] && !$invoice) {
            $this->addError('txInvoice', 'No invoice #' . $invoiceIds[0] . ' exists.');

            return;
        }

        if ($in <= 0 && $out <= 0) {
            $this->addError('txIn', 'Enter an Amount In or an Amount Out.');

            return;
        }

        try {
            if ($in > 0 && $invoice) {
                $transaction = ExtensionHelper::addPayment(
                    $invoice->id,
                    $this->txMethod ?: null,
                    $in,
                    $this->txFees !== '' ? (float) $this->txFees : null,
                    $this->txId ?: null,
                );

                // The reference's Date field: an offline payment recorded after the fact
                // belongs on the day it happened, not the day it was typed in.
                if ($transaction && ($when = $this->pickedDate())) {
                    $transaction->created_at = $when;
                    $transaction->save();
                }

                // The reference's own free-text Description — core's transaction row has
                // no column for it, so it lands in this extension's own table instead.
                if ($transaction && trim($this->txDescription) !== '') {
                    TransactionNote::create([
                        'transaction_id' => $transaction->id,
                        'note' => trim($this->txDescription),
                    ]);
                }
            } elseif ($in > 0 && !$this->txCredit) {
                $this->addError('txInvoice', 'An Amount In needs an invoice — or tick Credit to top up the client\'s balance instead.');

                return;
            }

            if ($in > 0 && $this->txCredit) {
                if (!$this->txClient) {
                    $this->addError('txClient', 'Pick the client whose credit balance this tops up.');

                    return;
                }

                $credit = \App\Models\Credit::firstOrCreate(
                    ['user_id' => $this->txClient, 'currency_code' => strtoupper($this->txCurrency ?: config('settings.default_currency', 'USD'))],
                    ['amount' => 0],
                );
                $credit->increment('amount', $in);
            }

            if ($out > 0) {
                if (!$invoice) {
                    $this->addError('txOut', 'An Amount Out needs the Invoice ID it refunds.');

                    return;
                }

                InvoiceRefund::create([
                    'invoice_id' => $invoice->id,
                    'transaction_id' => null,
                    'amount' => $out,
                    'currency_code' => $invoice->currency_code,
                    'method' => InvoiceRefund::METHOD_OFFLINE,
                    // The reference's own Description field — an offline refund has no
                    // gateway transaction to note instead, so it goes here.
                    'reason' => trim($this->txDescription) ?: ($this->txId ?: 'Recorded from Add Transaction'),
                    'admin_id' => Auth::id(),
                ]);
            }
        } catch (\Throwable $e) {
            Notification::make()->title('Transaction not recorded')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Transaction recorded')->success()->send();
        $this->reset(['txClient', 'txId', 'txInvoice', 'txIn', 'txFees', 'txOut', 'txCredit', 'txDescription']);
        $this->adding = false;
    }

    private function pickedDate(): ?\Carbon\Carbon
    {
        foreach (['m/d/Y', 'Y-m-d'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, trim($this->txDate))->setTimeFrom(now());
            } catch (\Throwable $e) {
            }
        }

        return null;
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

        // One query for every note the visible page of transactions might have, keyed by
        // transaction id — merge() reads this rather than each row firing its own lookup.
        $notes = TransactionNote::whereIn('transaction_id', $rows->pluck('id'))->pluck('note', 'transaction_id');

        return [
            'rows' => $this->sift($this->merge($rows, $refunds, $notes)),
            'totals' => $this->totals(),
            'deltas' => $this->deltas(),
            'chart' => $this->chart(),
            'balances' => $this->gatewayBalances(),
            'feesRecorded' => InvoiceTransaction::where('fee', '>', 0)->exists(),
            'gateways' => Gateway::orderBy('name')->get(['id', 'name', 'extension']),
            'clients' => \App\Models\User::whereNull('role_id')->orderBy('first_name')->limit(500)
                ->get(['id', 'first_name', 'last_name', 'email']),
            'currencies' => \App\Models\Currency::pluck('code')->all() ?: [config('settings.default_currency', 'USD')],
        ];
    }

    /**
     * The Search/Filter panel, applied to the merged ledger — the same rows the grid
     * shows, so what the filter answers is exactly what the eye would have scanned for.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sift(array $rows): array
    {
        [$from, $to] = $this->parsedDates();

        return array_values(array_filter($rows, function (array $row) use ($from, $to): bool {
            if ($this->show === 'in' && $row['in'] <= 0) {
                return false;
            }

            if ($this->show === 'out' && $row['out'] <= 0) {
                return false;
            }

            if ($this->show === 'credit' && $row['method'] !== 'Account credit') {
                return false;
            }

            if ($this->q !== '' && !str_contains(strtolower($row['description'] . ' ' . $row['customer']), strtolower($this->q))) {
                return false;
            }

            if ($this->tid !== '' && !str_contains(strtolower((string) $row['trans']), strtolower($this->tid))) {
                return false;
            }

            if ($this->amount !== '' && is_numeric($this->amount)
                && abs($row['in'] - (float) $this->amount) >= 0.005
                && abs($row['out'] - (float) $this->amount) >= 0.005) {
                return false;
            }

            if ($this->method !== '' && $row['method'] !== $this->method) {
                return false;
            }

            if ($from && $row['at'] && $row['at']->lt($from->startOfDay())) {
                return false;
            }

            if ($to && $row['at'] && $row['at']->gt($to->copy()->endOfDay())) {
                return false;
            }

            return true;
        }));
    }

    /** @return array{?\Carbon\Carbon, ?\Carbon\Carbon} */
    private function parsedDates(): array
    {
        $text = trim($this->dates);

        if ($text === '') {
            return [null, null];
        }

        $parse = function (string $piece): ?\Carbon\Carbon {
            foreach (['m/d/Y', 'Y-m-d', 'd/m/Y'] as $format) {
                try {
                    return \Carbon\Carbon::createFromFormat($format, trim($piece));
                } catch (\Throwable $e) {
                }
            }

            return null;
        };

        $pieces = preg_split('/\s+[-–]\s+/', $text, 2);
        $from = $parse($pieces[0]);
        $to = isset($pieces[1]) ? $parse($pieces[1]) : $from;

        return [$from, $to];
    }

    /**
     * The reference's area chart: net revenue per day (amount minus fee), last 30 days, in
     * the store's default currency — the one line a chart without exchange rates can draw.
     *
     * @return array{currency: string, days: array<int, array{date: string, net: float}>}
     */
    private function chart(): array
    {
        $currency = config('settings.default_currency', 'USD');

        $sums = InvoiceTransaction::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_transactions.invoice_id')
            ->where('invoices.currency_code', $currency)
            ->where('invoice_transactions.created_at', '>=', now()->subDays(30)->startOfDay())
            ->groupBy(DB::raw('DATE(invoice_transactions.created_at)'))
            ->selectRaw('DATE(invoice_transactions.created_at) as day, SUM(invoice_transactions.amount - invoice_transactions.fee) as net')
            ->pluck('net', 'day');

        $days = [];
        for ($i = 30; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[] = ['date' => $date, 'net' => (float) ($sums[$date] ?? 0)];
        }

        return ['currency' => $currency, 'days' => $days];
    }

    /**
     * The reference's "-1% from last 30 days" lines: this month against the month before,
     * per currency, for income / fees / refunds.
     *
     * @return array<string, array{in: float|null, fee: float|null, out: float|null}>
     */
    private function deltas(): array
    {
        $window = fn ($from, $to) => InvoiceTransaction::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_transactions.invoice_id')
            ->whereBetween('invoice_transactions.created_at', [$from, $to])
            ->groupBy('invoices.currency_code')
            ->selectRaw('invoices.currency_code as code, SUM(invoice_transactions.amount) as in_sum, SUM(invoice_transactions.fee) as fee_sum')
            ->get()
            ->keyBy('code');

        $current = $window(now()->subDays(30), now());
        $previous = $window(now()->subDays(60), now()->subDays(30));

        $percent = function (?float $now, ?float $then): ?float {
            if (!$then) {
                return null;
            }

            return round((($now ?? 0) - $then) / $then * 100);
        };

        $deltas = [];
        foreach ($current->keys()->merge($previous->keys())->unique() as $code) {
            $deltas[$code] = [
                'in' => $percent((float) ($current[$code]->in_sum ?? 0), (float) ($previous[$code]->in_sum ?? 0)),
                'fee' => $percent((float) ($current[$code]->fee_sum ?? 0), (float) ($previous[$code]->fee_sum ?? 0)),
                'out' => null,
            ];
        }

        return $deltas;
    }

    /** The reference's Refresh button beside Gateway Balances: forget the cache, re-ask. */
    public function refreshBalances(): void
    {
        Cache::forget('invoiceops.gateway-balances');
    }

    /**
     * The reference's Gateway Balances panel. Stripe is the one connected gateway with a
     * balance API; asked politely, cached five minutes, and absent — not faked — when the
     * key is missing or the call fails.
     *
     * @return array{at: string, tiles: array<int, array{gateway: string, label: string, amount: string}>}
     */
    private function gatewayBalances(): array
    {
        return Cache::remember('invoiceops.gateway-balances', 300, function (): array {
            try {
                $secret = Gateway::where('extension', 'Stripe')->first()
                    ?->settings->firstWhere('key', 'stripe_secret_key')?->value;

                if (!$secret) {
                    return ['at' => now()->toIso8601String(), 'tiles' => []];
                }

                $response = Http::withToken($secret)->timeout(8)->get('https://api.stripe.com/v1/balance');

                if (!$response->ok()) {
                    return ['at' => now()->toIso8601String(), 'tiles' => []];
                }

                $tiles = [];
                foreach (['available' => 'Available', 'pending' => 'Pending'] as $key => $label) {
                    foreach ($response->json($key, []) as $bucket) {
                        $tiles[] = [
                            'gateway' => 'Stripe',
                            'label' => $label,
                            'amount' => '$' . number_format($bucket['amount'] / 100, 2) . ' ' . strtoupper($bucket['currency']),
                        ];
                    }
                }

                return ['at' => now()->toIso8601String(), 'tiles' => $tiles];
            } catch (\Throwable $e) {
                return ['at' => now()->toIso8601String(), 'tiles' => []];
            }
        });
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
    private function merge($transactions, $refunds, $notes = null): array
    {
        $rows = [];

        foreach ($transactions as $transaction) {
            $rows[] = [
                'at' => $transaction->created_at,
                'customer' => $transaction->invoice?->user?->email ?? '—',
                'method' => $transaction->is_credit_transaction
                    ? 'Account credit'
                    : ($transaction->gateway?->name ?? 'Unknown'),
                // The reference's own Description, when this transaction was given one on
                // the Add Transaction form; the synthesised line otherwise — every
                // transaction the daily cron or a webhook creates never had the chance.
                'description' => $notes?->get($transaction->id)
                    ?? 'Invoice Payment (#' . ($transaction->invoice?->number ?: $transaction->invoice_id) . ')',
                'trans' => $transaction->transaction_id ?: null,
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
                'description' => 'Refund (#' . ($refund->invoice?->number ?: $refund->invoice_id) . ')'
                    . ($refund->admin ? ' · by ' . $refund->admin->name : ''),
                'trans' => $refund->reason ? (string) str($refund->reason)->limit(80) : null,
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
