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

        <form class="ao-find ao-of" wire:submit.prevent="save">
            <div class="ao-of-rows">
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-code">Currency Code</label>
                    <span class="ao-of-stack">
                        <input id="ao-cur-code" class="ao-of-sm" type="text" value="{{ $this->currency->code }}" disabled
                            title="The code cannot change once money has been priced in it">
                        <i class="ao-of-note-dim">eg. USD, GBP, etc...</i>
                    </span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-prefix">Prefix</label>
                    <span><input @nofill id="ao-cur-prefix" class="ao-of-sm" type="text" wire:model="prefix"></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-suffix">Suffix</label>
                    <span><input @nofill id="ao-cur-suffix" class="ao-of-sm" type="text" wire:model="suffix"></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-format">Format</label>
                    <span><select @nofill id="ao-cur-format" class="ao-of-md" wire:model="format">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\CurrenciesList::FORMATS as $format)
                            <option value="{{ $format }}">{{ $format }}</option>
                        @endforeach
                    </select></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-rate">Base Conv. Rate</label>
                    <span class="ao-of-stack">
                        @if ($this->isBase())
                            <input id="ao-cur-rate" class="ao-of-sm" type="text" value="1.00000" disabled
                                title="This is the default currency — everything is priced against it, so its rate is 1">
                        @else
                            <input @nofill id="ao-cur-rate" class="ao-of-sm" type="text" wire:model="rate">
                        @endif
                        <i class="ao-of-note-dim">The current rate to convert to base currency</i>
                    </span>
                </div>
                @unless ($this->isBase())
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Update Pricing</span>
                        <label class="ao-of-check">
                            <input type="checkbox" wire:model="updatePricing">
                            Check to recalculate prices for this currency using the conversion rate
                        </label>
                    </div>
                @endunless
            </div>
            <div class="ao-of-buttons">
                <button type="submit" class="ao-find-go">&#128190; Save Changes</button>
                <a class="ao-gs-cancel" href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\CurrenciesList::getUrl() }}">Cancel Changes</a>
            </div>
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
