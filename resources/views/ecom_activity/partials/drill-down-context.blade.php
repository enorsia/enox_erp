@props(['context'])

@if (! empty($context))
    @php
        $sessionMetric = collect($context['metrics'] ?? [])->firstWhere('label', 'Matching sessions');
        $extraMetrics = collect($context['metrics'] ?? [])->reject(
            fn (array $metric) => ($metric['label'] ?? '') === 'Matching sessions'
        );
        $sessionCount = (int) ($sessionMetric['value'] ?? 0);
        $filterSegments = collect($context['criteria'] ?? [])
            ->map(function (array $criterion): ?string {
                $label = trim((string) ($criterion['label'] ?? ''));
                $value = trim((string) ($criterion['value'] ?? ''));

                if ($value === '') {
                    return null;
                }

                return match ($label) {
                    'Product' => $value,
                    'Search', 'Product search' => $value,
                    default => $label !== '' ? "{$label}: {$value}" : $value,
                };
            })
            ->filter()
            ->values();
        $tooltip = trim(($context['description'] ?? '').' '.$filterSegments->implode(' · '));
    @endphp

    <div
        class="etd-activity-context"
        role="status"
        aria-label="{{ ($context['clear_label'] ?? 'Clear section') === 'Clear filters' ? 'Filtered activity summary' : 'Dashboard drill-down summary' }}"
        @if ($tooltip !== '') title="{{ $tooltip }}" @endif
    >
        <p class="etd-activity-context__line">
            <span class="etd-activity-context__section">{{ $context['section'] }}</span>

            @foreach ($filterSegments as $segment)
                <span class="etd-activity-context__sep" aria-hidden="true">·</span>
                <span class="etd-activity-context__filter">{{ $segment }}</span>
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
