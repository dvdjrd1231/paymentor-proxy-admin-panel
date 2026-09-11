@php use Paymenter\Extensions\Others\AdminOps\Admin\Pages\ErrorLog; @endphp
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
            @if ($canClear)
                <button type="button" class="ao-mu-tab" wire:click="$set('confirmingClear', true)">Clear Log</button>
            @endif
        </div>

        @if ($filter)
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$set('page', 1)">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-el-dates">Date Range</label>
                        @include('adminops::partials.datepicker', [
                            'model' => 'dates', 'range' => true, 'id' => 'ao-el-dates',
                            'placeholder' => 'MM/DD/YYYY - MM/DD/YYYY', 'class' => 'ao-of-lg',
                        ])
                        <label class="ao-of-label" for="ao-el-q">Message or File</label>
                        <span><input @nofill id="ao-el-q" class="ao-of-lg" type="text" wire:model="q"
                            placeholder="Any part of the message, file or trace"></span>
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
                    <th>Message</th>
                    <th>File</th>
                    <th>Line</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->created_at?->format('m/d/Y H:i:s') }}</td>
                        <td class="ao-mu-left">{{ str(ErrorLog::field($row, 'message'))->limit(90) ?: '—' }}</td>
                        <td class="ao-mu-left">{{ str(ErrorLog::field($row, 'file'))->limit(60) ?: '—' }}</td>
                        <td>{{ ErrorLog::field($row, 'line') ?: '—' }}</td>
                        <td class="ao-mu-actions">
                            <button type="button" class="ao-ps-plus {{ $expanded === $row->id ? 'ao-on' : '' }}"
                                wire:click="expand({{ $row->id }})">{{ $expanded === $row->id ? '−' : '+' }}</button>
                        </td>
                    </tr>
                    @if ($expanded === $row->id)
                        <tr class="ao-ps-detail">
                            <td colspan="5">
                                <pre class="ao-hl-pre">{{ ErrorLog::field($row, 'trace') ?: json_encode(ErrorLog::context($row), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </td>
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

        @if ($confirmingClear)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingClear', false)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingClear', false)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Are you sure you wish to clear the error log?</p>
                        <p>Every entry is deleted. This cannot be undone.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingClear', false)">Cancel</button>
                            <button type="button" class="ao-mud-save" wire:click="clearLog">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
