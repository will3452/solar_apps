@props(['name', 'label', 'input' => [], 'type' => 'number', 'hint' => null])
@php($key = str_replace(['][', '[', ']'], ['.', '.', ''], $name))
@php($id = 'field-'.str_replace('.', '-', $key))
<div class="form-field">
    <label for="{{ $id }}">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($key, data_get($input, $key)) }}" @if ($type === 'number') step="any" @endif {{ $attributes->class(['form-input']) }} @if ($hint) aria-describedby="{{ $id }}-hint" @endif>
    @if ($hint)<p id="{{ $id }}-hint" class="field-hint">{{ $hint }}</p>@endif
    @error($key)<p class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
