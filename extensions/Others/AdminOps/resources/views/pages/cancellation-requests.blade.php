{{--
    Cancellation Requests, to issue #30's reference screenshot: Search/Filter tab in the
    header band, the Show Open/Completed Requests toggle, records line with Jump to Page,
    and the navy grid — Date, Product/Service, Reason, Type, Cancellation By End.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
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
                        <span class="ao-find-label">Client, Product or Reason</span>
                        <input @nofill type="search" wire:model.live.debounce.500ms="q" placeholder="Client name, email, product or reason">
                    </label>
                </div>
                <button type="submit" class="ao-find-go">Search</button>
            </form>
        @endif

        <div class="ao-sc-toggle">
            <button type="button" class="{{ $tab === 'open' ? 'ao-on' : '' }}" wire:click="$set('tab', 'open')">Show Open Requests</button>
            <button type="button" class="{{ $tab === 'completed' ? 'ao-on' : '' }}" wire:click="$set('tab', 'completed')">Show Completed Requests</button>
        </div>

        <div class="ao-mu-line">
            <span>{{ number_format($rows->count()) }} Records Found, Page 1 of 1</span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product/Service</th>
                    <th>Reason</th>
                    <th>Type</th>
                    <th>Cancellation By End</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->created_at?->format('m/d/Y') }}</td>
                        <td class="ao-mu-left">
                            <a href="{{ \App\Admin\Resources\ServiceResource::getUrl('edit', ['record' => $row->service_id]) }}">
                                {{ $row->service->product?->name ?? '—' }}
                                — {{ trim(($row->service->user->first_name ?? '') . ' ' . ($row->service->user->last_name ?? '')) ?: ($row->service->user->email ?? '') }}
                            </a>
                        </td>
                        <td class="ao-mu-left">{{ $row->reason ?: '—' }}</td>
                        <td>{{ $row->type === 'end_of_period' ? 'End of Billing Period' : 'Immediate' }}</td>
                        <td>{{ $row->type === 'end_of_period' ? ($row->service->expires_at?->format('m/d/Y') ?? '—') : '—' }}</td>
                        <td class="ao-mu-actions">
                            @if ($tab === 'open')
                                <button type="button" class="ao-cp-link" wire:click="confirm({{ $row->id }}, 'accept')">Accept</button>
                                <button type="button" class="ao-mo-delete" title="Refuse — the service goes back to renewing"
                                    wire:click="confirm({{ $row->id }}, 'deny')">
                                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <nav class="ao-mu-pages">
            <button type="button" disabled>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">1</span>
            <button type="button" disabled>Next Page &raquo;</button>
        </nav>

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        @if ($confirmAction === 'accept')
                            <p>Accept this cancellation request?</p>
                            <p>The service will be terminated and its proxies released.</p>
                        @else
                            <p>Refuse this cancellation request?</p>
                            <p>The service goes back to renewing as though it was never made.</p>
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="run">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
