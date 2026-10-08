@props([
    'title',
    'metrics' => [],
    'modifier' => '',
    'cols' => 2,
    'metricHrefs' => [],
])

<div class="etd-kpi-group etd-kpi-group--{{ $cols }} {{ $modifier }}">
    <p class="etd-kpi-section-label">{{ $title }}</p>
    <div class="etd-kpi-group-grid">
        @foreach ($metrics as $metric)
            @if ($metric)
                @php $href = $metric['href'] ?? ($metricHrefs[$loop->index] ?? null); @endphp
                @if (filled($href))
                    <a href="{{ $href }}" class="etd-kpi-drilldown-link no-underline text-inherit">
                        @include('ecom_tracker.partials.kpi-metric-card', ['metric' => $metric])
                    </a>
                @else
                    @include('ecom_tracker.partials.kpi-metric-card', ['metric' => $metric])
                @endif
            @endif
        @endforeach
    </div>
</div>
