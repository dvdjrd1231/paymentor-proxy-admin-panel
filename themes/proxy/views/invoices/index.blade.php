{{-- Invoices list — WHMCS "Six" style table in a panel. Same $invoices paginator. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('navigation.invoices') }}</h1>
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('navigation.invoices') }}</div>
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
                    @forelse ($invoices as $invoice)
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
                            <td style="text-align:end"><span class="wf-label {{ $tone }}">{{ ucfirst($invoice->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="wf-empty">{{ __('invoices.no_invoices') }}</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $invoices->links() }}
</div>
