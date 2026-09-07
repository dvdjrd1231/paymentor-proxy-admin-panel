{{--
    General Settings, to the reference's screenshots: the file-folder tab bar over a
    framed form of label-left rows, each with its inline hint, then Save/Cancel centred
    under the frame.

    Rows come from Support\SettingsReference, which lists the reference's fields in its
    order. A row either drives a real Paymenter setting or renders disabled with the
    reason it cannot — see the page class for why the disabled ones are here rather than
    quietly dropped.
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
            @foreach ($fields as $field)
                    @php $name = $field['name'] ?? null; $type = $field['type'] ?? 'text'; @endphp

                    @if (empty($name))
                        {{-- No Paymenter setting behind it. Shown, not hidden: an admin
                             hunting for a WHMCS switch should find out here that it does
                             not exist, rather than assume the page is incomplete. --}}
                        <div class="ao-gs-row ao-gs-row-off">
                            <span class="ao-gs-label">{{ $field['label'] }}</span>
                            <div class="ao-gs-field">
                                <input type="text" value="Not available" disabled>
                            </div>
                            <div class="ao-gs-hint">{{ $field['why'] }}</div>
                        </div>
                        @continue
                    @endif

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

                        </div>
                        {{-- The hint is its own column, as the reference has it. Nested
                             inside the field it wrapped underneath on any row whose
                             control was wide, which is why the page read as ragged. --}}
                        <div class="ao-gs-hint">{{ $field['hint'] ?? '' }}</div>
                    </div>
                @endforeach
        </div>

        <div class="ao-gs-actions">
            <button type="button" class="ao-find-go" wire:click="save">Save Changes</button>
            <a class="ao-gs-cancel" href="{{ static::getUrl(['tab' => $tab]) }}">Cancel Changes</a>
        </div>
    </div>
</x-filament-panels::page>
