@props(['label' => 'Search', 'placeholder' => 'Search', 'model' => 'search'])

<div {{ $attributes->class(['input-group']) }}>
    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
    <label for="search-{{ $model }}" class="visually-hidden">{{ $label }}</label>
    <input type="search" id="search-{{ $model }}" class="form-control" placeholder="{{ $placeholder }}"
           wire:model.live.debounce.300ms="{{ $model }}" autocomplete="off">
</div>
