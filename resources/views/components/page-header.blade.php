@props(['title', 'subtitle' => null, 'back' => null])

<header class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        @if ($back)
            <a href="{{ $back }}" class="small text-decoration-none d-inline-block mb-1">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
            </a>
        @endif
        <h1 class="h3 mb-0">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-body-secondary mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</header>
