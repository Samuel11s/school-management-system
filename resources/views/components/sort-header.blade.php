@props(['field', 'label', 'sortField', 'sortDirection'])

@php
    $active = $sortField === $field;
    $ariaSort = $active ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none';
@endphp

<th scope="col" aria-sort="{{ $ariaSort }}">
    <button type="button" class="sortable-header" wire:click="sortBy('{{ $field }}')">
        {{ $label }}
        @if ($active)
            <i class="bi {{ $sortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down' }}" aria-hidden="true"></i>
        @else
            <i class="bi bi-arrow-down-up text-body-tertiary small" aria-hidden="true"></i>
        @endif
    </button>
</th>
