@extends('layouts.app')

@section('title', 'Visitor Analytics')

@section('content')
@php
    use App\Support\TrackerTime;

    $a = $analytics;
    $summary = $a['summary'];
    $presetWindows = $page['presetWindows'];
    $window = $page['window'];
    $hasCustomRange = $page['hasCustomRange'];
    $datetimeFromValue = $page['datetimeFromValue'];
    $datetimeToValue = $page['datetimeToValue'];
    $activeFilterCount = $page['activeFilterCount'];
    $exportUrl = $page['exportUrl'];
    $detailLink = $page['detailLink'];
    $activityLink = $page['activityLink'];
@endphp

<div class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-tracker.visitors'),
        'resetUrl' => route('admin.ecom-tracker.visitors'),
        'presetWindows' => $presetWindows,
        'window' => $window,
        'hasCustomRange' => $hasCustomRange,
        'datetimeFromValue' => $datetimeFromValue,
        'datetimeToValue' => $datetimeToValue,
    ])

    @include('ecom_tracker.partials.visitor-page-header', [
        'title' => 'Visitor analytics',
        'rangeLabel' => $page['rangeLabel'],
        'activeWindow' => $page['activeWindow'],
        'datetimeFromValue' => $datetimeFromValue,
        'datetimeToValue' => $datetimeToValue,
        'activeFilterCount' => $activeFilterCount,
        'resetUrl' => $page['resetUrl'],
        'exportUrl' => $exportUrl,
        'resetActive' => $page['resetActive'],
        'analyticsCache' => $a['analytics_cache'] ?? null,
    ])

    <div class="etd-kpi-grid etd-kpi-grid--5 mb-5">
        @foreach ([
            ['label' => 'Unique visitors', 'key' => 'unique_visitors', 'format' => 'number'],
            ['label' => 'Returning visitors', 'key' => 'returning_visitors', 'format' => 'number'],
            ['label' => 'Sessions', 'key' => 'sessions', 'format' => 'number'],
            ['label' => 'Avg session duration', 'key' => 'avg_session_duration_label', 'format' => 'text'],
            ['label' => 'Total time on site', 'key' => 'total_stay_label', 'format' => 'text'],
        ] as $kpi)
            <div class="etd-kpi">
                <div class="etd-kpi-label">{{ $kpi['label'] }}</div>
                <div class="etd-kpi-value">{{ $kpi['format'] === 'number' ? number_format($summary[$kpi['key']] ?? 0) : ($summary[$kpi['key']] ?? '—') }}</div>
            </div>
        @endforeach
    </div>

    <div class="etd-grid-2 mb-5">
        <div class="etd-panel">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Unique visitors vs sessions</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('trend')])
            </div>
            <div class="etd-chart-wrap"><canvas id="vaTrendMini"></canvas></div>
        </div>
        <div class="etd-panel">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Unique vs returning</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('new-returning')])
            </div>
            <div class="etd-chart-wrap sm"><canvas id="vaNewReturningMini"></canvas></div>
        </div>
    </div>

    @include('ecom_tracker.partials.session-quality', [
        'visitorQuality' => $a['visitor_quality'] ?? [],
        'gridClass' => 'etd-kpi-grid--3',
    ])

    <div class="etd-panel mb-5">
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Session duration distribution</h2>
            @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('duration')])
        </div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            @foreach ($a['duration_buckets'] as $bucket)
                <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 text-center">
                    <div class="text-2xl font-semibold">{{ number_format($bucket['count']) }}</div>
                    <div class="text-xs text-slate-500 mt-1">{{ $bucket['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="etd-panel">
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Recent visitors</h2>
            @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('visitors'), 'viewLabel' => 'View all'])
        </div>
        <table class="etd-table w-full">
            <thead><tr><th>Visitor</th><th class="etd-num">Sessions</th><th class="etd-num">Order qty</th><th class="etd-num">Total stay</th><th>Last active</th></tr></thead>
            <tbody>
                @forelse ($a['top_visitors'] as $visitor)
                    <tr>
                        <td>
                            <code class="text-xs" title="{{ $visitor['visitor_id'] }}">{{ Str::limit($visitor['visitor_id'], 12) }}</code>
                            @can('ecom_tracker.activity.index')
                                <div class="mt-1"><a href="{{ $activityLink($visitor['visitor_id']) }}" class="etd-link">View sessions</a></div>
                            @endcan
                        </td>
                        <td class="etd-num">{{ $visitor['session_count'] }}</td>
                        <td class="etd-num">{{ number_format($visitor['order_qty'] ?? 0) }}</td>
                        <td class="etd-num">{{ $visitor['total_stay_label'] }}</td>
                        <td>{{ TrackerTime::diffForHumansFromStorage($visitor['last_active_at']) ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500 py-8">No visitors in this window.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    window.visitorAnalyticsData = @json([
        'trend' => $a['trend'],
        'new_returning' => $a['new_returning'],
    ]);
</script>
@vite('resources/js/pages/visitor-analytics.js')
@endsection
