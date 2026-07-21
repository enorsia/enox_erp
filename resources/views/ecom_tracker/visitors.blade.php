@extends('layouts.app')

@section('title', 'Visitor Analytics')

@section('content')
@php
    use App\Support\TrackerTime;
    $a = $analytics;
    $window = $filters['window'] ?? '24h';
    $hasCustomRange = filled($filters['datetime_from'] ?? null) && filled($filters['datetime_to'] ?? null);
    $activeFilterCount = $hasCustomRange ? 0 : ((request()->has('window') && ! in_array($window, ['24h', '7d', '30d', '90d'], true)) ? 1 : 0);
    $datetimeFromValue = filled($filters['datetime_from'] ?? null) ? TrackerTime::toLocal($filters['datetime_from'])?->format('Y-m-d\TH:i') : '';
    $datetimeToValue = filled($filters['datetime_to'] ?? null) ? TrackerTime::toLocal($filters['datetime_to'])?->format('Y-m-d\TH:i') : '';
    $presetWindows = ['3h' => '3 hours', '6h' => '6 hours', '12h' => '12 hours', '24h' => '24 hours', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', '1y' => '1 year'];
    $back = urlencode(request()->fullUrl());
    $detailLink = fn (string $section) => route('admin.ecom-tracker.visitors.details', $section).'?'.http_build_query(array_merge(request()->only(['window', 'datetime_from', 'datetime_to']), ['back' => $back]));
    $activityLink = fn (string $visitorId) => route('admin.ecom-activity.index', ['search' => $visitorId]);
    $summary = $a['summary'];
    $activeWindow = $hasCustomRange ? 'custom' : $window;
    $exportQuery = array_filter(request()->only(['window', 'datetime_from', 'datetime_to']), fn ($value) => filled($value));
    $exportUrl = route('admin.ecom-tracker.visitors.export', $exportQuery);
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

    <header class="etd-page-header">
        <div class="etd-page-header-bar"
             x-data="{
                windowKey: '{{ $activeWindow }}',
                datetimeFrom: '{{ $datetimeFromValue }}',
                datetimeTo: '{{ $datetimeToValue }}',
                apply(preset) {
                    if (preset === 'custom') {
                        this.windowKey = 'custom';
                        return;
                    }
                    const url = new URL(window.location.href);
                    url.searchParams.set('window', preset);
                    url.searchParams.delete('datetime_from');
                    url.searchParams.delete('datetime_to');
                    window.location.href = url.toString();
                },
                applyCustom() {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('window');
                    url.searchParams.set('datetime_from', this.datetimeFrom);
                    url.searchParams.set('datetime_to', this.datetimeTo);
                    window.location.href = url.toString();
                }
             }">
            <div class="etd-page-header-left">
                <h1 class="etd-page-title">Visitor analytics</h1>
                <span class="etd-header-sep" aria-hidden="true">·</span>
                <span class="etd-page-range">{{ $filters['window_label'] ?? 'Last 24 hours' }}</span>
                <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
                <div class="etd-page-meta">
                    @include('ecom_tracker.partials.timezone-notice')
                    @include('ecom_tracker.partials.analytics-cache-notice', ['analytics_cache' => $a['analytics_cache'] ?? null])
                </div>
            </div>

            <div class="etd-page-header-right">
                <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Time window">
                    @foreach (['24h' => '24 hours', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $windowKey => $windowLabel)
                        <button type="button" class="etd-segmented-btn {{ $activeWindow === $windowKey ? 'active' : '' }}" aria-label="{{ $windowLabel }}" @click="apply('{{ $windowKey }}')">{{ $windowKey }}</button>
                    @endforeach
                    <button type="button" class="etd-segmented-btn {{ $activeWindow === 'custom' ? 'active' : '' }}" aria-label="Custom date range" @click="apply('custom')">Custom</button>
                </div>

                <div class="etd-header-actions">
                    <button type="button" @click="drawerOpen = true" class="etd-header-btn etd-header-btn--icon {{ $activeFilterCount > 0 ? 'etd-header-btn--filtered' : '' }}" aria-label="Filters">
                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                        <span class="etd-header-btn-text">Filters</span>
                        @if ($activeFilterCount > 0)
                            <span class="etd-header-btn-badge">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    <a href="{{ $exportUrl }}" class="etd-header-btn etd-header-btn--primary no-underline" title="Export">
                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l4-4m-4 4L8 11M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/></svg>
                        <span class="etd-header-btn-text">Export</span>
                    </a>
                </div>
            </div>

            <div x-show="windowKey === 'custom'"
                 x-collapse
                 x-effect="if (windowKey === 'custom') { $nextTick(() => window.refreshEtdFilterControls?.($el)) }"
                 class="etd-custom-dates etd-custom-dates--inline etd-date-range"
                 data-etd-date-range>
                <input type="text"
                       x-model="datetimeFrom"
                       data-range="from"
                       data-default="{{ $datetimeFromValue }}"
                       value="{{ $datetimeFromValue }}"
                       placeholder="From date & time"
                       readonly
                       class="etd-flatpickr-datetime f-input etd-date-input etd-date-input--datetime"
                       aria-label="From date and time">
                <span class="etd-custom-dates-sep">–</span>
                <input type="text"
                       x-model="datetimeTo"
                       data-range="to"
                       data-default="{{ $datetimeToValue }}"
                       value="{{ $datetimeToValue }}"
                       placeholder="To date & time"
                       readonly
                       class="etd-flatpickr-datetime f-input etd-date-input etd-date-input--datetime"
                       aria-label="To date and time">
                <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply" @click="applyCustom()">Apply</button>
            </div>
        </div>
    </header>

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
                        <td>{{ TrackerTime::toLocal($visitor['last_active_at'])?->diffForHumans() ?? '—' }}</td>
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
