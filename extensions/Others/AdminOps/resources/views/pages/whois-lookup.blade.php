{{-- WHOIS Lookup: the domain box and the registry's raw answer. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-who">
        <form class="ao-find" wire:submit.prevent="lookup">
            <div class="ao-find-fields">
                <label class="ao-find-field ao-find-grow">
                    <span>Domain</span>
                    <input type="text" wire:model="domain" placeholder="example.com" required>
                </label>
            </div>
            <button type="submit" class="ao-find-go" wire:loading.attr="disabled">Lookup</button>
        </form>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if ($result !== '')
            <pre class="ao-who-result">{{ $result }}</pre>
        @endif
    </div>
</x-filament-panels::page>
