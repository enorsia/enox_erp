@props([
    'distribution' => [],
    'newReturning' => [],
    'activityDurationLink' => null,
    'newReturningChartId' => 'etdNewReturningChart',
    'forCompare' => false,
    'audienceDetailUrl' => null,
])

@php
    $unique = (int) ($newReturning['unique'] ?? $newReturning['new'] ?? 0);
    $returning = (int) ($newReturning['returning'] ?? 0);
    $total = $unique + $returning;
@endphp

<div @class([
    'etd-grid-2 etd-grid-2--acquisition-insights',
    'mt-5 mb-5' => ! $forCompare,
    'etd-grid-2--compare-acquisition etd-acquisition-insights--compare-compact' => $forCompare,
]) @unless($forCompare) id="duration" @endunless>
    @include('ecom_tracker.partials.session-duration-distribution', [
        'distribution' => $distribution,
        'inGrid' => true,
        'activityDurationLink' => $activityDurationLink,
        'panelClass' => $forCompare ? 'etd-compare-panel etd-compare-detail-only etd-print-unit' : '',
        'compareSync' => $forCompare ? 'duration' : null,
        'compact' => $forCompare,
    ])

    <div @class([
        'etd-panel etd-panel--new-returning',
        'etd-compare-detail-only etd-print-unit etd-panel--new-returning--under-duration' => $forCompare,
    ]) @if ($forCompare) data-compare-sync="new-returning" @endif>
        <div class="etd-panel-head">
            @if ($forCompare)
                <h3 class="etd-acquisition-subsection-title">Unique vs returning</h3>
            @else
                <h2 class="etd-panel-title">Unique vs returning</h2>
            @endif
        </div>
        <div class="etd-panel-body">
            @if ($total > 0)
                <p @class(['etd-new-returning-summary m-0', 'mb-3' => ! $forCompare, 'mb-2' => $forCompare])>
                    @if (filled($audienceDetailUrl))
                        <a href="{{ $audienceDetailUrl }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">
                            <strong>{{ number_format($unique) }}</strong> unique visitors
                        </a>
                    @else
                        <strong>{{ number_format($unique) }}</strong> unique visitors
                    @endif
                    · <strong>{{ number_format($returning) }}</strong> returning sessions
                </p>
                <div class="etd-chart-wrap xs"><canvas id="{{ $newReturningChartId }}"></canvas></div>
            @else
                <p class="text-center text-slate-500 py-8 m-0">No sessions in this period.</p>
            @endif
        </div>
    </div>
</div>
