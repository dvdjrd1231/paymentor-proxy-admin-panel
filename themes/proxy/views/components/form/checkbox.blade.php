<div class="wf-check {{ $divClass ?? '' }}">
    <input type="checkbox" name="{{ $name }}" id="{{ $id ?? $name }}"
        {{ $attributes->except(['label', 'name', 'id', 'class', 'divClass', 'required']) }} />
    <label for="{{ $id ?? $name }}">
        @isset($label)
            {{ $label }}
        @else
            {{ $slot }}
        @endisset
    </label>
    @error($name)
        <span class="wf-error">{{ $message }}</span>
    @enderror
</div>
