@props([
    'metric',
])

<div class="etd-kpi etd-kpi--compact">
    @include('ecom_tracker.partials.kpi-label-with-tip', [
        'label' => $metric['label'],
        'tip' => $metric['tip'] ?? null,
    ])
    @include('ecom_tracker.partials.kpi-value-with-comparison', [
        'formatted' => $metric['formatted'],
        'comparison' => $metric['comparison'] ?? null,
        'valueClass' => $metric['value_class'] ?? '',
    ])
</div>
