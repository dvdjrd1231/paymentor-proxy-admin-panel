{{--
    General Settings, to issue #39's reference screenshots: the file-folder tab bar over a
    framed form of label-left rows, each with its inline hint, then Save/Cancel centred
    under the frame. Every field is one of Paymenter's real settings; the three tabs whose
    WHMCS content has no Paymenter equivalent say so instead of inventing fields.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-gs">
        <div class="ao-gs-tabs">
            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\GeneralSettings::TABS as $key => $label)
                <button type="button" class="ao-gs-tab {{ $tab === $key ? 'ao-on' : '' }}"
                    wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
            @endforeach
        </div>

        <div class="ao-gs-frame">
            @if ($tab === 'ordering')
                <p class="ao-gs-empty" title="WHMCS's Ordering tab configures its order form flows; Paymenter's checkout has no equivalent switches">
                    Paymenter's checkout carries no configurable ordering options — WHMCS's
                    Ordering settings have no equivalent here.
                </p>
            @elseif ($tab === 'domains')
                <p class="ao-gs-empty" title="No domain registrar is connected to this store">
                    No domain registrar is connected to this store, so there is nothing to
                    configure here.
                </p>
            @elseif ($tab === 'affiliates')
                <p class="ao-gs-empty">
                    Affiliate settings live on the Affiliates extension —
                    <a href="{{ url('/admin/extensions') }}">Admin → Extensions → Affiliates</a>.
                </p>
            @else
                @foreach ($fields as $field)
                    @php $name = $field['name']; $type = $field['type'] ?? 'text'; @endphp
                    <div class="ao-gs-row">
                        <label class="ao-gs-label" for="gs-{{ $name }}">{{ $field['label'] ?? $name }}</label>
                        <div class="ao-gs-field">
                            @switch($type)
                                @case('select')
                                    @php
                                        $options = $field['options'] ?? [];
                                        $assoc = array_keys($options) !== range(0, count($options) - 1);
                                    @endphp
                                    <select id="gs-{{ $name }}" wire:model="values.{{ $name }}" @if (!empty($field['multiple'])) multiple @endif>
                                        @foreach ($options as $value => $label)
                                            <option value="{{ $assoc ? $value : $label }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @break

                                @case('checkbox')
                                    <input type="checkbox" id="gs-{{ $name }}" wire:model="values.{{ $name }}">
                                    @break

                                @case('textarea')
                                @case('markdown')
                                @case('tags')
                                    <textarea id="gs-{{ $name }}" rows="4" wire:model="values.{{ $name }}"></textarea>
                                    @break

                                @case('password')
                                    <input type="password" id="gs-{{ $name }}" wire:model="values.{{ $name }}" autocomplete="new-password">
                                    @break

                                @case('number')
                                    <input type="number" id="gs-{{ $name }}" wire:model="values.{{ $name }}">
                                    @break

                                @case('time')
                                    <input type="time" id="gs-{{ $name }}" wire:model="values.{{ $name }}">
                                    @break

                                @default
                                    <input type="text" id="gs-{{ $name }}" wire:model="values.{{ $name }}">
                            @endswitch

                            @if (!empty($field['description']))
                                <span class="ao-gs-hint">{{ $field['description'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        @if (!in_array($tab, ['ordering', 'domains', 'affiliates'], true))
            <div class="ao-gs-actions">
                <button type="button" class="ao-find-go" wire:click="save">Save Changes</button>
                <a class="ao-gs-cancel" href="{{ static::getUrl(['tab' => $tab]) }}">Cancel Changes</a>
            </div>
        @endif
    </div>
</x-filament-panels::page>
