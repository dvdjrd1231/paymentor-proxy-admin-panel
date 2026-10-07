{{-- My Invoices — WHMCS "Six" style, with the billing rail beside it.

     The list is queried here rather than taken from core's $invoices paginator, because the
     rail's Status entries filter it and core's component accepts no status (Leandro,
     2026-10-07). Same query core runs, plus the filter and a count line. --}}
@php
    use App\Models\Invoice;

    // 'unpaid' is the reference's word for it; the column says 'pending'.
    $invStatuses = [
        'paid' => Invoice::STATUS_PAID,
        'unpaid' => Invoice::STATUS_PENDING,
        'cancelled' => Invoice::STATUS_CANCELLED,
        'refunded' => 'refunded',
    ];

    $invFilter = request('status');
    $invQuery = Auth::user()->invoices()->with(['user', 'snapshot', 'items'])->orderBy('id', 'desc');

    if (isset($invStatuses[$invFilter])) {
        $invQuery->where('status', $invStatuses[$invFilter]);
    } else {
        $invFilter = null;
    }

    $invRows = $invQuery->paginate(config('settings.pagination'))->withQueryString();
    $invTotal = Auth::user()->invoices()->count();
@endphp

<div class="wf-page">
    <div class="wf-layout">
        <x-billing-rail active="invoices" />

        <div>
            <div class="wf-title">
                <h1>{{ __('theme.my_invoices') }}</h1>
                <span>{{ __('theme.my_invoices_subtitle') }}</span>
            </div>
            <hr class="wf-title-rule">

            <div class="wf-crumb">
                <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
                <span>/</span><a href="{{ route('dashboard') }}" wire:navigate>{{ __('theme.client_area') }}</a>
                <span>/</span>{{ __('theme.my_invoices') }}
            </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span>{{ __('theme.showing_entries', [
                'from' => $invRows->total() ? $invRows->firstItem() : 0,
                'to' => $invRows->total() ? $invRows->lastItem() : 0,
                'total' => $invRows->total(),
            ]) }}@if ($invFilter) {{ __('theme.filtered_from', ['total' => $invTotal]) }}@endif</span>
        </div>
        <div class="wf-table-wrap">
            <table class="wf-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('invoices.invoice_date') }}</th>
                        <th>{{ __('invoices.total') ?? 'Total' }}</th>
                        <th style="text-align:end">{{ __('invoices.status') ?? 'Status' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invRows as $invoice)
                        @php
                            $tone = match ($invoice->status) {
                                'paid' => 'wf-label--success',
                                'cancelled' => 'wf-label--info',
                                'pending' => 'wf-label--warning',
                                default => '',
                            };
                            $label = !$invoice->number && config('settings.invoice_proforma', false)
                                ? __('invoices.proforma_invoice', ['id' => $invoice->id])
                                : __('invoices.invoice', ['id' => $invoice->number]);
                        @endphp
                        <tr>
                            <td><a href="{{ route('invoices.show', $invoice) }}" wire:navigate>{{ $label }}</a></td>
                            <td>{{ $invoice->created_at->format('d M Y') }}</td>
                            <td>{{ $invoice->formattedTotal }}</td>
                            {{-- The badge opens the invoice, as it does on the reference —
                                 it was the one thing in the row that looked clickable and
                                 was not (Leandro, 2026-10-07). --}}
                            <td style="text-align:end">
                                <a class="wf-label {{ $tone }}" href="{{ route('invoices.show', $invoice) }}" wire:navigate>
                                    {{ ucfirst($invoice->status) }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="wf-empty">{{ __('invoices.no_invoices') }}</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

            {{ $invRows->links() }}
        </div>
    </div>
</div>
