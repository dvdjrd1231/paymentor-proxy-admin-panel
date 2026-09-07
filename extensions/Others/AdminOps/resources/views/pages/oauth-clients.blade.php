{{--
    OpenID Connect, to the reference's screenshots: the green Generate button, the records
    line with Jump to Page, and the Name / Description / Last Updated grid with Manage on
    each row. No credential is ever printed here.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        @if ($createUrl)
            <div class="ao-gs-actions ao-gs-actions-left">
                <a class="ao-api-generate" href="{{ $createUrl }}">&#10010; Generate New Client API Credentials</a>
            </div>
        @endif

        <div class="ao-mu-line">
            <span>
                {{ number_format($clients->total()) }} Records Found, Page
                {{ $clients->currentPage() }} of {{ max(1, $clients->lastPage()) }}
            </span>
            <span class="ao-mu-line-right">
                <label class="ao-mu-jump">
                    Jump to Page:
                    <select wire:change="jump($event.target.value)">
                        @foreach (range(1, max(1, $clients->lastPage())) as $number)
                            <option value="{{ $number }}" @selected($number === $clients->currentPage())>{{ $number }}</option>
                        @endforeach
                    </select>
                </label>
            </span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr><th>Name</th><th>Description</th><th>Last Updated</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($clients as $client)
                    <tr>
                        <td class="ao-mu-left"><a href="{{ $manageUrl($client) }}">{{ $client->name }}</a></td>
                        <td class="ao-mu-left">{{ $client->description ?: '—' }}</td>
                        <td>{{ $client->updated_at?->format('jS F Y g:i:sA') ?? '—' }}</td>
                        <td class="ao-mu-actions">
                            <a href="{{ $manageUrl($client) }}">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ao-cs-band-foot">
            <span class="ao-cs-band-pages">
                <button type="button" wire:click="jump({{ max(1, $clients->currentPage() - 1) }})"
                    @disabled($clients->onFirstPage())>&laquo; Previous Page</button>
                <button type="button" wire:click="jump({{ $clients->currentPage() + 1 }})"
                    @disabled(!$clients->hasMorePages())>Next Page &raquo;</button>
            </span>
        </div>
    </div>
</x-filament-panels::page>
