{{-- Refund Requests (the reference's Disputes), to issue #16. --}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'all' => 'All'] as $key => $label)
                <button type="button" class="ao-mu-tab {{ $tab === $key ? 'ao-on' : '' }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
            @endforeach
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
        </div>

        @if ($filter)
            <form class="ao-find" autocomplete="off" wire:submit.prevent="$refresh">
                <span class="ao-find-glass" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="18" height="18">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                </span>
                <div class="ao-find-fields">
                    <label class="ao-find-field ao-find-grow">
                        <span class="ao-find-label">Client or Reason</span>
                        <input @nofill type="search" wire:model.live.debounce.500ms="q" placeholder="Client name, email or reason">
                    </label>
                </div>
                <button type="submit" class="ao-find-go">Search</button>
            </form>
        @endif

        <div class="ao-mu-line"><span>{{ number_format($rows->count()) }} Records Found</span></div>

        <table class="ao-mu-grid">
            <thead>
                <tr><th>ID</th><th>Client</th><th>Invoice</th><th>Amount</th><th>Reason</th><th>Status</th><th>Requested</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $decide = null;
                        try {
                            $decide = \Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundRequestResource::getUrl('edit', ['record' => $row->id]);
                        } catch (\Throwable $e) {
                            try {
                                $decide = \Paymenter\Extensions\Others\InvoiceOps\Admin\Resources\RefundRequestResource::getUrl('index');
                            } catch (\Throwable $e) {
                            }
                        }
                    @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>
                            <a href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $row->user_id]) }}">
                                {{ trim(($row->user->first_name ?? '') . ' ' . ($row->user->last_name ?? '')) ?: ($row->user->email ?? '—') }}
                            </a>
                        </td>
                        <td>{{ $row->invoice->number ?? $row->invoice_id }}</td>
                        <td>{{ $row->amount !== null ? '$' . number_format((float) $row->amount, 2) : 'Full' }}</td>
                        <td class="ao-mu-left">{{ str($row->reason)->limit(60) }}</td>
                        <td>
                            <span class="{{ ['pending' => 'ao-st-answered', 'approved' => 'ao-st-open', 'denied' => 'ao-st-closed'][$row->status] ?? '' }}">
                                {{ ucfirst($row->status) }}
                            </span>
                        </td>
                        <td>{{ $row->created_at?->format('m/d/Y H:i') }}</td>
                        <td class="ao-mu-actions">
                            @if ($decide)
                                <a class="ao-cp-link" href="{{ $decide }}">{{ $row->status === 'pending' ? 'Decide' : 'View' }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
