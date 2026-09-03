{{--
    Fixed Terms, carried to the new window standard: the Search/Filter band, the "Still
    running" toggle where the reference's own filter chip sat, the navy grid, and the
    Extend/History actions as this skin's own form/list modals.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
        </div>

        @if ($filter)
            <form class="ao-find" autocomplete="off" wire:submit.prevent="search">
                <span class="ao-find-glass" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="18" height="18">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                </span>
                <div class="ao-find-fields">
                    <label class="ao-find-field ao-find-wide">
                        <span class="ao-find-label">Service or Customer</span>
                        <input @nofill type="search" wire:model="q" placeholder="Service #, name or email">
                    </label>
                    <label class="ao-find-field">
                        <span class="ao-find-label">Ended As</span>
                        <select @nofill wire:model="outcome">
                            <option value="">Any</option>
                            <option value="terminated">Terminated</option>
                            <option value="suspended">Suspended</option>
                            <option value="released">Released</option>
                        </select>
                    </label>
                </div>
                <button type="submit" class="ao-find-go">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="13" height="13" aria-hidden="true">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                    Search
                </button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>
                {{ number_format($terms->total()) }} Records Found{{ $terms->total() > 0 ? ', Showing ' . number_format($terms->firstItem()) . ' to ' . number_format($terms->lastItem()) : '' }}
            </span>
            <span class="ao-mu-line-right">
                {{-- Where the reference's own "Still running ×" filter chip sat — the
                     same default-on filter, our own pill. --}}
                <button type="button" class="ao-mu-toggle {{ $openOnly ? 'ao-on' : '' }}" wire:click="toggleOpenOnly">
                    <i>{{ $openOnly ? 'ON' : 'OFF' }}</i>
                    Still Running
                </button>
                <label class="ao-mu-jump">
                    Jump to Page:
                    <select wire:change="jump($event.target.value)">
                        @foreach (range(1, max(1, $terms->lastPage())) as $number)
                            <option value="{{ $number }}" @selected($number === $terms->currentPage())>{{ $number }}</option>
                        @endforeach
                    </select>
                </label>
            </span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Bought</th>
                    <th>Extended</th>
                    <th>Started</th>
                    <th>Ends</th>
                    <th>Remaining</th>
                    <th>Ended As</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($terms as $term)
                    @php $url = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\FixedTerms::serviceUrl($term); @endphp
                    <tr>
                        <td>
                            @if ($url)
                                <a href="{{ $url }}">#{{ $term->service_id }}</a>
                            @else
                                #{{ $term->service_id }}
                            @endif
                        </td>
                        <td class="ao-mu-left">{{ $term->service?->user?->email ?? '—' }}</td>
                        <td class="ao-mu-left">{{ $term->service?->product?->name ?? '—' }}</td>
                        <td>{{ $term->hours }} h</td>
                        <td>{{ $term->extendedHours() === 0 ? '—' : '+' . $term->extendedHours() . ' h' }}</td>
                        <td>{{ $term->started_at?->format('m/d/Y H:i') }}</td>
                        <td>{{ $term->ends_at?->format('m/d/Y H:i') }}</td>
                        <td>
                            <span class="ao-mu-status {{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\FixedTerms::remainingColor($term) }}">
                                {{ $term->remainingForHumans() }}
                            </span>
                        </td>
                        <td>{{ $term->outcome ? ucfirst($term->outcome) : '—' }}</td>
                        <td class="ao-mu-actions ao-mu-iconpair">
                            @if ($canExtend)
                                <button type="button" title="Extend this term" wire:click="openExtend({{ $term->id }})">
                                    <x-filament::icon icon="ri-time-line" class="ao-mu-cell-icon" />
                                </button>
                            @endif
                            @if ($term->extensions->isNotEmpty())
                                <button type="button" title="Extensions granted" wire:click="openHistory({{ $term->id }})">
                                    <x-filament::icon icon="ri-history-line" class="ao-mu-cell-icon" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Extend: a form modal, the reference's own shape — navy title bar,
             right-aligned labels, Close/Save footer. --}}
        @if ($extending)
            <div class="ao-mud-overlay" wire:click.self="closeExtend">
                <div class="ao-mud" role="dialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Extend service #{{ $extending }}
                        <button type="button" wire:click="closeExtend" aria-label="Close">&times;</button>
                    </div>
                    <form class="ao-mud-body" wire:submit.prevent="saveExtend">
                        <label class="ao-mud-row">
                            <span>Hours to Add</span>
                            <input type="number" min="1" max="720" wire:model="extend.hours" required>
                        </label>
                        <label class="ao-mud-row ao-mud-row-top">
                            <span>Reason</span>
                            <textarea rows="3" wire:model="extend.reason" placeholder="The maintenance window, the outage, the ticket number" required></textarea>
                        </label>
                        <p class="ao-gs-empty">
                            Added to the end of the term, not to the time now — an outage that cost six
                            hours costs six hours wherever in the term it happened.
                        </p>

                        @if ($errors->any())
                            <ul class="ao-anc-errors">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="ao-mud-foot ao-mud-foot-only-right">
                            <span class="ao-mud-foot-right">
                                <button type="button" class="ao-mud-close" wire:click="closeExtend">Close</button>
                                <button type="submit" class="ao-mud-save">Save</button>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- History: read-only, the extensions already granted for this term. --}}
        @if ($viewingHistory)
            <div class="ao-mud-overlay" wire:click.self="closeHistory">
                <div class="ao-mud" role="dialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Extensions granted — service #{{ $historyTerm?->service_id }}
                        <button type="button" wire:click="closeHistory" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-body">
                        <table class="ao-mu-grid">
                            <thead>
                                <tr><th>Date</th><th>Hours</th><th>Reason</th><th>Granted By</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($history ?? [] as $entry)
                                    <tr>
                                        <td>{{ $entry->created_at?->format('m/d/Y H:i') }}</td>
                                        <td>+{{ $entry->hours }} h</td>
                                        <td class="ao-mu-left">{{ $entry->reason }}</td>
                                        <td class="ao-mu-left">{{ $entry->admin?->email ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="ao-mu-none">No Records Found</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="closeHistory">Close</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
