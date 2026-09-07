{{--
    The reference's payment gateway configuration screen: the gateway's name and module,
    then the module's own settings label-left with the hint beside each, closed by the
    centred Save / Cancel / Deactivate row.

    Stored values are shown rather than masked — see the page class for why, and for the
    two guards that stay.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <a class="ao-mu-tab"
                href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\PaymentGateways::getUrl() }}">&laquo; Back to Payment Gateways</a>
        </div>

        <form class="ao-gs" wire:submit.prevent="save">
            <div class="ao-gs-frame">
                <div class="ao-gs-row">
                    <label class="ao-gs-label" for="ao-gw-name">Gateway Name</label>
                    <div class="ao-gs-field">
                        <input id="ao-gw-name" type="text" wire:model="name" required @nofill>
                    </div>
                    <div class="ao-gs-hint">The name clients see when choosing how to pay</div>
                </div>

                <div class="ao-gs-row">
                    <span class="ao-gs-label">Module</span>
                    <div class="ao-gs-field">
                        <input type="text" value="{{ $this->gateway->extension }}" disabled>
                    </div>
                    <div class="ao-gs-hint">The module cannot change once a gateway exists — deactivate it and activate the other instead</div>
                </div>

                @forelse ($this->config() as $option)
                    @php
                        $key = $option['name'];
                        $type = $option['type'] ?? 'text';
                        $id = 'ao-gw-' . $key;
                    @endphp
                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="{{ $id }}">{{ $option['label'] ?? $key }}</label>
                        <div class="ao-gs-field">
                            @switch($type)
                                @case('checkbox')
                                    <input type="checkbox" id="{{ $id }}" wire:model="settings.{{ $key }}">
                                    @break

                                @case('select')
                                    <select id="{{ $id }}" wire:model="settings.{{ $key }}">
                                        @foreach (($option['options'] ?? []) as $value => $label)
                                            <option value="{{ is_int($value) ? $label : $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @break

                                @case('textarea')
                                @case('markdown')
                                    <textarea id="{{ $id }}" rows="4" wire:model="settings.{{ $key }}" @nofill></textarea>
                                    @break

                                @case('number')
                                    <input type="number" id="{{ $id }}" wire:model="settings.{{ $key }}" @nofill>
                                    @break

                                @case('placeholder')
                                    <span class="ao-gw-note">{{ strip_tags((string) ($option['label'] ?? '')) }}</span>
                                    @break

                                @default
                                    {{-- Type text even for a password field: the stored value is
                                         shown on purpose here, and a masked box that reveals its
                                         contents on copy is worse than an honest one. @nofill keeps
                                         password managers from capturing or overwriting a key. --}}
                                    <input type="text" id="{{ $id }}" wire:model="settings.{{ $key }}" @nofill>
                            @endswitch
                        </div>
                        <div class="ao-gs-hint">{!! $option['description'] ?? '' !!}</div>
                    </div>
                @empty
                    <p class="ao-gs-empty">
                        This gateway's module is not installed on this server, so it has no settings
                        to configure. The gateway can still be renamed or deactivated.
                    </p>
                @endforelse
            </div>

            <div class="ao-oc-actions">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <a class="ao-oc-cancel"
                    href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\PaymentGateways::getUrl() }}">Cancel Changes</a>
                <button type="button" class="ao-eo-delete" wire:click="$set('confirmingDelete', true)">Deactivate</button>
            </div>
        </form>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif

        @if ($confirmingDelete)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingDelete', false)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingDelete', false)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Deactivate {{ $this->gateway->name }}?</p>
                        <p>Clients stop being offered it immediately. Its settings are kept, so
                            activating it again restores them.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingDelete', false)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="delete">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
