{{--
    The Module Queue in the panel's standard window: Search/Filter band, records line,
    navy grid, and the "Are you sure?" before a retry calls the panel API.
--}}
<x-filament-panels::page>
    <div class="ao-mu" x-data="{ filter: @js($filter) }">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab" :class="{ 'ao-on': filter }"
                @click="filter = !filter">Search/Filter</button>
        </div>

        <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$refresh"
            x-show="filter" x-cloak>
            <div class="ao-of-rows">
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-pq-q">Module or Error</label>
                    <span><input @nofill id="ao-pq-q" class="ao-of-lg" type="text"
                        wire:model.live.debounce.500ms="q" placeholder="Module, customer email or words from the error"></span>
                    <label class="ao-of-label" for="ao-pq-status">Status</label>
                    <span><select @nofill id="ao-pq-status" class="ao-of-md" wire:model.live="status">
                        <option value="">Any</option>
                        <option value="failed">Failed</option>
                        <option value="succeeded">Resolved</option>
                    </select></span>
                </div>
                <div class="ao-of-row">
                    <label class="ao-of-label" for="ao-pq-action">Action</label>
                    <span><select @nofill id="ao-pq-action" class="ao-of-md" wire:model.live="action">
                        <option value="">Any</option>
                        @foreach ($actions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select></span>
                </div>
            </div>
            <div class="ao-of-buttons">
                <button type="submit" class="ao-of-go">Filter</button>
            </div>
        </form>

        <div class="ao-mu-line"><span>{{ number_format($rows->count()) }} Records Found, Page 1 of 1</span></div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Customer</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Last error</th>
                    <th>Last attempt</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            @php
                                $serviceUrl = null;
                                if ($row->service) {
                                    try {
                                        $serviceUrl = class_exists(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::class)
                                            ? \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::serviceUrl($row->service)
                                            : \App\Admin\Resources\ServiceResource::getUrl('edit', ['record' => $row->service_id]);
                                    } catch (\Throwable $e) {
                                    }
                                }
                            @endphp
                            @if ($serviceUrl)
                                <a href="{{ $serviceUrl }}">#{{ $row->service_id }}</a>
                            @else
                                #{{ $row->service_id }}
                            @endif
                        </td>
                        <td class="ao-mu-left">{{ $row->service?->user?->email ?: '—' }}</td>
                        <td>{{ $row->extension }}</td>
                        <td>{{ $actions[$row->action] ?? ucfirst($row->action) }}</td>
                        <td>
                            <span class="ao-mu-status {{ $row->isFailed() ? 'ao-mu-st-cancelled' : 'ao-mu-st-active' }}">
                                {{ $row->isFailed() ? 'Failed' : 'Resolved' }}
                            </span>
                        </td>
                        <td>{{ $row->attempts }}</td>
                        <td class="ao-mu-left" title="{{ $row->error }}">{{ str($row->error)->limit(90) ?: '—' }}</td>
                        <td>{{ $row->last_attempt_at?->format('m/d/Y H:i') ?: '—' }}</td>
                        <td class="ao-mu-actions">
                            {{-- Only a failed lifecycle action can be re-run; a callback row is
                                 a record of something that already happened. --}}
                            @if ($row->isFailed() && $row->retryMethod() !== null)
                                <button type="button" class="ao-cp-link" wire:click="confirm({{ $row->id }})">Retry</button>
                            @endif
                            <button type="button" class="ao-cp-link" wire:click="deleteRow({{ $row->id }})">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Re-run this operation against the panel?</p>
                        <p>The module is called for real, against the live API.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runRetry">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
