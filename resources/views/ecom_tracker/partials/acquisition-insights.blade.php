@props([
    'distribution' => [],
    'newReturning' => [],
    'activityDurationLink' => null,
])

@php
    $unique = (int) ($newReturning['unique'] ?? $newReturning['new'] ?? 0);
    $returning = (int) ($newReturning['returning'] ?? 0);
    $total = $unique + $returning;
@endphp

<div class="etd-grid-2 etd-grid-2--acquisition-insights mt-5 mb-5" id="duration">
    @include('ecom_tracker.partials.session-duration-distribution', [
        'distribution' => $distribution,
        'inGrid' => true,
        'activityDurationLink' => $activityDurationLink,
    ])

    <div class="etd-panel etd-panel--new-returning">
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Unique vs returning</h2>
        </div>
        <div class="etd-panel-body">
            @if ($total > 0)
                <p class="etd-new-returning-summary m-0 mb-3">
                    <strong>{{ number_format($unique) }}</strong> unique visitors
                    · <strong>{{ number_format($returning) }}</strong> returning sessions
                </p>
                <div class="etd-chart-wrap xs"><canvas id="etdNewReturningChart"></canvas></div>
            @else
                <p class="text-center text-slate-500 py-8 m-0">No sessions in this period.</p>
            @endif
        </div>
    </div>
</div>
