@props([
    'distribution' => [],
    'detailsUrl' => null,
    'chartId' => 'etdDurationDistChart',
    'showPanel' => true,
])

@php
    $buckets = $distribution['buckets'] ?? [];
    $totalSessions = (int) ($distribution['total_sessions'] ?? 0);
    $medianLabel = $distribution['median_label'] ?? '0s';
@endphp

@if ($showPanel)
    <div {{ $attributes->merge(['class' => 'etd-panel mb-5']) }}>
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Session duration distribution</h2>
            @if ($detailsUrl)
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailsUrl])
            @endif
        </div>
        <div class="etd-panel-body">
@endif

            <p class="etd-duration-summary m-0 mb-3">
                <strong>{{ number_format($totalSessions) }}</strong> sessions
                @if ($totalSessions > 0)
                    · median <strong>{{ $medianLabel }}</strong>
                @endif
            </p>

            @if ($totalSessions > 0)
                <div class="etd-chart-wrap etd-chart-wrap--duration-dist mb-4">
                    <canvas id="{{ $chartId }}"></canvas>
                </div>

                <div class="etd-duration-buckets" role="list">
                    @foreach ($buckets as $bucket)
                        <div class="etd-duration-bucket" role="listitem">
                            <div class="etd-duration-bucket__head">
                                <span class="etd-duration-bucket__label">{{ $bucket['label'] }}</span>
                                <span class="etd-duration-bucket__stats">
                                    {{ number_format($bucket['count']) }}
                                    <span class="etd-duration-bucket__pct">({{ number_format($bucket['pct'] ?? 0, 1) }}%)</span>
                                </span>
                            </div>
                            <div class="etd-duration-bucket__bar" aria-hidden="true">
                                <span class="etd-duration-bucket__fill" style="width: {{ min(100, (float) ($bucket['pct'] ?? 0)) }}%"></span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="etd-panel-note etd-panel-note--duration m-0 mt-3">
                    How long shoppers stay per session — useful for landing-page quality, content engagement, and checkout friction.
                </p>
            @else
                <p class="text-center text-slate-500 py-8 m-0">No sessions in this period.</p>
            @endif

@if ($showPanel)
        </div>
    </div>
@endif
