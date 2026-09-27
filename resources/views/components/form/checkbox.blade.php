@props([
    'name',
    'label',
    'help' => null,
])

@php
    $id = $attributes->get('id', 'field-'.\Illuminate\Support\Str::slug(str_replace('.', '-', $name)));
    $invalid = $errors->has($name);
    $wire = $attributes->whereStartsWith('wire:model')->isEmpty() ? ['wire:model' => $name] : [];
@endphp

<div {{ $attributes->only('class')->class(['form-check mb-3']) }}>
    <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1"
        {{ $attributes->except(['class', 'id'])->merge($wire)->class(['form-check-input', 'is-invalid' => $invalid]) }}
        @if ($help) aria-describedby="{{ $id }}-help" @endif>
    <label for="{{ $id }}" class="form-check-label">{{ $label }}</label>
    @if ($help)
        <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
    @endif
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
