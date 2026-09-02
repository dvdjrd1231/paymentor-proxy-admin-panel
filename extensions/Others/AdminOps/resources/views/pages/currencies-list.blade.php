{{--
    Currencies, to issue #46's reference: the navy grid, the update buttons, and the
    Add Additional Currency inline form. Base Conv. Rate shows a dash on purpose —
    Paymenter stores a price per currency, not a conversion rate; rates come from the
    market through the Currency Rates sync.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Currency Code</th>
                    <th>Prefix</th>
                    <th>Suffix</th>
                    <th>Format</th>
                    <th title="Paymenter stores a price per currency rather than a conversion rate; rates come from the market via the sync below">Base Conv. Rate</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($currencies as $entry)
                    @php $currency = $entry['row']; @endphp
                    <tr>
                        <td>{{ $currency->code }}</td>
                        <td>{{ $currency->prefix ?: '—' }}</td>
                        <td>{{ $currency->suffix ?: '—' }}</td>
                        <td>{{ $currency->format }}</td>
                        <td>{{ $currency->code === config('settings.default_currency', 'USD') ? '1.00000' : '—' }}</td>
                        <td class="ao-mu-actions">
                            @if ($entry['edit'])
                                <a href="{{ $entry['edit'] }}" title="Edit currency">
                                    <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ao-gs-actions">
            <button type="button" class="ao-pg-btn" wire:click="updateRates"
                wire:confirm="Pull the latest market rates and rewrite secondary-currency product prices now?">Update Exchange Rates</button>
            <button type="button" class="ao-pg-btn" wire:click="updateRates"
                title="The same sync — Paymenter stores prices per currency, so updating the rate is updating the prices"
                wire:confirm="Pull the latest market rates and rewrite secondary-currency product prices now?">Update Product Prices</button>
        </div>

        <h4 class="ao-ano-heading">Add Additional Currency</h4>
        <form class="ao-anc-card" wire:submit.prevent="addCurrency">
            <label class="ao-anc-row">
                <span>Currency Code</span>
                <span class="ao-anc-field">
                    <input type="text" class="ao-w-25" maxlength="3" wire:model="newCode" placeholder="e.g. BRL" required>
                    <i>eg. USD, GBP, etc...</i>
                </span>
            </label>
            <label class="ao-anc-row">
                <span>Display Name</span>
                <input type="text" class="ao-w-25" wire:model="newName" placeholder="Optional — defaults to the code">
            </label>
            <label class="ao-anc-row">
                <span>Prefix</span>
                <input type="text" class="ao-w-25" wire:model="newPrefix" placeholder="e.g. R$">
            </label>
            <label class="ao-anc-row">
                <span>Suffix</span>
                <input type="text" class="ao-w-25" wire:model="newSuffix" placeholder="Defaults to the code">
            </label>
            <label class="ao-anc-row">
                <span>Format</span>
                <select class="ao-w-25" wire:model="newFormat">
                    @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\CurrenciesList::FORMATS as $format)
                        <option value="{{ $format }}">{{ $format }}</option>
                    @endforeach
                </select>
            </label>
            <div class="ao-pr-center"><button type="submit" class="ao-find-go">Add Currency</button></div>
        </form>
        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif
    </div>
</x-filament-panels::page>
