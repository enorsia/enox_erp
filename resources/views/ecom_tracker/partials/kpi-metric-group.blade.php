@props([
    'title',
    'metrics' => [],
    'modifier' => '',
    'cols' => 2,
])

<div class="etd-kpi-group etd-kpi-group--{{ $cols }} {{ $modifier }}">
    <p class="etd-kpi-section-label">{{ $title }}</p>
    <div class="etd-kpi-group-grid">
        @foreach ($metrics as $metric)
            @if ($metric)
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
            @endif
        @endforeach
    </div>
</div>
