{{--
    The reference's currency editor. Core's own /admin/currencies/{code}/edit redirects
    here, so this carries everything that form did plus the field the reference is built
    around — Base Conv. Rate.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <a class="ao-mu-tab"
                href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\CurrenciesList::getUrl() }}">&laquo; Back to Currencies</a>
        </div>

        <form class="ao-anc-card" wire:submit.prevent="save">
            <label class="ao-anc-row">
                <span>Currency Code</span>
                <span class="ao-anc-field">
                    <input type="text" class="ao-w-25" value="{{ $this->currency->code }}" disabled>
                    <i>The code cannot change once money has been priced in it.</i>
                </span>
            </label>
            <label class="ao-anc-row">
                <span>Display Name</span>
                <input type="text" class="ao-w-25" wire:model="name" required>
            </label>
            <label class="ao-anc-row">
                <span>Prefix</span>
                <input type="text" class="ao-w-25" wire:model="prefix" placeholder="e.g. R$">
            </label>
            <label class="ao-anc-row">
                <span>Suffix</span>
                <input type="text" class="ao-w-25" wire:model="suffix">
            </label>
            <label class="ao-anc-row">
                <span>Format</span>
                <select class="ao-w-25" wire:model="format">
                    @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\CurrenciesList::FORMATS as $format)
                        <option value="{{ $format }}">{{ $format }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ao-anc-row">
                <span>Base Conv. Rate</span>
                <span class="ao-anc-field">
                    @if ($this->isBase())
                        <input type="text" class="ao-w-25" value="1.00000" disabled>
                        <i>This is the default currency — everything is priced against it, so its rate is 1.</i>
                    @else
                        <input type="text" class="ao-w-25" wire:model="rate" placeholder="e.g. 5.42000">
                        <i>How many {{ $this->currency->code }} one
                            {{ config('settings.default_currency') }} buys. Set it here, then use
                            <strong>Update Product Prices</strong> on the Currencies screen to reprice from it —
                            or leave it to <strong>Update Exchange Rates</strong> to fill in from the market.</i>
                    @endif
                </span>
            </label>

            <div class="ao-pr-center"><button type="submit" class="ao-find-go">Save Changes</button></div>
        </form>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif

        @unless ($this->isBase())
            <div class="ao-gs-actions">
                <button type="button" class="ao-pg-btn" wire:click="delete"
                    wire:confirm="Delete {{ $this->currency->code }}? This is only possible while nothing is priced in it.">Delete Currency</button>
            </div>
        @endunless
    </div>
</x-filament-panels::page>
