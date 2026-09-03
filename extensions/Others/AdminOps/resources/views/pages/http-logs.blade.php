{{-- Gateway Transaction Log, to the reference: the Search/Filter panel (Date Range,
     Debug Data, Gateway, Result) over the outbound calls, each row unfolding its context. --}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
        </div>

        @if ($filter)
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$set('page', 1)">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-gl-dates">Date Range</label>
                        @include('adminops::partials.datepicker', [
                            'model' => 'dates', 'range' => true, 'id' => 'ao-gl-dates',
                            'placeholder' => 'MM/DD/YYYY - MM/DD/YYYY', 'class' => 'ao-of-lg',
                        ])
                        <label class="ao-of-label" for="ao-gl-gateway">Gateway</label>
                        <span><select @nofill id="ao-gl-gateway" class="ao-of-sm" wire:model="gateway">
                            <option value="">Any</option>
                            @foreach ($gateways as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-gl-q">Debug Data</label>
                        <span><input @nofill id="ao-gl-q" class="ao-of-lg" type="text"
                            wire:model="q" placeholder="Type or request contents"></span>
                        <label class="ao-of-label" for="ao-gl-result">Result</label>
                        <span><select id="ao-gl-result" class="ao-of-sm" disabled
                            title="The debug log records no outcome flag, so there is no result to filter by">
                            <option>Any</option>
                        </select></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Filter</button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>{{ number_format($rows->total()) }} Records Found{{ $rows->total() > 0 ? ', Showing ' . number_format($rows->firstItem()) . ' to ' . number_format($rows->lastItem()) : '' }}</span>
            <label class="ao-mu-jump">
                Jump to Page:
                <select wire:change="jump($event.target.value)">
                    @foreach (range(1, max(1, $rows->lastPage())) as $number)
                        <option value="{{ $number }}" @selected($number === $rows->currentPage())>{{ $number }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Gateway</th>
                    <th>Debug Data</th>
                    <th title="The debug log records no outcome flag; every row's honest answer is a dash">Result</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $context = is_array($row->context) ? $row->context : (array) json_decode($row->context ?? '[]', true); @endphp
                    <tr>
                        <td>{{ $row->created_at?->format('m/d/Y H:i:s') }}</td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\HttpLogs::gatewayOf($row, $gateways) }}</td>
                        <td class="ao-mu-left">{{ $row->type }} · {{ str(json_encode($context))->limit(80) }}</td>
                        <td>—</td>
                        <td class="ao-mu-actions">
                            <button type="button" class="ao-ps-plus {{ $expanded === $row->id ? 'ao-on' : '' }}"
                                wire:click="expand({{ $row->id }})">{{ $expanded === $row->id ? '−' : '+' }}</button>
                        </td>
                    </tr>
                    @if ($expanded === $row->id)
                        <tr class="ao-ps-detail">
                            <td colspan="5"><pre class="ao-hl-pre">{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $rows->currentPage() - 1 }})" @disabled($rows->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $rows->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $rows->currentPage() + 1 }})" @disabled(!$rows->hasMorePages())>Next Page &raquo;</button>
        </nav>
    </div>
</x-filament-panels::page>
