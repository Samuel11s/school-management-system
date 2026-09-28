@props(['label', 'value', 'icon' => 'bi-bar-chart', 'variant' => 'primary', 'href' => null, 'hint' => null])

<div {{ $attributes->class(['card stat-card h-100 shadow-sm', 'stat-'.$variant]) }}>
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            <span class="stat-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
            <p class="stat-label mb-0">{{ $label }}</p>
        </div>
        <p @class(['stat-value mb-0', 'is-text' => ! is_numeric($value)])>{{ $value }}</p>
        @if ($hint)
            <p class="stat-hint mt-1 mb-0">{{ $hint }}</p>
        @endif
        @if ($href)
            <a href="{{ $href }}" class="stretched-link"><span class="visually-hidden">View {{ $label }}</span></a>
        @endif
    </div>
</div>
