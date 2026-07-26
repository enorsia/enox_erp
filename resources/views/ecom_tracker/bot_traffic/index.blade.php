@extends('layouts.app')

@section('title', 'Bot Traffic')

@section('content')
@php
    use App\Support\TrackerTime;

    $summary = $report['summary'];
    $trend = $report['trend'];
    $range = $report['range'];
    $activityLink = $page['activityLink'];

    $period = $filters['period'] ?? '24h';
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $activePreset = in_array($period, ['24h', '7d', '30d', '90d'], true) ? $period : '24h';

    if ($period === 'custom' || (filled($dateFrom) && filled($dateTo))) {
        $activePreset = 'custom';
    }

    $baseQuery = request()->except(['date_from', 'date_to', 'page', 'period']);
    $presetUrl = fn (string $preset) => match ($preset) {
        '24h' => route('admin.ecom-tracker.bot-traffic', array_merge($baseQuery, ['period' => '24h'])),
        '7d' => route('admin.ecom-tracker.bot-traffic', array_merge($baseQuery, ['period' => '7d'])),
        '30d' => route('admin.ecom-tracker.bot-traffic', array_merge($baseQuery, ['period' => '30d'])),
        '90d' => route('admin.ecom-tracker.bot-traffic', array_merge($baseQuery, ['period' => '90d'])),
        default => route('admin.ecom-tracker.bot-traffic', $baseQuery),
    };
@endphp

