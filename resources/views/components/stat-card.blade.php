@props(['label', 'value', 'icon' => 'bi-bar-chart', 'variant' => 'primary', 'href' => null])

<div {{ $attributes->class(['card stat-card h-100 shadow-sm']) }}>
    <div class="card-body d-flex align-items-center gap-3">
        <span class="rounded-3 p-3 bg-{{ $variant }}-subtle text-{{ $variant }}-emphasis">
            <i class="bi {{ $icon }} fs-4" aria-hidden="true"></i>
        </span>
        <div>
            <p class="text-body-secondary small mb-0">{{ $label }}</p>
            <p class="display-6 fs-3 mb-0">{{ $value }}</p>
            @if ($href)
                <a href="{{ $href }}" class="stretched-link small"><span class="visually-hidden">View {{ $label }}</span></a>
            @endif
        </div>
    </div>
</div>
