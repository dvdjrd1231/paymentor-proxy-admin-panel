{{--
    Currencies, to issue #46's reference: the navy grid, the update buttons, and the
    Add Additional Currency inline form. Base Conv. Rate is a real stored column since
    2026-09-07 — Update Exchange Rates fills it from the market, an admin can set it by
    hand in the editor, and Update Product Prices reprices from whatever it holds.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <p class="ao-cp-note">You can sell in different currencies concurrently by setting them up below. Customers who visit your site can then choose to shop in their local currency.</p>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Currency Code</th>
                    <th>Prefix</th>
                    <th>Suffix</th>
                    <th>Format</th>
                    <th title="How many units of this currency one unit of {{ $baseCode }} buys">Base Conv. Rate</th>
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
                        <td>
                            @if ($currency->code === $baseCode)
                                1.00000
                            @elseif ($currency->base_conv_rate !== null)
                                {{ number_format((float) $currency->base_conv_rate, 5, '.', '') }}
                            @else
                                {{-- Honest blank: no rate has been fetched or entered yet. --}}
                                <span title="No rate yet — set one in the editor, or use Update Exchange Rates">—</span>
                            @endif
                        </td>
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
            <button type="button" class="ao-pg-btn" wire:click="updateRates(false)"
                title="Fetch today's published rates and store them against each currency"
                wire:confirm="Fetch the latest market rates now?">Update Exchange Rates</button>
            <button type="button" class="ao-pg-btn" wire:click="updateRates(true)"
                title="Rewrite secondary-currency product prices from the Base Conv. Rate stored above — including one you set by hand"
                wire:confirm="Rewrite secondary-currency product prices from the rates stored above?">Update Product Prices</button>
        </div>

        <h4 class="ao-ano-heading">Add Additional Currency</h4>
        <form class="ao-find ao-of" wire:submit.prevent="addCurrency">
            <div class="ao-of-rows">
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-code">Currency Code</label>
                    <span class="ao-of-stack">
                        <input @nofill id="ao-cur-code" class="ao-of-sm" type="text" maxlength="3" wire:model="newCode" required>
                        <i class="ao-of-note-dim">eg. USD, GBP, etc...</i>
                    </span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-prefix">Prefix</label>
                    <span><input @nofill id="ao-cur-prefix" class="ao-of-sm" type="text" wire:model="newPrefix"></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-suffix">Suffix</label>
                    <span><input @nofill id="ao-cur-suffix" class="ao-of-sm" type="text" wire:model="newSuffix"></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-format">Format</label>
                    <span><select @nofill id="ao-cur-format" class="ao-of-md" wire:model="newFormat">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\CurrenciesList::FORMATS as $format)
                            <option value="{{ $format }}">{{ $format }}</option>
                        @endforeach
                    </select></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-cur-rate">Base Conv. Rate</label>
                    <span class="ao-of-stack">
                        <input @nofill id="ao-cur-rate" class="ao-of-sm" type="text" wire:model="newRate" placeholder="1.00">
                        <i class="ao-of-note-dim">The current rate to convert to base currency</i>
                    </span>
                </div>
            </div>
            <div class="ao-of-buttons"><button type="submit" class="ao-find-go">Add Currency</button></div>
        </form>
        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif
    </div>
</x-filament-panels::page>
