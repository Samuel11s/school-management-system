@props([
    'name',
    'label',
    'rows' => 3,
    'required' => false,
    'help' => null,
])

@php
    $id = $attributes->get('id', 'field-'.\Illuminate\Support\Str::slug(str_replace('.', '-', $name)));
    $invalid = $errors->has($name);
    $describedBy = trim(($help ? $id.'-help ' : '').($invalid ? $id.'-error' : ''));
    $wire = $attributes->whereStartsWith('wire:model')->isEmpty() ? ['wire:model' => $name] : [];
@endphp

<div {{ $attributes->only('class')->class(['mb-3']) }}>
    <label for="{{ $id }}" class="form-label">
        {{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif
    </label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        {{ $attributes->except(['class', 'id'])->merge($wire)->class(['form-control', 'is-invalid' => $invalid]) }}
        @required($required)
        @if ($invalid) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif></textarea>
    @if ($help)
        <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
    @endif
    @error($name)
        <div id="{{ $id }}-error" class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
