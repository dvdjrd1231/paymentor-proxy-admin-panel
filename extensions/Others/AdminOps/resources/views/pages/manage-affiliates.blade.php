{{-- Affiliates, to the reference screenshot: records line, Jump to Page, the navy grid. --}}
<x-filament-panels::page>
    <div class="ao-mu">
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
                        {{-- No withdrawal ledger exists; a perpetual $0.00 would be an answer nobody recorded. --}}
                        <td>—</td>
                        <td class="ao-mu-actions">
                            <a href="{{ $edit }}" title="Edit affiliate">
                                <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
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
