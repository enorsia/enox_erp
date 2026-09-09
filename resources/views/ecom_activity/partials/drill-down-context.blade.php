@props([
    'context',
    'export_key' => null,
    'export' => null,
])

@if (! empty($context))
    @php
        $sessionMetric = collect($context['metrics'] ?? [])->firstWhere('label', 'Matching sessions');
        $extraMetrics = collect($context['metrics'] ?? [])->reject(
            fn (array $metric) => ($metric['label'] ?? '') === 'Matching sessions'
        );
        $sessionCount = (int) ($sessionMetric['value'] ?? 0);
        $filterChips = collect($context['filter_chips'] ?? [])->filter(
            fn (array $chip) => filled($chip['label'] ?? null)
        )->values();
        $tooltip = trim((string) ($context['description'] ?? ''));
    @endphp

    <div
        class="etd-activity-context"
        role="region"
        aria-label="{{ ($context['clear_label'] ?? 'Clear section') === 'Clear filters' ? 'Filtered activity summary' : 'Dashboard drill-down summary' }}"
        @if ($tooltip !== '') title="{{ $tooltip }}" @endif
    >
        <div class="etd-activity-context__summary">
            <div class="etd-activity-context__metrics" role="status">
                <span class="etd-activity-context__metric etd-activity-context__metric--sessions">
                    <span class="sr-only">Matching sessions: </span>{{ number_format($sessionCount) }} {{ $sessionCount === 1 ? 'session' : 'sessions' }}
                </span>

                @foreach ($extraMetrics as $metric)
                    <span class="etd-activity-context__metric">
                        <span class="etd-activity-context__metric-label">{{ $metric['label'] }}</span>
                        {{ $metric['value'] }}
                    </span>
                @endforeach
            </div>

            @if (filled($export_key))
                <div class="etd-activity-context__export">
                    @include('ecom_tracker.partials.exports.export-header-status', [
                        'export_key' => $export_key,
                        'export' => $export,
                    ])
                </div>
            @endif
        </div>

        @if ($filterChips->isNotEmpty())
            <div class="etd-activity-context__filters">
                <div class="etd-activity-context__filter-chips">
                    @foreach ($filterChips as $chip)
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
