@extends('layouts.app')

@section('title', 'Style Sold')

@section('content')
    @php
        $defaultPeriod = \App\Http\Controllers\EcommerceDepartmentProductSalesController::DEFAULT_PERIOD;
        $metricSortColumns = [
            'total_qty' => 'Total qty',
            'total_sale' => 'Sold amount',
        ];
        $activePeriod = $data['period'] ?? $defaultPeriod;
        $rowTotal = $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? (int) $rows->total() : 0;
        $filtersActive = ($search ?? '') !== '' || ($departmentIds ?? []) !== [] || ($sortBy ?? 'total_sale') !== 'total_sale' || ($sortDir ?? 'desc') !== 'desc' || $activePeriod !== $defaultPeriod;
        $periodLabel = $data['periodLabel'] ?? 'Last 7 days';
        $sortUrl = function (string $column) use ($sortBy, $sortDir, $data): string {
            $nextDir = ($sortBy === $column && $sortDir === 'desc') ? 'asc' : 'desc';

            return request()->fullUrlWithQuery(array_filter([
                ...($data['filterQuery'] ?? []),
                'sort' => $column,
                'dir' => $nextDir,
                'page' => null,
            ], fn ($value) => $value !== null && $value !== []));
        };
    @endphp

    <div id="ecom-tracker-dashboard-content" class="etd-page etd-page--product-performance-list etd-page--ctr-summary etd-page--ctr-summary-index etd-page--style-sold">

        @include('ecommerce.department_product_sales.filter-drawer')

        <header class="etd-page-header">
            <div class="etd-page-header-bar">
                <div class="etd-page-header-main">
                    <div class="etd-page-header-left-stack etd-ctr-summary-header">
                        <h1 class="etd-ctr-summary-page-title text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 m-0">
                            <span class="w-8 h-8 bg-accent-400/15 rounded-lg flex items-center justify-center shrink-0" aria-hidden="true">
                                <svg class="w-4 h-4 text-accent-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h6"/>
                                </svg>
                            </span>
                            Style Sold
                        </h1>
                        <p class="etd-ctr-summary-page-caption text-[12px] leading-snug text-slate-500 dark:text-slate-400 mt-0.5 mb-0 pl-10">
                            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $periodLabel }}</span>
                            <span class="text-slate-400 dark:text-slate-500">({{ $data['rangeLabel'] ?? '' }})</span>
                            <span class="text-slate-300 dark:text-slate-600" aria-hidden="true"> · </span>
                            {{ number_format($rowTotal) }} {{ $rowTotal === 1 ? 'row' : 'rows' }}
                            <span class="text-slate-300 dark:text-slate-600" aria-hidden="true"> · </span>
                            <span class="text-slate-500">Enorsia replica</span>
                        </p>
                    </div>
                </div>
                <div class="etd-page-header-toolbar">
                    <div class="etd-header-toolbar-row">
                        <div class="etd-header-period-nav etd-print-hide">
                            @if ($data['prevUrl'] ?? null)
                                <a href="{{ $data['prevUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Previous period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></a>
                            @endif
                            <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
                                @foreach ($data['periodPresets'] ?? \App\Http\Controllers\EcommerceDepartmentProductSalesController::PERIOD_PRESETS as $periodKey => $periodLabelBtn)
                                    <a href="{{ ($data['periodPresetUrl'] ?? fn (string $key) => '#')($periodKey) }}"
                                       class="etd-segmented-btn {{ $activePeriod === $periodKey ? 'active' : '' }} no-underline">{{ $periodLabelBtn }}</a>
                                @endforeach
                                <button type="button"
                                        class="etd-segmented-btn js-ecom-dashboard-period-custom-toggle {{ $activePeriod === 'custom' ? 'active' : '' }}"
                                        aria-label="Custom date range">Custom</button>
                            </div>
                            <div id="ecom-dashboard-header-custom-dates"
                                 class="etd-custom-dates etd-custom-dates--header @if($activePeriod !== 'custom') etd-custom-dates--closed @endif"
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
                                <a href="{{ $data['nextUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Next period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></a>
                            @endif
                        </div>
                        <div class="etd-header-toolbar-icon-group etd-header-toolbar-icon-group--actions">
                            @can('ecommerce.department_product_sales.export')
                                <a href="{{ $data['exportUrl'] ?? '#' }}"
                                   class="etd-header-btn etd-topbar-btn--export etd-print-hide no-underline"
                                   title="Export all rows in this view to Excel">
                                    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span class="etd-header-btn-text">Export Excel</span>
                                </a>
                            @endcan
                            @include('ecom_tracker.partials.header-reset-button', [
                                'url' => $data['resetUrl'],
                                'active' => $filtersActive,
                            ])
                            <button type="button"
                                id="ecom-dashboard-filter-open"
                                class="etd-header-btn etd-header-btn--icon-only js-ecom-dashboard-filter-open {{ $filtersActive ? 'etd-header-btn--filtered' : '' }}"
                                aria-label="Filters">
                                <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        @if (filled($connectionError ?? null))
            <div class="mx-4 mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100">
                {{ $connectionError }}
            </div>
        @endif

        <div class="etd-panel etd-print-unit etd-ctr-summary-list">
            <div class="etd-ctr-summary-table-scroll overflow-x-auto" tabindex="0">
                <table class="etd-table etd-table--ctr-summary w-full text-[13px] border-collapse">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap etd-ctr-summary-col--label">Department</th>
                            <th class="whitespace-nowrap etd-ctr-summary-col--label">Product code</th>
                            @foreach ($metricSortColumns as $sortKey => $sortLabel)
                                @php
                                    $isActiveSort = ($sortBy ?? 'total_sale') === $sortKey;
                                @endphp
                                <th class="whitespace-nowrap etd-ctr-summary-col--num">
                                    <span class="inline-flex items-center justify-center gap-0.5 w-full min-w-0">
                                        <span class="text-slate-600 dark:text-slate-300 font-medium">{{ $sortLabel }}</span>
                                        <a href="{{ $sortUrl($sortKey) }}"
                                            class="inline-flex flex-col items-center justify-center leading-none gap-px shrink-0 p-0.5 rounded transition-colors
                                                {{ $isActiveSort
                                                    ? 'text-slate-700 dark:text-slate-200'
                                                    : 'text-slate-400/90 dark:text-slate-500 hover:text-slate-500 dark:hover:text-slate-400' }}"
                                            aria-label="Sort by {{ $sortLabel }}"
                                            @if ($isActiveSort) aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                            <svg @class([
                                                'w-3 h-3 transition-colors',
                                                'text-accent-600 dark:text-accent-300' => $isActiveSort && $sortDir === 'asc',
                                                'text-slate-300/80 dark:text-slate-600' => ! $isActiveSort || $sortDir !== 'asc',
                                            ]) viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 2.5 10 8H2L6 2.5z"/></svg>
                                            <svg @class([
                                                'w-3 h-3 transition-colors',
                                                'text-accent-600 dark:text-accent-300' => $isActiveSort && $sortDir === 'desc',
                                                'text-slate-300/80 dark:text-slate-600' => ! $isActiveSort || $sortDir !== 'desc',
                                            ]) viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 9.5 2 4h8L6 9.5z"/></svg>
                                        </a>
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows ?? [] as $row)
                            <tr class="etd-ctr-summary-row">
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--label etd-ctr-summary-col--dept" data-label="Department">{{ $row['department_name'] }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--label etd-ctr-summary-col--product font-mono text-[12px]" data-label="Product code">{{ $row['product_code'] }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--metric etd-ctr-summary-col--num tabular-nums max-sm:!text-left sm:!text-center" data-label="Total qty">{{ number_format((int) $row['total_qty']) }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--metric etd-ctr-summary-col--num tabular-nums max-sm:!text-left sm:!text-center" data-label="Sold amount">£{{ number_format((float) $row['total_sale'], 2) }}</td>
                            </tr>
                        @empty
                            <tr class="etd-ctr-summary-empty">
                                <td colspan="4" class="etd-ctr-summary-empty__cell">
                                    No sold rows match this date range or filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="etd-product-performance-list__pager etd-print-hide">
                @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                    @include('ecom_activity.partials.pagination', [
                        'paginator' => $rows,
                        'itemLabel' => 'rows',
                    ])
                @endif
            </div>
        </div>
    </div>
@endsection