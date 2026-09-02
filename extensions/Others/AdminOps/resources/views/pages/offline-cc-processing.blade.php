{{--
    Offline Credit Card Processing, to the reference screenshot: the records line, Jump to
    Page, and the navy grid — ID, Client Name, Invoice Date, Due Date, Total, Actions.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-mu-line">
            <span>{{ number_format($invoices->total()) }} Records Found, Page {{ $invoices->currentPage() }} of {{ max(1, $invoices->lastPage()) }}</span>
            <label class="ao-mu-jump">
                Jump to Page:
                <select wire:change="jump($event.target.value)">
                    @foreach (range(1, max(1, $invoices->lastPage())) as $number)
                        <option value="{{ $number }}" @selected($number === $invoices->currentPage())>{{ $number }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client Name</th>
                    <th>Invoice Date</th>
                    <th>Due Date</th>
                    <th>Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->number ?? $invoice->id }}</td>
                        <td class="ao-mu-left">
                            <a href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $invoice->user_id]) }}">
                                {{ trim(($invoice->user->first_name ?? '') . ' ' . ($invoice->user->last_name ?? '')) ?: ($invoice->user->email ?? '—') }}
                            </a>
                        </td>
                        <td>{{ $invoice->created_at?->format('m/d/Y') }}</td>
                        <td>{{ $invoice->due_at?->format('m/d/Y') }}</td>
                        <td>${{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency_code }}</td>
                        <td class="ao-mu-actions">
                            <a class="ao-cp-link" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\AddTransaction::getUrl(['invoiceId' => $invoice->id]) }}">
                                Attempt Charge
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $invoices->currentPage() - 1 }})"
                @disabled($invoices->onFirstPage())>&laquo; Previous Page</button>
            <button type="button" wire:click="jump({{ $invoices->currentPage() + 1 }})"
                @disabled(!$invoices->hasMorePages())>Next Page &raquo;</button>
        </nav>
    </div>
</x-filament-panels::page>
