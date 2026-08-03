@extends('layouts.app')

@section('title', 'User Activity')

@section('content')
@php
    use App\Support\SessionTrafficAttribution;
    use App\Support\TrackerTime;
    use Carbon\Carbon;

    $today = TrackerTime::localNow()->copy()->startOfDay();
    $todayStr = $today->toDateString();
    $dateFrom = request('date_from');
    $dateTo = request('date_to');
    $period = request('period', '24h');
    $activePreset = $period === 'all' ? 'all' : '24h';
    $rangeLabel = $period === 'all' ? 'All sessions' : \App\Support\TrackerTime::todayPresetLabel();

    if ($period !== 'all' && filled($dateFrom) && filled($dateTo)) {
        $from = Carbon::parse($dateFrom, TrackerTime::timezone())->startOfDay();
        $to = Carbon::parse($dateTo, TrackerTime::timezone())->startOfDay();

        if ($from->equalTo($today->copy()->subDays(6)) && $to->equalTo($today)) {
            $activePreset = '7d';
            $rangeLabel = 'Last 7 days';
        } elseif ($from->equalTo($today->copy()->subDays(29)) && $to->equalTo($today)) {
            $activePreset = '30d';
            $rangeLabel = 'Last 30 days';
        } elseif ($from->equalTo($today->copy()->subDays(89)) && $to->equalTo($today)) {
            $activePreset = '90d';
            $rangeLabel = 'Last 90 days';
        } else {
            $activePreset = 'custom';
            $rangeLabel = $from->format('d M Y').' – '.$to->format('d M Y');
        }
    } elseif (filled($dateFrom) || filled($dateTo)) {
        $activePreset = 'custom';
        $rangeLabel = 'Custom range';
    }

    $baseQuery = request()->except(['date_from', 'date_to', 'page', 'period']);
    $presetUrl = fn (string $preset) => match ($preset) {
        '24h' => route('admin.ecom-activity.index', $baseQuery),
        'all' => route('admin.ecom-activity.index', array_merge($baseQuery, ['period' => 'all'])),
        '7d' => route('admin.ecom-activity.index', array_merge($baseQuery, [
            'date_from' => $today->copy()->subDays(6)->toDateString(),
            'date_to' => $todayStr,
        ])),
        '30d' => route('admin.ecom-activity.index', array_merge($baseQuery, [
            'date_from' => $today->copy()->subDays(29)->toDateString(),
            'date_to' => $todayStr,
        ])),
        '90d' => route('admin.ecom-activity.index', array_merge($baseQuery, [
            'date_from' => $today->copy()->subDays(89)->toDateString(),
            'date_to' => $todayStr,
        ])),
        default => route('admin.ecom-activity.index', $baseQuery),
    };

    $activeFilterCount = collect(['search', 'device_type', 'logged_in', 'has_order', 'country', 'visitor_type', 'utm_source', 'utm_medium'])
        ->filter(fn (string $key) => filled(request($key)))
        ->count();
@endphp

