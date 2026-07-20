@extends('layouts.app')

@section('title', 'Visitor Analytics')

@section('content')
@php
    use App\Support\TrackerTime;
    $a = $analytics;
    $window = $filters['window'] ?? '24h';
    $hasCustomRange = filled($filters['datetime_from'] ?? null) && filled($filters['datetime_to'] ?? null);
    $activeFilterCount = $hasCustomRange ? 1 : ((request()->has('window') && $window !== '24h') ? 1 : 0);
    $datetimeFromValue = filled($filters['datetime_from'] ?? null) ? TrackerTime::toLocal($filters['datetime_from'])?->format('Y-m-d\TH:i') : '';
    $datetimeToValue = filled($filters['datetime_to'] ?? null) ? TrackerTime::toLocal($filters['datetime_to'])?->format('Y-m-d\TH:i') : '';
    $presetWindows = ['3h' => '3 hours', '6h' => '6 hours', '12h' => '12 hours', '24h' => '24 hours', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', '1y' => '1 year'];
    $back = urlencode(request()->fullUrl());
    $detailLink = fn (string $section) => route('admin.ecom-tracker.visitors.details', $section).'?'.http_build_query(array_merge(request()->only(['window', 'datetime_from', 'datetime_to']), ['back' => $back]));
    $activityLink = fn (string $visitorId) => route('admin.ecom-activity.index', ['search' => $visitorId]);
    $summary = $a['summary'];
    $prior = $a['prior_summary'] ?? [];
    $delta = fn (string $key) => ($prior[$key] ?? 0) > 0 ? round((($summary[$key] ?? 0) - $prior[$key]) / $prior[$key] * 100, 1) : null;
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

    @include('ecom_tracker.partials.tracker-nav', ['current' => 'visitors'])

    <div class="etd-topbar">
        <div class="etd-topbar-intro">
            <h1 class="etd-page-title">Visitor analytics</h1>
            <p class="etd-page-desc">{{ $filters['window_label'] ?? 'Last 24 hours' }}</p>
            @include('ecom_tracker.partials.timezone-notice')
        </div>
        <div class="flex items-center gap-2 flex-wrap shrink-0">
            <a href="{{ route('admin.ecom-tracker.visitors.export', request()->query()) }}" class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 transition-colors font-medium no-underline">Export</a>
            <button type="button" @click="drawerOpen = true" class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilterCount > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600' }}">
                Filters @if ($activeFilterCount > 0)<span class="bg-accent-400 text-white text-[9px] font-bold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilterCount }}</span>@endif
            </button>
        </div>
    </div>

    <div class="etd-kpi-grid etd-kpi-grid--3 mb-5">
        @foreach ([
            ['label' => 'Active visitors', 'key' => 'active_visitors', 'format' => 'number'],
            ['label' => 'New visitors', 'key' => 'new_visitors', 'format' => 'number'],
            ['label' => 'Sessions', 'key' => 'sessions', 'format' => 'number'],
            ['label' => 'Avg session duration', 'key' => 'avg_session_duration_label', 'format' => 'text'],
            ['label' => 'Avg visitor stay', 'key' => 'avg_visitor_stay_label', 'format' => 'text'],
            ['label' => 'Total time on site', 'key' => 'total_stay_label', 'format' => 'text'],
        ] as $kpi)
            <div class="etd-kpi">
                <div class="etd-kpi-label">{{ $kpi['label'] }}</div>
                <div class="etd-kpi-value">{{ $kpi['format'] === 'number' ? number_format($summary[$kpi['key']] ?? 0) : ($summary[$kpi['key']] ?? '—') }}</div>
                @if ($kpi['format'] === 'number' && $delta($kpi['key']) !== null)
                    <div class="etd-kpi-delta {{ $delta($kpi['key']) >= 0 ? 'up' : 'down' }}">{{ $delta($kpi['key']) >= 0 ? '+' : '' }}{{ $delta($kpi['key']) }}% vs prior</div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="etd-grid-2 mb-5">
        <div class="etd-panel">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Visitors & sessions over time</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('trend')])
            </div>
            <div class="etd-chart-wrap"><canvas id="vaTrendMini"></canvas></div>
        </div>
        <div class="etd-panel">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">New vs returning</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('new-returning')])
            </div>
            <div class="etd-chart-wrap sm"><canvas id="vaNewReturningMini"></canvas></div>
        </div>
    </div>

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
            <h2 class="etd-panel-title">Top visitors by stay</h2>
            @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('visitors'), 'viewLabel' => 'View all'])
        </div>
        <table class="etd-table w-full">
            <thead><tr><th>Visitor</th><th class="etd-num">Sessions</th><th class="etd-num">Total stay</th><th>Last active</th></tr></thead>
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
                        <td class="etd-num">{{ $visitor['total_stay_label'] }}</td>
                        <td>{{ TrackerTime::toLocal($visitor['last_active_at'])?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-500 py-8">No visitors in this window.</td></tr>
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
