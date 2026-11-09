@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])
<div class="form-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ $value ?? old($name) }}" @endif
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($errors->has($name) || $hint) aria-describedby="{{ $name }}-{{ $errors->has($name) ? 'error' : 'hint' }}" @endif
        {{ $attributes->class(['form-input']) }}
    >
    @error($name)
        <p id="{{ $name }}-error" class="field-error" role="alert">{{ $message }}</p>
    @else
        @if ($hint)
            <p id="{{ $name }}-hint" class="field-hint">{{ $hint }}</p>
        @endif
    @enderror
</div>
