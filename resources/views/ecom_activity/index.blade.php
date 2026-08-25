@extends('layouts.app')

@section('title', 'User Activity')

@section('content')
@php
    $period = $period ?? request('period', '24h');
    $activePreset = match ($period) {
        'yesterday', '7d', '30d', 'custom' => $period,
        default => '24h',
    };
    $basePreset = in_array($period, ['24h', 'yesterday', '7d', '30d'], true) ? $period : '24h';
    $baseQuery = request()->except(['date_from', 'date_to', 'period', 'page']);
    $dateFrom = $dateFrom ?? request('date_from', '');
    $dateTo = $dateTo ?? request('date_to', '');
    $rangeLabel = $rangeLabel ?? ($range['label'] ?? '');

    $sidebarFilterCount = $sidebarFilterCount ?? \App\Support\EcomActivityFocus::activeFilterCount(request());
    $showCatalogFilters = $showCatalogFilters ?? in_array(request('focus'), ['products', 'categories'], true);
    $showProductCatalogExtras = \App\Support\EcomActivityFocus::showProductCatalogExtrasInDrawer(request());
@endphp

<div class="etd-page etd-page--activity" id="ecom-activity-page-content" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-activity.index'),
        'resetUrl' => $filterResetUrl ?? route('admin.ecom-activity.index'),
        'showActivityFilters' => true,
        'activityFiltersIncludeDateRange' => false,
        'preservePeriodParams' => true,
        'period' => $period,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'drawerWide' => true,
        'includeVisitorTrust' => true,
        'includeSessionSearch' => \App\Support\EcomActivityFocus::showActivitySearchInDrawer(request()),
        'showProductFilters' => $showProductCatalogExtras,
        'productFiltersHeading' => $showProductCatalogExtras ? 'Additional product filters' : null,
        'productFilterOptions' => $productFilterOptions ?? ['categories' => [], 'colors' => [], 'sizes' => []],
        'eventScenarioOptions' => $eventScenarioOptions ?? [],
        'productSortGroups' => $productSortGroups ?? [],
        'productActivityOptions' => $productActivityOptions ?? [],
        'currentProductSort' => request('sort_by', ''),
        'productCatalogShowSort' => false,
        'filterOptionCounts' => $filterOptionCounts ?? [],
        'utmFilterState' => $utmFilterState ?? null,
        'categoryFilterOptions' => $categoryFilterOptions ?? ['departments' => [], 'categories_by_department' => []],
    ])

    <header class="etd-page-header">
        <div class="etd-page-header-bar"
             x-data="{
                presetKey: '{{ $activePreset }}',
                basePreset: '{{ $basePreset }}',
                dateFrom: '{{ $dateFrom }}',
                dateTo: '{{ $dateTo }}',
                toggleCustom() {
                    this.presetKey = this.presetKey === 'custom' ? this.basePreset : 'custom';
                },
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
                    @if (filled($focusLabel ?? null) && empty($activityListContext ?? $drillDownContext ?? null))
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
                @if (filled($backUrl ?? null))
                    @include('ecom_tracker.partials.header-back-button', [
                        'url' => $backUrl,
                        'label' => 'Dashboard',
                    ])
                @endif

                @include('ecom_tracker.partials.dashboard-period-controls', [
                    'baseQuery' => $baseQuery,
                    'range' => $range,
                    'period' => $period,
                    'routeName' => 'admin.ecom-activity.index',
                ])

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
                 data-etd-date-range
                 @if ($activePreset !== 'custom') style="display: none" @endif>
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

        @if (! empty($activityListContext ?? $drillDownContext ?? null))
            @include('ecom_activity.partials.drill-down-context', ['context' => $activityListContext ?? $drillDownContext])
        @elseif (! empty($filterChips))
            @include('ecom_tracker.partials.active-filter-chips', ['chips' => $filterChips ?? []])
        @endif

        @if (empty($activityListContext ?? $drillDownContext ?? null) && ! empty($summaryCards))
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

    <div class="etd-activity-table-block" data-etd-activity-table-block>
        <div class="etd-panel">
            @include('ecom_activity.partials.activity-sort-toolbar')
            @include('ecom_activity.partials.sessions-table', [
                'sessions' => $sessions,
                'focusColumns' => $focusColumns ?? [],
                'rowMetrics' => $rowMetrics ?? [],
                'emptyMessage' => $emptyMessage ?? 'No visitor sessions found.',
                'clearFocusUrl' => $clearFocusUrl ?? null,
                'hasFocus' => $hasFocus ?? false,
            ])
        </div>

        <div class="etd-activity-pagination">
            @include('layouts.pagination', ['paginator' => $sessions])
        </div>
    </div>
</div>
@endsection
