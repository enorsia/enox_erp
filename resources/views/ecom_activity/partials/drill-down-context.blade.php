@if ($drillDownUi)
    @php
        $sessionSlim = ($drillDownUi['useKpiCards'] ?? false) && blank($drillDownUi['sectionSubtitle'] ?? null);
        $slimCards = $drillDownUi['metricCards'] ?? [];
        $slimLead = $sessionSlim ? array_shift($slimCards) : null;
    @endphp
    @if ($sessionSlim)
        <div class="etd-panel etd-print-unit etd-product-performance-totals" role="region" aria-label="{{ $drillDownUi['contextAriaLabel'] }}">
            <div class="etd-product-performance-totals__row">
                <div class="etd-product-performance-totals__lead">
                    <span class="etd-product-performance-totals__lead-label">Total<span class="etd-product-performance-totals__lead-sep" aria-hidden="true">:</span></span>
                    <span class="etd-product-performance-totals__lead-scope">{{ $slimLead['formatted'] ?? '0' }} {{ (int) str_replace(',', '', (string) ($slimLead['formatted'] ?? '0')) === 1 ? 'session' : 'sessions' }}</span>
                </div>
                <div class="etd-product-performance-totals__metrics">
                    @foreach ($slimCards as $metric)
                        @php
                            $isSale = str_starts_with((string) ($metric['formatted'] ?? ''), '£');
                            $slimLabel = match ($metric['label'] ?? '') {
                                'Add to cart' => 'Add',
                                'Begin checkout' => 'Begin',
                                'Proceed checkout' => 'Proceed',
                                'Units sold' => 'Units',
                                'Sold amount' => 'Sale',
                                default => $metric['label'] ?? '',
                            };
                        @endphp
                        <div @class(['etd-product-performance-totals__metric', 'etd-product-performance-totals__metric--sale' => $isSale])>
                            <span class="etd-product-performance-totals__metric-label">
                                @include('ecom_tracker.partials.column-header-with-tip', [
                                    'label' => $slimLabel,
                                    'tip' => $metric['tip'] ?? '',
                                    'align' => 'left',
                                    'iconBefore' => true,
                                    'labelSuffix' => ':',
                                ])
                            </span>
                            <span class="etd-product-performance-totals__metric-value">{{ $metric['formatted'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
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
                        @if (($chip['label'] ?? '') === 'Clear filter')
                            <a href="{{ $chip['remove_url'] }}" class="etd-clear-section-btn no-underline">Clear filter</a>
                        @else
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
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>
    @endif
@endif
