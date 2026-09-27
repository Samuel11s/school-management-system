@props(['target' => null, 'label' => 'Loading'])

<div {{ $attributes->class(['align-items-center text-body-secondary small']) }}
     wire:loading.flex @if ($target) wire:target="{{ $target }}" @endif role="status">
    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>{{ $label }}&hellip;
</div>
