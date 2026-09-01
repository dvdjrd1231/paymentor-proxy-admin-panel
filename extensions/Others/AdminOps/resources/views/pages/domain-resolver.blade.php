{{-- Domain Resolver: the hostname box and its live DNS records. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-dns">
        <form class="ao-find" autocomplete="off" wire:submit.prevent="resolve">
            <div class="ao-find-fields">
                <label class="ao-find-field ao-find-grow">
                    <span>Hostname</span>
                    <input @nofill type="text" wire:model="host" placeholder="example.com" required>
                </label>
            </div>
            <button type="submit" class="ao-find-go" wire:loading.attr="disabled">Resolve</button>
        </form>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if ($searched)
            <table class="ao-mu-grid">
                <thead>
                    <tr><th>Type</th><th>Host</th><th>Value</th></tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record[0] }}</td>
                            <td>{{ $record[1] }}</td>
                            <td class="ao-mu-left">{{ $record[2] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ao-mu-none">No DNS records found</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>
