@extends('layouts.app')

@section('title', 'CTR Summary')

@section('content')
    @php
        $sortableColumns = [
            'ctr' => 'CTR',
            'ctr_average' => 'CTR average',
            'click' => 'Click',
            'impression' => 'Impression',
        ];
        $sortUrl = function (string $column) use ($sortBy, $sortDir, $data): string {
            $nextDir = ($sortBy === $column && $sortDir === 'desc') ? 'asc' : 'desc';

            return request()->fullUrlWithQuery(array_filter([
                ...($data['filterQuery'] ?? []),
                'sort' => $column,
                'dir' => $nextDir,
                'page' => null,
            ], fn ($value) => $value !== null && $value !== []));
        };
        $categoryQuery = array_values(array_filter(
            array_map('strval', (array) request('category', [])),
            fn (string $value) => $value !== '' && $value !== '__all__',
        ));
        $departmentQuery = array_values(array_filter(
            array_map('strval', (array) request('department', [])),
            fn (string $value) => $value !== '' && $value !== '__all__',
        ));
        $hasCatalogFilters = $departmentQuery !== [] || $categoryQuery !== [];
        $filtersActive = $search !== '' || $hasCatalogFilters || $sortBy !== 'ctr' || $sortDir !== 'desc'
            || ($data['period'] ?? '24h') !== '24h';
        $ctrPeriodLabel = match ($data['period'] ?? '24h') {
            'yesterday' => 'Yesterday',
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            'custom' => 'Custom range',
            default => 'Today',
        };
        $ctrProductTotal = (int) $summaries->total();
    @endphp

    <div id="ecom-tracker-dashboard-content" class="etd-page etd-page--product-performance-list etd-page--ctr-summary">

        @include('ecommerce.ctr_summary.filter-drawer', ['search' => $search])

        <header class="etd-page-header">
            <div class="etd-page-header-bar">
                <div class="etd-page-header-main">
                    <div class="etd-page-header-left-stack etd-ctr-summary-header">
                        <h1 class="etd-ctr-summary-page-title text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 m-0">
                            <span class="w-8 h-8 bg-accent-400/15 rounded-lg flex items-center justify-center shrink-0" aria-hidden="true">
                                <svg class="w-4 h-4 text-accent-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16l4-5 4 3 5-7"/>
                                </svg>
                            </span>
                            CTR Summary
                        </h1>
                        <p class="etd-ctr-summary-page-caption text-[12px] leading-snug text-slate-500 dark:text-slate-400 mt-0.5 mb-0 pl-10">
                            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $ctrPeriodLabel }}</span>
                            <span class="text-slate-400 dark:text-slate-500">({{ $data['rangeLabel'] ?? '' }})</span>
                            <span class="text-slate-300 dark:text-slate-600" aria-hidden="true"> · </span>
                            {{ number_format($ctrProductTotal) }} {{ $ctrProductTotal === 1 ? 'product' : 'products' }}
                        </p>
                    </div>
                </div>
                @if (! empty($data['productSorting']))
                    @php
                        $sortFromStore = (bool) ($data['productSorting']['fromStoreApi'] ?? false);
                        $sortCtrDays = (int) $data['productSorting']['ctrDays'];
                        $sortMinClicks = (int) $data['productSorting']['minClicks'];
                        $sortRulesLabel = "Product sort: {$sortCtrDays}-day CTR window, minimum {$sortMinClicks} clicks"
                            .($sortFromStore ? ' (from store settings)' : ' (config fallback)');
                    @endphp
                    <div class="etd-ctr-summary-header-center etd-print-hide">
                        <p class="etd-ctr-summary-sort-rules"
                           tabindex="0"
                           role="note"
                           aria-label="{{ $sortRulesLabel }}">
                            <span class="etd-ctr-summary-sort-rules__label">Product sort</span>
                            <span class="etd-ctr-summary-sort-rules__values">
                                = {{ $sortCtrDays }} - Day
                                <span class="etd-ctr-summary-sort-rules__sep" aria-hidden="true">||</span>
                                <span class="etd-ctr-summary-sort-rules__label">Min clicks</span> = {{ $sortMinClicks }}
                            </span>
                            @unless ($sortFromStore)
                                <span class="etd-ctr-summary-sort-rules__fallback">Config fallback</span>
                            @endunless
                        </p>
                    </div>
                @endif
                <div class="etd-page-header-toolbar">
                    <div class="etd-header-toolbar-row">
                        <div class="etd-header-period-nav etd-print-hide">
                            @if ($data['prevUrl'] ?? null)
                                <a href="{{ $data['prevUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Previous period" title="Previous period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></a>
                            @else
                                <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true" aria-label="Previous period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></span>
                            @endif
                            <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
                                @foreach (['24h' => 'Today', 'yesterday' => 'Yesterday', '7d' => '7d', '30d' => '30d'] as $periodKey => $periodLabel)
                                    <a href="{{ ($data['periodPresetUrl'] ?? fn (string $key) => route('admin.ecommerce.ctr-summary.index', ['period' => $key]))($periodKey) }}"
                                       class="etd-segmented-btn {{ ($data['period'] ?? '24h') === $periodKey ? 'active' : '' }} no-underline">{{ $periodLabel }}</a>
                                @endforeach
                                <button type="button"
                                        class="etd-segmented-btn js-ecom-dashboard-period-custom-toggle {{ ($data['period'] ?? '24h') === 'custom' ? 'active' : '' }}"
                                        aria-label="Custom date range">Custom</button>
                            </div>
                            <div id="ecom-dashboard-header-custom-dates"
                                 class="etd-custom-dates etd-custom-dates--header @if(($data['period'] ?? '24h') !== 'custom') etd-custom-dates--closed @endif"
                                 data-etd-date-range-single>
                                <input type="text"
                                       class="etd-flatpickr-date-range f-input etd-date-input etd-date-input--range"
                                       data-default-from="{{ $data['dateFrom'] ?? '' }}"
                                       data-default-to="{{ $data['dateTo'] ?? '' }}"
                                       placeholder="Select date range"
                                       readonly
                                       aria-label="Custom date range">
                                <input type="hidden" data-range="from" id="ecom-dashboard-header-date-from" value="{{ $data['dateFrom'] ?? '' }}">
                                <input type="hidden" data-range="to" id="ecom-dashboard-header-date-to" value="{{ $data['dateTo'] ?? '' }}">
                                <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply js-ecom-dashboard-header-custom-apply">Apply</button>
                            </div>
                            @if ($data['nextUrl'] ?? null)
                                <a href="{{ $data['nextUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Next period" title="Next period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></a>
                            @else
                                <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true" aria-label="Next period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></span>
                            @endif
                        </div>
                        <div class="etd-header-toolbar-icon-group etd-header-toolbar-icon-group--actions">
                            @can('ecommerce.ctr_summary.export')
                                @if (filled($data['exportUrl'] ?? null))
                                    <a href="{{ $data['exportUrl'] }}"
                                       class="etd-header-btn etd-topbar-btn--export etd-print-hide no-underline"
                                       title="Export all rows in this view to Excel">
                                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="etd-header-btn-text">Export Excel</span>
                                    </a>
                                @endif
                            @endcan
                            @include('ecom_tracker.partials.header-reset-button', [
                                'url' => $data['resetUrl'],
                                'active' => $filtersActive,
                            ])
                            <button type="button"
                                id="ecom-dashboard-filter-open"
                                class="etd-header-btn etd-header-btn--icon-only js-ecom-dashboard-filter-open {{ ($search !== '' || $hasCatalogFilters || $sortBy !== 'ctr' || $sortDir !== 'desc') ? 'etd-header-btn--filtered' : '' }}"
                                aria-label="Filters"
                                aria-expanded="false">
                                <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="etd-panel etd-print-unit etd-ctr-summary-list">
            <div class="etd-ctr-summary-table-scroll overflow-x-auto" tabindex="0" role="region" aria-label="CTR summary table">
                <table class="etd-table etd-table--ctr-summary w-full text-[13px] text-left border-collapse">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap">Department</th>
                            <th class="whitespace-nowrap">Category</th>
                            <th class="whitespace-nowrap">Product code</th>
                            @foreach ($sortableColumns as $sortKey => $sortLabel)
                                @php
                                    $isActiveSort = $sortBy === $sortKey;
                                @endphp
                                <th class="whitespace-nowrap text-right">
                                    <a href="{{ $sortUrl($sortKey) }}"
                                        class="inline-flex items-center justify-end gap-1.5 w-full transition-colors
                                            {{ $isActiveSort
                                                ? 'text-slate-700 dark:text-slate-200'
                                                : 'text-slate-400/90 dark:text-slate-500 hover:text-slate-500 dark:hover:text-slate-400' }}"
                                        @if ($isActiveSort) aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                        <span>{{ $sortLabel }}</span>
                                        <span class="inline-flex flex-col items-center justify-center leading-none gap-px shrink-0" aria-hidden="true">
                                            <svg @class([
                                                'w-3 h-3 transition-colors',
                                                'text-accent-600 dark:text-accent-300' => $isActiveSort && $sortDir === 'asc',
                                                'text-slate-300/80 dark:text-slate-600' => ! $isActiveSort || $sortDir !== 'asc',
                                            ]) viewBox="0 0 12 12" fill="currentColor"><path d="M6 2.5 10 8H2L6 2.5z"/></svg>
                                            <svg @class([
                                                'w-3 h-3 transition-colors',
                                                'text-accent-600 dark:text-accent-300' => $isActiveSort && $sortDir === 'desc',
                                                'text-slate-300/80 dark:text-slate-600' => ! $isActiveSort || $sortDir !== 'desc',
                                            ]) viewBox="0 0 12 12" fill="currentColor"><path d="M6 9.5 2 4h8L6 9.5z"/></svg>
                                        </span>
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summaries as $row)
                            <tr>
                                <td class="whitespace-nowrap">{{ $row['department'] }}</td>
                                <td class="whitespace-nowrap">{{ $row['category_display'] }}</td>
                                <td class="font-mono text-[12px] whitespace-nowrap">{{ $row['product_code'] }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['ctr'] * 100, 2) }}%</td>
                                <td class="text-right tabular-nums">{{ number_format($row['ctr_average'] * 100, 2) }}%</td>
                                <td class="text-right tabular-nums">{{ number_format($row['click']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['impression']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-slate-500 dark:text-slate-400">
                                    No CTR summary rows match your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="etd-product-performance-list__pager etd-print-hide">
                @include('ecom_activity.partials.pagination', [
                    'paginator' => $summaries,
                    'itemLabel' => 'rows',
                ])
            </div>
        </div>

    </div>
@endsection
