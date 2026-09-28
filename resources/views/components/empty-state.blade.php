@props(['icon' => 'bi-inbox', 'title' => 'Nothing here yet', 'message' => null])

<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
    <p class="empty-state-title">{{ $title }}</p>
    @if ($message)
        <p class="mb-0 small">{{ $message }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
