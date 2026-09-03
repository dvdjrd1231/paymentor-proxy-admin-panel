{{--
    Quotes, to the live reference: ID, Subject, Client Name, Stage, Total, Valid Until,
    Last Modified, with its Valid and Expired views.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            @foreach (['all' => 'List All Quotes', 'valid' => 'Valid', 'expired' => 'Expired'] as $key => $label)
                <button type="button" class="ao-mu-tab {{ $tab === $key ? 'ao-on' : '' }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
            @endforeach
            <a class="ao-mu-tab" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\CreateQuote::getUrl() }}">Create New Quote</a>
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
        </div>

        @if ($filter)
            {{-- The reference's Search/Filter panel — the same striped rows as Manage Orders'. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$refresh">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-ql-q">Subject / Client</label>
                        <span><input @nofill id="ao-ql-q" class="ao-of-lg" type="text"
                            wire:model.live.debounce.500ms="q" placeholder="Subject, client name or email"></span>
                        <label class="ao-of-label" for="ao-ql-stage">Stage</label>
                        <span><select @nofill id="ao-ql-stage" class="ao-of-sm" wire:model.live="stage">
                            <option value="">Any</option>
                            @foreach ($stages as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Search</button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>{{ number_format($quotes->count()) }} Records Found, Page 1 of 1</span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>ID</th><th>Subject</th><th>Client Name</th><th>Stage</th>
                    <th>Total</th><th>Valid Until</th><th>Last Modified &#9662;</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotes as $quote)
                    @php $open = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\CreateQuote::getUrl(['record' => $quote->id]); @endphp
                    <tr>
                        <td><a href="{{ $open }}">{{ $quote->id }}</a></td>
                        <td class="ao-mu-left"><a href="{{ $open }}">{{ $quote->subject }}</a></td>
                        <td>
                            @if ($quote->user_id)
                                <a href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $quote->user_id]) }}">
                                    {{ trim(($quote->user->first_name ?? '') . ' ' . ($quote->user->last_name ?? '')) ?: ($quote->user->email ?? '—') }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $stages[$quote->status] ?? ucfirst($quote->status) }}</td>
                        <td>${{ number_format($quote->total(), 2) }} {{ $quote->currency_code }}</td>
                        <td>{{ $quote->valid_until?->format('m/d/Y') ?? '—' }}</td>
                        <td>{{ $quote->updated_at?->format('m/d/Y H:i') }}</td>
                        <td class="ao-mu-actions">
                            <a class="ao-cp-link" href="{{ $open }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <nav class="ao-mu-pages">
            <button type="button" disabled>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">1</span>
            <button type="button" disabled>Next Page &raquo;</button>
        </nav>
    </div>
</x-filament-panels::page>
