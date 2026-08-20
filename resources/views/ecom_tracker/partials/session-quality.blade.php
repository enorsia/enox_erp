@props([
    'visitorQuality' => [],
    'botTrafficUrl' => null,
    'extraMetrics' => [],
    'gridClass' => '',
])

@if (! empty($visitorQuality) || ! empty($extraMetrics))
    <div class="mb-5">
        <div class="flex items-center justify-between mb-2">
            <p class="etd-kpi-section-label m-0">Session quality</p>
            @can('ecom_tracker.bot_traffic.index')
                <a href="{{ $botTrafficUrl ?? route('admin.ecom-tracker.bot-traffic') }}" class="text-[12px] text-accent-500 no-underline hover:underline">View bot traffic details →</a>
            @endcan
        </div>
        <div @class(['etd-kpi-grid', $gridClass => filled($gridClass)])>
            @php $metricLabels = \App\Support\VisitorClassificationLabels::summaryMetricLabels(); @endphp
            @foreach ([
                ['key' => 'real_shoppers', 'label' => $metricLabels['real_shoppers']],
                ['key' => 'automated_traffic', 'label' => $metricLabels['automated_traffic']],
                ['key' => 'not_classified', 'label' => $metricLabels['not_classified']],
            ] as $kpi)
                @php $m = $visitorQuality[$kpi['key']] ?? ['current' => 0]; @endphp
                @include('ecom_tracker.partials.ga4-kpi-card', [
                    'label' => $kpi['label'],
                    'value' => $m['current'] ?? 0,
                    'compact' => true,
                ])
            @endforeach
            @foreach ($extraMetrics as $extra)
                @include('ecom_tracker.partials.ga4-kpi-card', [
                    'label' => $extra['label'],
                    'value' => $extra['value'],
                    'compact' => true,
                ])
            @endforeach
        </div>
    </div>
@endif
