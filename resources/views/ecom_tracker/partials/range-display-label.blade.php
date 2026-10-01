@props([
    'range' => [],
    'class' => '',
])

@php
    use App\Support\TrackerTime;

    $parts = TrackerTime::rangeDisplayParts($range);
@endphp

@if (($parts['type'] ?? '') === 'range')
    <span @class(['etd-date-range', $class])>{{ $parts['from'] }} – {{ $parts['to'] }}</span>
@else
    <span @class(['etd-date-range', 'etd-date-range--single', $class])>{{ $parts['label'] ?? '' }}</span>
@endif
