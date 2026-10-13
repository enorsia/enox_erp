@if ($drillDownUi)
    <div
        class="etd-activity-context @if ($drillDownUi['useKpiCards'] ?? false) etd-activity-context--product-kpis @endif"
        role="region"
        aria-label="{{ $drillDownUi['contextAriaLabel'] }}"
        @if (($drillDownUi['tooltip'] ?? '') !== '') title="{{ $drillDownUi['tooltip'] }}" @endif
    >
        <div class="etd-activity-context__summary">
            @if ($drillDownUi['useKpiCards'] ?? false)
                <div class="etd-activity-product-kpis" role="status">
                    <div class="etd-kpi-grid etd-kpi-grid--6 etd-activity-product-kpis__grid">
                        @foreach ($drillDownUi['metricCards'] ?? [] as $metric)
                            @include('ecom_tracker.partials.kpi-metric-card', ['metric' => $metric])
                        @endforeach
                    </div>
                </div>
            @else
                <div class="etd-activity-context__metrics" role="status">
                    <span class="etd-activity-context__metric etd-activity-context__metric--sessions">
                        <span class="sr-only">Matching sessions: </span>{{ number_format($drillDownUi['sessionCount']) }} {{ $drillDownUi['sessionCountLabel'] }}
                    </span>

                    @foreach ($drillDownUi['extraMetrics'] as $metric)
                        <span class="etd-activity-context__metric">
                            <span class="etd-activity-context__metric-label">{{ $metric['label'] }}</span>
                            {{ $metric['value'] }}
                        </span>
                    @endforeach
                </div>
            @endif

            @if ($drillDownUi['showExport'])
                <div class="etd-activity-context__export">
                    @include('ecom_tracker.partials.exports.export-header-status', [
                        'export_key' => $drillDownUi['exportKey'],
                        'export' => $activityExport,
                    ])
                </div>
            @endif
        </div>

        @if ($drillDownUi['filterChips'] !== [] && ! ($drillDownUi['useKpiCards'] ?? false))
            <div class="etd-activity-context__filters">
                <div class="etd-activity-context__filter-chips">
                    @foreach ($drillDownUi['filterChips'] as $chip)
                        <span class="etd-activity-context__chip">
                            <span class="etd-activity-context__chip-label">{{ $chip['label'] }}</span>
                            @if (! empty($chip['remove_url']))
                                <a
                                    href="{{ $chip['remove_url'] }}"
                                    class="etd-activity-context__chip-remove"
                                    aria-label="Remove filter: {{ $chip['label'] }}"
                                >
                                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </a>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif
