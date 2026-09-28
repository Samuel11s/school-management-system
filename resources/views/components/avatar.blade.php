@props(['name' => '', 'size' => null, 'src' => null])

@php
    $words = preg_split('/\s+/', trim((string) $name)) ?: [];
    $initials = mb_substr($words[0] ?? '', 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : '');
    $tone = abs(crc32((string) $name)) % 6;
@endphp

@if ($src)
    <img src="{{ $src }}" alt="" {{ $attributes->class(['rounded-circle', 'avatar-lg' => $size === 'xl', 'avatar' => $size !== 'xl']) }}>
@else
    <span {{ $attributes->class(['avatar-initials', 'avatar-tone-'.$tone, 'avatar-'.$size => $size]) }} aria-hidden="true">{{ $initials ?: '?' }}</span>
@endif
