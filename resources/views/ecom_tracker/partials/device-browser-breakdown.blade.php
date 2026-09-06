@props([
    'devices' => ['by_device' => [], 'by_browser' => []],
])

@php
    $deviceRows = $devices['by_device'] ?? [];
    $browserRows = $devices['by_browser'] ?? [];
@endphp

<div class="etd-device-browser etd-device-browser-grid">
    @include('ecom_tracker.partials.device-browser-table', [
        'title' => 'Device',
        'rows' => $deviceRows,
        'emptyMessage' => 'No device data in this period.',
    ])

    @include('ecom_tracker.partials.device-browser-table', [
        'title' => 'Browser',
        'rows' => $browserRows,
        'emptyMessage' => 'No browser data in this period.',
    ])
</div>