<div class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-activity.index'),
        'resetUrl' => route('admin.ecom-activity.index'),
        'showActivityFilters' => true,
        'filterOptionCounts' => $filterOptionCounts ?? [],
        'utmFilterState' => $utmFilterState ?? null,
    ])

    <header class="etd-page-header">
        <div class="etd-page-header-bar"
             x-data="{
                presetKey: '{{ $activePreset }}',
                dateFrom: '{{ $dateFrom ?? '' }}',
                dateTo: '{{ $dateTo ?? '' }}',
                applyCustom() {
                    const url = new URL(window.location.href);
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
                <h1 class="etd-page-title">User activity</h1>
                <span class="etd-header-sep" aria-hidden="true">·</span>
                <span class="etd-page-range">{{ $rangeLabel }}</span>
                <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
                <div class="etd-page-meta">
                    @include('ecom_tracker.partials.timezone-notice')
                </div>
            </div>

            <div class="etd-page-header-right">
                <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Session date range">
                    <a href="{{ $presetUrl('24h') }}" class="etd-segmented-btn {{ $activePreset === '24h' ? 'active' : '' }} no-underline" aria-label="{{ \App\Support\TrackerTime::todayPresetLabel() }}">{{ \App\Support\TrackerTime::todayPresetButtonLabel() }}</a>
                    <a href="{{ $presetUrl('7d') }}" class="etd-segmented-btn {{ $activePreset === '7d' ? 'active' : '' }} no-underline" aria-label="Last 7 days">7d</a>
                    <a href="{{ $presetUrl('30d') }}" class="etd-segmented-btn {{ $activePreset === '30d' ? 'active' : '' }} no-underline" aria-label="Last 30 days">30d</a>
                    <a href="{{ $presetUrl('90d') }}" class="etd-segmented-btn {{ $activePreset === '90d' ? 'active' : '' }} no-underline" aria-label="Last 90 days">90d</a>
                    <button type="button" class="etd-segmented-btn {{ $activePreset === 'custom' ? 'active' : '' }}" aria-label="Custom date range" @click="presetKey = 'custom'">Custom</button>
                </div>

                <div class="etd-header-actions">
                    @include('ecom_tracker.partials.header-reset-button', [
                        'url' => route('admin.ecom-activity.index'),
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
                       data-default="{{ $dateFrom ?? '' }}"
                       value="{{ $dateFrom ?? '' }}"
                       placeholder="From date"
                       readonly
                       class="etd-flatpickr-date f-input etd-date-input"
                       aria-label="From date">
                <span class="etd-custom-dates-sep">–</span>
                <input type="text"
                       x-model="dateTo"
                       data-range="to"
                       data-default="{{ $dateTo ?? '' }}"
                       value="{{ $dateTo ?? '' }}"
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

        @include('ecom_tracker.partials.active-filter-chips', ['chips' => $filterChips ?? []])

        @if (! empty($visitorQualitySummary))
            <p class="text-[12px] text-slate-500 dark:text-slate-400 mt-2 mb-0">
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ number_format($visitorQualitySummary['real_shoppers']) }}</span> real visitors ·
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ number_format($visitorQualitySummary['automated_traffic']) }}</span> automated ·
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ number_format($visitorQualitySummary['not_classified']) }}</span> not classified
                @can('ecom_tracker.bot_traffic.index')
                    · <a href="{{ route('admin.ecom-tracker.bot-traffic') }}" class="text-accent-500 no-underline hover:underline">View bot traffic details</a>
                @endcan
            </p>
        @endif
    </header>

    <div class="etd-panel">
        <div class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--activity">
            <table class="etd-table etd-table--activity w-full">
                <thead>
                    <tr>
                        <th class="etd-col-session">Session</th>
                        <th class="etd-col-user">User</th>
                        <th class="etd-col-trust">
                            @include('ecom_tracker.partials.column-header-with-tip', [
                                'label' => 'Visitor trust',
                                'tip' => 'Whether this session looks like a real visitor, automated traffic, or could not be checked',
                            ])
                        </th>
                        <th>Device</th>
                        <th>IP</th>
                        <th class="etd-num">Order</th>
                        <th class="etd-num">Actions</th>
                        <th>Duration</th>
                        <th>Last active</th>
                        <th class="etd-col-action">View</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        @php($traffic = SessionTrafficAttribution::listRowSummary($session))
                        <tr>
                            <td class="etd-col-session">
                                @include('ecom_tracker.partials.session-id-chip', ['sessionId' => $session->session_id])
                                <div class="etd-subtle mt-0.5">{{ TrackerTime::formatFromStorage($session->created_at) }}</div>
                                @include('ecom_tracker.partials.session-traffic-lines', [
                                    'source' => $traffic['source'],
                                    'utm' => $traffic['utm'],
                                    'referer' => $traffic['referer'],
                                ])
                            </td>
                            <td class="etd-col-user">
                                @include('ecom_tracker.partials.session-identity', ['session' => $session])
                            </td>
                            <td class="etd-col-trust">
                                @include('ecom_tracker.partials.visitor-classification-badge', ['session' => $session, 'mode' => 'compact'])
                            </td>
                            <td>
                                {{ ucfirst($session->device_type ?? '—') }}
                                <div class="etd-subtle">{{ $session->browser }} · {{ $session->os }}</div>
                            </td>
                            <td>{{ $session->botContext?->client_ip ?? $session->ip ?? '—' }}</td>
                            <td class="etd-num">
                                @if (($session->order_qty ?? 0) > 0)
                                    {{ number_format($session->order_qty) }}
                                @else
                                    <span class="etd-subtle">—</span>
                                @endif
                            </td>
                            <td class="etd-num">{{ $session->actions_count }}</td>
                            <td>{{ format_duration((int) ($session->session_duration_seconds ?? 0)) }}</td>
                            <td>{{ TrackerTime::diffForHumansLatestActivity($session->updated_at, $session->last_active_at, $session->created_at) ?? '—' }}</td>
                            <td class="etd-col-action">
                                @can('ecom_tracker.activity.show')
                                    <a href="{{ \App\Support\EcomTrackerViewData::activityShowUrl($session->session_id) }}" class="etd-link">View session</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-slate-500 py-10">No visitor sessions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('layouts.pagination', ['paginator' => $sessions])
</div>
@endsection
