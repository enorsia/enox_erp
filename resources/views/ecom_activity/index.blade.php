@extends('layouts.app')

@section('title', 'User Activity')

@section('content')
@php
    use App\Support\TrackerTime;
    use Carbon\Carbon;

    $today = TrackerTime::localNow()->copy()->startOfDay();
    $todayStr = $today->toDateString();
    $dateFrom = request('date_from');
    $dateTo = request('date_to');
    $period = request('period', '24h');
    $activePreset = $period === 'all' ? 'all' : '24h';
    $rangeLabel = $rangeLabel ?? ($period === 'all' ? 'All sessions' : TrackerTime::todayPresetLabel());

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
        } elseif ($period === 'yesterday' && $from->equalTo($to)) {
            $activePreset = 'custom';
            $rangeLabel = TrackerTime::yesterdayPresetLabel();
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
        '24h' => route('admin.ecom-activity.index', array_merge($baseQuery, ['period' => '24h'])),
        'all' => route('admin.ecom-activity.index', array_merge($baseQuery, ['period' => 'all'])),
        '7d' => route('admin.ecom-activity.index', array_merge($baseQuery, [
            'period' => '7d',
            'date_from' => $today->copy()->subDays(6)->toDateString(),
            'date_to' => $todayStr,
        ])),
        '30d' => route('admin.ecom-activity.index', array_merge($baseQuery, [
            'period' => '30d',
            'date_from' => $today->copy()->subDays(29)->toDateString(),
            'date_to' => $todayStr,
        ])),
        '90d' => route('admin.ecom-activity.index', array_merge($baseQuery, [
            'period' => '90d',
            'date_from' => $today->copy()->subDays(89)->toDateString(),
            'date_to' => $todayStr,
        ])),
        default => route('admin.ecom-activity.index', $baseQuery),
    };

    $sidebarFilterCount = $sidebarFilterCount ?? \App\Support\EcomActivityFocus::activeFilterCount(request());
    $showCatalogFilters = $showCatalogFilters ?? in_array(request('focus'), ['products', 'categories'], true);
@endphp

<div class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-activity.index'),
        'resetUrl' => $filterResetUrl ?? route('admin.ecom-activity.index'),
        'showActivityFilters' => true,
        'activityFiltersIncludeDateRange' => true,
        'includeSessionSearch' => ! $showCatalogFilters,
        'sessionFiltersHeading' => $showCatalogFilters ? 'Session filters' : null,
        'showProductFilters' => $showCatalogFilters,
        'productFiltersHeading' => $showCatalogFilters ? 'Product / category filters' : null,
        'productFilterOptions' => $productFilterOptions ?? ['categories' => [], 'colors' => [], 'sizes' => []],
        'eventScenarioOptions' => $eventScenarioOptions ?? [],
        'productSortGroups' => $productSortGroups ?? [],
        'productActivityOptions' => $productActivityOptions ?? [],
        'currentProductSort' => request('sort_by', ''),
        'productCatalogShowSort' => false,
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
                    url.searchParams.set('period', 'custom');
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
                @if (! empty($breadcrumbs))
                    <div class="mb-2">
                        @include('ecom_tracker.partials.breadcrumbs', ['items' => $breadcrumbs])
                    </div>
                @endif
                <div class="flex items-center flex-wrap gap-x-2 gap-y-1">
                    <h1 class="etd-page-title">User activity</h1>
                    @if (filled($focusLabel ?? null) && empty($drillDownContext))
                        <span class="etd-header-sep" aria-hidden="true">·</span>
                        <span class="etd-page-range">{{ $focusLabel }}</span>
                    @endif
                    <span class="etd-header-sep" aria-hidden="true">·</span>
                    <span class="etd-page-range">{{ $rangeLabel }}</span>
                    <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
                    <div class="etd-page-meta">
                        @include('ecom_tracker.partials.timezone-notice')
                    </div>
                </div>
            </div>

            <div class="etd-page-header-right">
                <div class="etd-header-range-row">
                    @if (filled($backUrl ?? null))
                        @include('ecom_tracker.partials.header-back-button', [
                            'url' => $backUrl,
                            'label' => 'Dashboard',
                        ])
                    @endif
                    <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Session date range">
                        <a href="{{ $presetUrl('24h') }}" class="etd-segmented-btn {{ $activePreset === '24h' ? 'active' : '' }} no-underline" aria-label="{{ TrackerTime::todayPresetLabel() }}">{{ TrackerTime::todayPresetButtonLabel() }}</a>
                        <a href="{{ $presetUrl('7d') }}" class="etd-segmented-btn {{ $activePreset === '7d' ? 'active' : '' }} no-underline" aria-label="Last 7 days">7d</a>
                        <a href="{{ $presetUrl('30d') }}" class="etd-segmented-btn {{ $activePreset === '30d' ? 'active' : '' }} no-underline" aria-label="Last 30 days">30d</a>
                        <a href="{{ $presetUrl('90d') }}" class="etd-segmented-btn {{ $activePreset === '90d' ? 'active' : '' }} no-underline" aria-label="Last 90 days">90d</a>
                        <button type="button" class="etd-segmented-btn {{ $activePreset === 'custom' ? 'active' : '' }}" aria-label="Custom date range" @click="presetKey = 'custom'">Custom</button>
                    </div>
                </div>

                <div class="etd-header-actions">
                    @include('ecom_tracker.partials.header-reset-button', [
                        'url' => route('admin.ecom-activity.index'),
                        'active' => count(request()->except('page')) > 0,
                    ])
                    <button type="button" @click="drawerOpen = true" class="etd-header-btn etd-header-btn--icon {{ $sidebarFilterCount > 0 ? 'etd-header-btn--filtered' : '' }}" aria-label="Filters">
                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                        <span class="etd-header-btn-text">Filters</span>
                        @if ($sidebarFilterCount > 0)
                            <span class="etd-header-btn-badge">{{ $sidebarFilterCount }}</span>
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

        @if (! empty($drillDownContext))
            @include('ecom_activity.partials.drill-down-context', ['context' => $drillDownContext])
        @endif

        @if ($sidebarFilterCount > 0)
            <p class="etd-filter-active-note etd-filter-active-note--compact">Filters applied — combined with the section above.</p>
        @endif

        @if (! empty($filterChips))
            @include('ecom_tracker.partials.active-filter-chips', ['chips' => $filterChips ?? []])
        @endif

        @if (empty($drillDownContext) && ! empty($summaryCards))
            <div class="etd-kpi-grid mt-3 mb-1">
                @foreach ($summaryCards as $card)
                    @include('ecom_tracker.partials.ga4-kpi-card', [
                        'label' => $card['label'],
                        'value' => $card['value'],
                        'compact' => true,
                    ])
                @endforeach
            </div>
        @elseif (! empty($visitorQualitySummary) && ! ($hasFocus ?? false))
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
        @include('ecom_activity.partials.sessions-table', [
            'sessions' => $sessions,
            'focusColumns' => $focusColumns ?? [],
            'rowMetrics' => $rowMetrics ?? [],
            'emptyMessage' => $emptyMessage ?? 'No visitor sessions found.',
            'clearFocusUrl' => $clearFocusUrl ?? null,
            'hasFocus' => $hasFocus ?? false,
        ])
    </div>

    @include('layouts.pagination', ['paginator' => $sessions])
</div>
@endsection
