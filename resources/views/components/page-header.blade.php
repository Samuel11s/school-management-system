@props(['title', 'subtitle' => null, 'back' => null])

<header class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="back-link">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
            </a>
        @endif
        <h1 class="text-break">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</header>
