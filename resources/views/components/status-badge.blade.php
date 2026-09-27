@props(['status'])

@if ($status)
    <span {{ $attributes->class(['badge', 'text-bg-'.(method_exists($status, 'badge') ? $status->badge() : 'secondary')]) }}>
        {{ $status->label() }}
    </span>
@endif
