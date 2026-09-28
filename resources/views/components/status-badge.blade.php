@props(['status'])

@if ($status)
    <span {{ $attributes->class(['badge badge-soft', 'badge-soft-'.(method_exists($status, 'badge') ? $status->badge() : 'secondary')]) }}>
        {{ $status->label() }}
    </span>
@endif
