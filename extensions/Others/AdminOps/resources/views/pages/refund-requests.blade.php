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
            {{-- The reference's Search/Filter panel — the same striped rows as Manage Orders'. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$refresh">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-rr-q">Client or Reason</label>
                        <span><input @nofill id="ao-rr-q" class="ao-of-lg" type="text"
                            wire:model.live.debounce.500ms="q" placeholder="Client name, email or reason"></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Search</button>
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
