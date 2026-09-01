{{-- Affiliates, to the reference screenshot: records line, Jump to Page, the navy grid. --}}
<x-filament-panels::page>
    <div class="ao-mu">
        <button type="button" class="ao-mu-tab {{ $this->filter ? 'ao-on' : '' }}" wire:click="toggleFilter">
            Search/Filter
        </button>

        @if ($this->filter)
            <form class="ao-find" autocomplete="off" wire:submit.prevent="search">
                <span class="ao-find-glass" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="18" height="18">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                </span>
                <div class="ao-find-fields">
                    <label class="ao-find-field ao-find-grow">
                        <span class="ao-find-label">Client Name/Email</span>
                        <input @nofill type="search" wire:model="q" placeholder="Client name or email">
                    </label>
                </div>
                <button type="submit" class="ao-find-go">Search</button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>{{ number_format($affiliates->total()) }} Records Found, Page {{ $affiliates->currentPage() }} of {{ max(1, $affiliates->lastPage()) }}</span>
            <label class="ao-mu-jump">
                Jump to Page:
                <select wire:change="jump($event.target.value)">
                    @foreach (range(1, max(1, $affiliates->lastPage())) as $number)
                        <option value="{{ $number }}" @selected($number === $affiliates->currentPage())>{{ $number }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox" data-ao-check-all></th>
                    <th>ID</th>
                    <th>Signup Date</th>
                    <th>Client Name &#9650;</th>
                    <th>Visitors Referred</th>
                    <th>Signups</th>
                    <th>Balance</th>
                    <th>Withdrawn</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($affiliates as $affiliate)
                    @php
                        $summary = $affiliate->user_id
                            ? \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $affiliate->user_id])
                            : null;
                        $edit = \Paymenter\Extensions\Others\Affiliates\Admin\Resources\AffiliateResource::getUrl('edit', ['record' => $affiliate->id]);
                        $name = trim(($affiliate->user->first_name ?? '') . ' ' . ($affiliate->user->last_name ?? '')) ?: ($affiliate->user->email ?? '—');
                    @endphp
                    <tr>
                        <td class="ao-mu-check"><input type="checkbox" data-ao-check value="{{ $affiliate->user?->email }}"></td>
                        <td>{{ $affiliate->id }}</td>
                        <td>{{ $affiliate->created_at?->format('m/d/Y') }}</td>
                        <td class="ao-mu-left">
                            @if ($summary)
                                <a href="{{ $summary }}">{{ $name }}</a>
                            @else
                                {{ $name }}
                            @endif
                        </td>
                        <td>{{ number_format($affiliate->visitors) }}</td>
                        <td>{{ number_format($affiliate->signups) }}</td>
                        <td>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ManageAffiliates::balance($affiliate) }}</td>
                        {{-- No withdrawal ledger exists, so zero withdrawn is the truth. --}}
                        <td>$0.00 USD</td>
                        <td class="ao-mu-actions ao-mu-iconpair">
                            {{-- The reference's pair: a blue report icon and a red one. The
                                 report opens the affiliate's client profile; the red opens
                                 the affiliate record, where disabling and deleting live. --}}
                            <a href="{{ $summary ?? $edit }}" title="View client profile">
                                <x-filament::icon icon="ri-file-chart-line" class="ao-mu-cell-icon" />
                            </a>
                            <a href="{{ $edit }}" title="Manage affiliate">
                                <x-filament::icon icon="ri-indeterminate-circle-line" class="ao-mu-cell-icon ao-mu-icon-red" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $affiliates->currentPage() - 1 }})"
                @disabled($affiliates->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $affiliates->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $affiliates->currentPage() + 1 }})"
                @disabled(!$affiliates->hasMorePages())>Next Page &raquo;</button>
        </nav>
    </div>
</x-filament-panels::page>
