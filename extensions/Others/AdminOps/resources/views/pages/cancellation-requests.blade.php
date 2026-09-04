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
            {{-- The reference's Search/Filter panel, field for field: Reason, Domain,
                 Service ID on the left; Client and Type on the right; the Filter button
                 centred below. Domain is the one honestly-dead field — proxy services
                 carry no domain — with the reason on its title. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$refresh">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-cr-reason">Reason</label>
                        <span><input @nofill id="ao-cr-reason" class="ao-of-lg" type="text"
                            wire:model.live.debounce.500ms="reason" placeholder="Words from the reason"></span>
                        <label class="ao-of-label" for="ao-cr-client">Client</label>
                        <span><input @nofill id="ao-cr-client" class="ao-of-lg" type="text"
                            wire:model.live.debounce.500ms="client" placeholder="Start Typing to Search Clients"></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-cr-domain">Domain</label>
                        <span><input id="ao-cr-domain" class="ao-of-md" type="text" disabled
                            placeholder="Not recorded"
                            title="Proxy services carry no domain, so this field cannot filter anything"></span>
                        <label class="ao-of-label" for="ao-cr-type">Type</label>
                        <span><select @nofill id="ao-cr-type" class="ao-of-md" wire:model.live="type">
                            <option value="">Any</option>
                            <option value="immediate">Immediate</option>
                            <option value="end_of_period">End of Billing Period</option>
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-cr-svc">Service ID</label>
                        <span><input @nofill id="ao-cr-svc" class="ao-of-sm" type="text" inputmode="numeric"
                            wire:model.live.debounce.500ms="svc" placeholder="ID"></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Filter</button>
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
                            <a href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::serviceUrl($row->service) }}">
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
