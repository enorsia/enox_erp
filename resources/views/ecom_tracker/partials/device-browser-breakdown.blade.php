@props([
    'devices' => ['by_device' => [], 'by_browser' => []],
    'deviceActivityLink' => null,
    'readOnly' => false,
    'showCompareDelta' => false,
    'deviceDeltas' => [],
    'browserDeltas' => [],
    'showInlineCompareDelta' => false,
    'deviceMetricDeltas' => [],
    'browserMetricDeltas' => [],
])

@php
    $deviceRows = $devices['by_device'] ?? [];
    $browserRows = $devices['by_browser'] ?? [];
    $devicesFocusLink = (! ($readOnly ?? false) && is_callable($deviceActivityLink)) ? $deviceActivityLink('') : null;
    $rowActivityLink = ($readOnly ?? false) ? null : $deviceActivityLink;
@endphp

<div @class(['etd-device-browser', 'etd-device-browser-grid', 'etd-device-browser--compare-inline' => $showInlineCompareDelta])>
    @include('ecom_tracker.partials.device-browser-table', [
        'title' => 'Device',
        'rows' => $deviceRows,
        'emptyMessage' => 'No device data in this period.',
        'rowActivityLink' => $rowActivityLink,
        'showCompareDelta' => $showCompareDelta,
        'compareDeltas' => $deviceDeltas,
        'showInlineCompareDelta' => $showInlineCompareDelta,
        'compareMetricDeltas' => $deviceMetricDeltas,
    ])

    @include('ecom_tracker.partials.device-browser-table', [
        'title' => 'Browser',
        'rows' => $browserRows,
        'emptyMessage' => 'No browser data in this period.',
        'rowActivityLink' => fn (string $label) => $devicesFocusLink,
        'showCompareDelta' => $showCompareDelta,
        'compareDeltas' => $browserDeltas,
        'showInlineCompareDelta' => $showInlineCompareDelta,
        'compareMetricDeltas' => $browserMetricDeltas,
    ])
</div>
