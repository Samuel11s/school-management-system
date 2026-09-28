@props(['data', 'title' => 'Attendance this term', 'href' => null])

@php
    $total = $data['total'];
    $series = [
        ['present', 'Present', 'bg-chart-present'],
        ['late', 'Late', 'bg-chart-late'],
        ['absent', 'Absent', 'bg-chart-absent'],
        ['excused', 'Excused', 'bg-chart-excused'],
    ];
    $pct = fn (string $key) => $total > 0 ? round($data['counts'][$key] / $total * 100, 1) : 0;
    $summary = collect($series)->map(fn ($s) => $s[1].' '.$pct($s[0]).'%')->join(', ');
@endphp

<section {{ $attributes->class(['card shadow-sm h-100']) }} aria-labelledby="attendance-breakdown-heading">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 id="attendance-breakdown-heading" class="h6 mb-0 d-flex align-items-center">
            <span class="card-title-icon"><i class="bi bi-clipboard2-check" aria-hidden="true"></i></span>{{ $title }}
        </h2>
        @if ($href)
            <a href="{{ $href }}" class="small fw-medium">Details</a>
        @endif
    </div>
    <div class="card-body">
        @if ($total === 0)
            <x-empty-state icon="bi-clipboard" title="No attendance recorded yet" class="py-4" />
        @else
            <div class="segment-bar mb-4" role="img" aria-label="Attendance breakdown: {{ $summary }}">
                @foreach ($series as [$key, $label, $class])
                    @if ($data['counts'][$key] > 0)
                        <span class="{{ $class }}" style="flex-grow: {{ $data['counts'][$key] }}" title="{{ $label }}: {{ $pct($key) }}%"></span>
                    @endif
                @endforeach
            </div>
            <ul class="chart-legend">
                @foreach ($series as [$key, $label, $class])
                    <li>
                        <span><span class="legend-swatch {{ $class }}" aria-hidden="true"></span>{{ $label }}</span>
                        <span>
                            <span class="text-body-secondary small me-2">{{ number_format($data['counts'][$key]) }}</span>
                            <strong>{{ $pct($key) }}%</strong>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
