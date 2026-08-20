@props(['name', 'label' => null, 'options' => [], 'selected' => null, 'multiple' => false, 'required' => false, 'divClass' => null, 'id' => null, 'hideRequiredIndicator' => false])
<fieldset class="wf-field {{ $divClass ?? '' }}">
    @if ($label)
        <label for="{{ $id ?? $name }}">
            {{ $label }}
            @if ($required && !$hideRequiredIndicator)
                <span class="wf-req">*</span>
            @endif
        </label>
    @endif

    <select id="{{ $id ?? $name }}" {{ $multiple ? 'multiple' : '' }}
        {{ $attributes->except(['options', 'id', 'name', 'multiple'])->merge(['class' => 'wf-select']) }}
        name="{{ $name }}{{ $multiple ? '[]' : '' }}">
        @if (count($options) == 0 && $slot)
            {{ $slot }}
        @else
            @foreach ($options as $key => $option)
                <option value="{{ gettype($options) == 'array' ? $option : $key }}"
                    {{ ($multiple && $selected ? in_array($key, $selected) : $selected == $option) ? 'selected' : '' }}>
                    {{ $option }}</option>
            @endforeach
        @endif
    </select>

    @error($name)
        <span class="wf-error">{{ $message }}</span>
    @enderror
</fieldset>
