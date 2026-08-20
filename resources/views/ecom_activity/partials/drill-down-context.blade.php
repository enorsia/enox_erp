@props(['context'])

@if (! empty($context))
    @php
        $sessionMetric = collect($context['metrics'] ?? [])->firstWhere('label', 'Matching sessions');
        $extraMetrics = collect($context['metrics'] ?? [])->reject(
            fn (array $metric) => ($metric['label'] ?? '') === 'Matching sessions'
        );
        $sessionCount = (int) ($sessionMetric['value'] ?? 0);
        $filterValues = collect($context['criteria'] ?? [])->pluck('value')->filter()->values();
        $tooltip = trim(($context['description'] ?? '').' '.collect($context['criteria'] ?? [])->map(
            fn (array $criterion) => $criterion['label'].': '.$criterion['value']
        )->implode(' · '));
    @endphp

    <div
        class="etd-activity-context"
        role="status"
        aria-label="{{ ($context['clear_label'] ?? 'Clear section') === 'Clear filters' ? 'Filtered activity summary' : 'Dashboard drill-down summary' }}"
        @if ($tooltip !== '') title="{{ $tooltip }}" @endif
    >
        <p class="etd-activity-context__line">
            <span class="etd-activity-context__section">{{ $context['section'] }}</span>

            @foreach ($filterValues as $value)
                <span class="etd-activity-context__sep" aria-hidden="true">·</span>
                <span class="etd-activity-context__filter">{{ $value }}</span>
            @endforeach

            <span class="etd-activity-context__sep" aria-hidden="true">·</span>
            <span class="etd-activity-context__count">
                <span class="sr-only">Matching sessions: </span>{{ number_format($sessionCount) }} {{ $sessionCount === 1 ? 'session' : 'sessions' }}
            </span>

            @foreach ($extraMetrics as $metric)
                <span class="etd-activity-context__sep" aria-hidden="true">·</span>
                <span class="etd-activity-context__extra">
                    <span class="etd-activity-context__extra-label">{{ $metric['label'] }}</span>
                    {{ $metric['value'] }}
                </span>
            @endforeach

            @if (! empty($context['clear_focus_url']))
                <a href="{{ $context['clear_focus_url'] }}" class="etd-activity-context__clear">{{ $context['clear_label'] ?? 'Clear section' }}</a>
            @endif
        </p>
    </div>
@endif