<div id="ecom-tracker-bot-traffic-content" class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-tracker.bot-traffic'),
        'resetUrl' => route('admin.ecom-tracker.bot-traffic'),
        'showActivityFilters' => true,
        'activityFiltersIncludeDateRange' => false,
        'includeVisitorTrust' => false,
        'preservePeriodParams' => true,
        'period' => $period,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
    ])

    <header class="etd-page-header">
        <div class="etd-page-header-bar"
             x-data="{
                presetKey: '{{ $activePreset }}',
                dateFrom: '{{ $dateFrom }}',
                dateTo: '{{ $dateTo }}',
                applyCustom() {
                    const url = new URL(window.location.href);
                    url.searchParams.set('period', 'custom');
                    url.searchParams.delete('page');
                    if (this.dateFrom) {
                        url.searchParams.set('date_from', this.dateFrom);
                    } else {
                        url.searchParams.delete('date_from');
                    }
                    if (this.dateTo) {
                        url.searchParams.set('date_to', this.dateTo);
                    } else {
                        url.searchParams.delete('date_to');
                    }
                    window.location.href = url.toString();
                }
             }">
            <div class="etd-page-header-left">
                <h1 class="etd-page-title">Bot traffic</h1>
                <span class="etd-header-sep" aria-hidden="true">·</span>
                <span class="etd-page-range">{{ $range['label'] }}</span>
                <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
                <div class="etd-page-meta">
                    @include('ecom_tracker.partials.timezone-notice')
                </div>
            </div>

            <div class="etd-page-header-right">
                <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
                    <a href="{{ $presetUrl('24h') }}" class="etd-segmented-btn {{ $activePreset === '24h' ? 'active' : '' }} no-underline" aria-label="Last 24 hours">24h</a>
                    <a href="{{ $presetUrl('7d') }}" class="etd-segmented-btn {{ $activePreset === '7d' ? 'active' : '' }} no-underline" aria-label="Last 7 days">7d</a>
                    <a href="{{ $presetUrl('30d') }}" class="etd-segmented-btn {{ $activePreset === '30d' ? 'active' : '' }} no-underline" aria-label="Last 30 days">30d</a>
                    <a href="{{ $presetUrl('90d') }}" class="etd-segmented-btn {{ $activePreset === '90d' ? 'active' : '' }} no-underline" aria-label="Last 90 days">90d</a>
                    <button type="button" class="etd-segmented-btn {{ $activePreset === 'custom' ? 'active' : '' }}" aria-label="Custom date range" @click="presetKey = 'custom'">Custom</button>
                </div>

                <div class="etd-header-actions">
                    @include('ecom_tracker.partials.header-reset-button', [
                        'url' => route('admin.ecom-tracker.bot-traffic'),
                        'active' => count(request()->except('page')) > 0,
                    ])
                    <button type="button" @click="drawerOpen = true" class="etd-header-btn etd-header-btn--icon {{ $activeFilterCount > 0 ? 'etd-header-btn--filtered' : '' }}" aria-label="Filters">
                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                        <span class="etd-header-btn-text">Filters</span>
                        @if ($activeFilterCount > 0)
                            <span class="etd-header-btn-badge">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                </div>
            </div>

            <div x-show="presetKey === 'custom'"
                 x-collapse
                 x-effect="if (presetKey === 'custom') { $nextTick(() => window.refreshEtdFilterControls?.($el)) }"
                 class="etd-custom-dates etd-custom-dates--inline etd-date-range"
                 data-etd-date-range>
                <input type="text"
                       x-model="dateFrom"
                       data-range="from"
                       data-default="{{ $dateFrom }}"
                       value="{{ $dateFrom }}"
                       placeholder="From date"
                       readonly
                       class="etd-flatpickr-date f-input etd-date-input"
                       aria-label="From date">
                <span class="etd-custom-dates-sep">–</span>
                <input type="text"
                       x-model="dateTo"
                       data-range="to"
                       data-default="{{ $dateTo }}"
                       value="{{ $dateTo }}"
                       placeholder="To date"
                       readonly
                       class="etd-flatpickr-date f-input etd-date-input"
                       aria-label="To date">
                <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply" @click="applyCustom()">Apply</button>
            </div>
        </div>

        @if ($activeFilterCount > 0)
            <p class="etd-filter-active-note etd-filter-active-note--compact">Filters applied — open Filters to change or reset.</p>
        @endif

        <p class="text-[12px] text-slate-500 dark:text-slate-400 mt-2 mb-0 max-w-3xl">
            Automated sessions only — crawlers, scripts, and other non-human traffic detected on your store.
        </p>

        @include('ecom_tracker.partials.active-filter-chips', ['chips' => $chips])
    </header>

    <div class="etd-kpi-grid mb-5">
        @foreach ([
            ['key' => 'automated_traffic', 'label' => 'Automated sessions'],
            ['key' => 'bot_countries', 'label' => 'Countries detected'],
        ] as $kpi)
            @php $m = $summary[$kpi['key']]; @endphp
            @include('ecom_tracker.partials.ga4-kpi-card', [
                'label' => $kpi['label'],
                'value' => $m['current'],
                'delta_pct' => $m['delta_pct'],
                'delta_direction' => $m['delta_direction'],
                'delta_label' => $m['delta_label'],
                'sparkline' => $m['sparkline'] ?? [],
                'comparison_label' => $m['comparison_label'] ?? null,
            ])
        @endforeach
    </div>

    <div class="etd-panel mb-5">
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Automated traffic trend</h2>
            @can('ecom_tracker.activity.index')
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $activityLink, 'viewLabel' => 'View all'])
            @endcan
        </div>
        <div class="etd-chart-wrap">
            <canvas id="botTrafficTrendChart"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
        @include('ecom_tracker.partials.breakdown-table', [
            'title' => 'Top detection reasons',
            'rows' => $report['reason_breakdown'],
        ])
        @include('ecom_tracker.partials.breakdown-table', [
            'title' => 'Automated traffic by country',
            'rows' => $report['country_breakdown'],
        ])
    </div>

    <div class="etd-panel">
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Automated traffic sessions</h2>
            @can('ecom_tracker.activity.index')
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $activityLink, 'viewLabel' => 'View all'])
            @endcan
        </div>
        <div class="etd-table-scroll">
            <table class="etd-table w-full">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Detection</th>
                        <th>Location</th>
                        <th>Device</th>
                        <th>Last active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['sessions'] as $session)
                        <tr>
                            <td>
                                <span class="etd-chip" title="{{ $session->session_id }}">{{ Str::limit($session->session_id, 14) }}</span>
                            </td>
                            <td>
                                @include('ecom_tracker.partials.visitor-classification-badge', ['session' => $session, 'mode' => 'compact'])
                            </td>
                            <td>{{ $session->marketer_country_label ?? '—' }}</td>
                            <td>{{ ucfirst($session->device_type ?? '—') }}</td>
                            <td>{{ filled($session->last_active_at) ? TrackerTime::formatIdleSince($session->last_active_at) : '—' }}</td>
                            <td>
                                @can('ecom_tracker.activity.show')
                                    <a href="{{ route('admin.ecom-activity.show', ['session' => $session->session_id, 'back' => urlencode(request()->fullUrl())]) }}"
                                       class="text-accent-500 text-[12px] no-underline hover:underline">View</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-slate-400 py-8">No automated traffic sessions in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $report['sessions']->links() }}</div>
    </div>
</div>

<script>
    window.botTrafficTrendData = @json($trend);
</script>
@endsection
