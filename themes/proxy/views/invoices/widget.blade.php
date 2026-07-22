{{-- Invoices widget as a Six-style list group (same $invoices data as the default theme). --}}
<ul class="wf-list">
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
        <li>
            <a href="{{ route('invoices.show', $invoice) }}" wire:navigate>
                <span style="min-width:0">
                    <span class="wf-list-title">{{ $label }}</span>
                    <span class="wf-list-sub">
                        {{ __('invoices.invoice_date') }}: {{ $invoice->created_at->format('d M Y') }}
                    </span>
                </span>
                <span style="display:flex; align-items:center; gap:.6rem; white-space:nowrap">
                    <span class="wf-list-title">{{ $invoice->formattedTotal }}</span>
                    <span class="wf-label {{ $tone }}">{{ ucfirst($invoice->status) }}</span>
                </span>
            </a>
        </li>
    @empty
        <li><div class="wf-empty">{{ __('dashboard.unpaid_invoices') }} &mdash; 0</div></li>
    @endforelse
</ul>
