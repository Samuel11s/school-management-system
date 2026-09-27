@props(['icon' => 'bi-inbox', 'title' => 'Nothing here yet', 'message' => null])

<div {{ $attributes->class(['text-center text-body-secondary py-5']) }}>
    <i class="bi {{ $icon }} display-6 d-block mb-2" aria-hidden="true"></i>
    <p class="fw-semibold mb-1">{{ $title }}</p>
    @if ($message)
        <p class="mb-0 small">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
