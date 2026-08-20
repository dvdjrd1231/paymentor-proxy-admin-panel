@props(['name', 'label' => null, 'required' => false, 'divClass' => null, 'class' => null, 'placeholder' => null, 'id' => null, 'type' => null, 'hideRequiredIndicator' => false, 'dirty' => false])
<fieldset class="wf-field {{ $divClass ?? '' }}">
    @if ($label)
        <label for="{{ $id ?? $name }}">
            {{ $label }}
            @if ($required && !$hideRequiredIndicator)
                <span class="wf-req">*</span>
            @endif
        </label>
    @endif
    <textarea id="{{ $id ?? $name }}" name="{{ $name }}" class="wf-input wf-textarea {{ $class ?? '' }}"
        placeholder="{{ $placeholder ?? ($label ?? '') }}"
        @if ($dirty && isset($attributes['wire:model'])) wire:dirty.class="wf-input--dirty" @endif
        {{ $attributes->except(['placeholder', 'label', 'id', 'name', 'type', 'class', 'divClass', 'required', 'hideRequiredIndicator', 'dirty']) }}
        @required($required)>{{ $slot }}</textarea>
    @error($name)
        <span class="wf-error">{{ $message }}</span>
    @enderror
</fieldset>
