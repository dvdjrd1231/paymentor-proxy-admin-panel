{{-- Gateway Log, to issue #17: the outbound calls, each row unfolding its context. --}}
<x-filament-panels::page>
    <div class="ao-mu">
        <form class="ao-find" autocomplete="off" wire:submit.prevent="$set('page', 1)">
            <div class="ao-find-fields">
                <label class="ao-find-field ao-find-grow">
                    <span>Search</span>
                    <input type="text" wire:model="q" placeholder="Type or request contents">
                </label>
            </div>
            <button type="submit" class="ao-find-go">Search</button>
        </form>

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
                <tr><th>ID</th><th>Date</th><th>Type</th><th>Summary</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $context = is_array($row->context) ? $row->context : (array) json_decode($row->context ?? '[]', true); @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->created_at?->format('m/d/Y H:i:s') }}</td>
                        <td class="ao-mu-left">{{ $row->type }}</td>
                        <td class="ao-mu-left">{{ str(json_encode($context))->limit(90) }}</td>
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
