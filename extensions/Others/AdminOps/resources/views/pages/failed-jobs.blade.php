<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
            <button type="button" class="ao-mu-tab" wire:click="retryAll"
                wire:confirm="Queue every failed job for retry?">Retry All</button>
        </div>

        @if ($filter)
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$set('page', 1)">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-fj-dates">Date Range</label>
                        @include('adminops::partials.datepicker', [
                            'model' => 'dates', 'range' => true, 'id' => 'ao-fj-dates',
                            'placeholder' => 'MM/DD/YYYY - MM/DD/YYYY', 'class' => 'ao-of-lg',
                        ])
                        <label class="ao-of-label" for="ao-fj-queue">Queue</label>
                        <span><select @nofill id="ao-fj-queue" class="ao-of-md" wire:model="queue">
                            <option value="">Any</option>
                            @foreach ($queues as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-fj-q">Job or Exception</label>
                        <span><input @nofill id="ao-fj-q" class="ao-of-lg" type="text" wire:model="q"
                            placeholder="Any part of the job name or error"></span>
                        <span class="ao-of-label"></span>
                        <span></span>
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
                    <th>ID</th>
                    <th>Failed</th>
                    <th>Queue</th>
                    <th>Job</th>
                    <th>Reason</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->failed_at?->format('m/d/Y H:i:s') }}</td>
                        <td>{{ $row->queue ?: '—' }}</td>
                        <td class="ao-mu-left">{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\FailedJobs::jobName($row) }}</td>
                        <td class="ao-mu-left">{{ str(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\FailedJobs::reason($row))->limit(90) }}</td>
                        <td class="ao-mu-actions">
                            <button type="button" wire:click="retryJob({{ $row->id }})"
                                wire:confirm="Queue this job for retry?">Retry</button>
                            <button type="button" wire:click="deleteJob({{ $row->id }})"
                                wire:confirm="Delete this failed job? This cannot be undone.">Delete</button>
                            <button type="button" class="ao-ps-plus {{ $expanded === $row->id ? 'ao-on' : '' }}"
                                wire:click="expand({{ $row->id }})">{{ $expanded === $row->id ? '−' : '+' }}</button>
                        </td>
                    </tr>
                    @if ($expanded === $row->id)
                        <tr class="ao-ps-detail">
                            <td colspan="6"><pre class="ao-hl-pre">{{ $row->exception }}</pre></td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="ao-mu-none">No Records Found</td></tr>
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
