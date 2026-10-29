@extends('layouts.app')

@section('title', 'CTR Summary')

@section('content')
    @php
        $sortableColumns = [
            'ctr' => 'CTR',
            'ctr_average' => 'CTR average',
            'click' => 'Click',
            'impression' => 'Impression',
            'sold_7_days' => '7 days sold',
            'total_sold' => 'Total sold',
        ];
        $sortUrl = function (string $column) use ($sortBy, $sortDir, $search): string {
            $nextDir = ($sortBy === $column && $sortDir === 'desc') ? 'asc' : 'desc';

            return request()->fullUrlWithQuery([
                'sort' => $column,
                'dir' => $nextDir,
                'search' => $search !== '' ? $search : null,
                'department' => request('department') ?: null,
                'category' => request('category') ?: null,
                'page' => null,
            ]);
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
        $filtersActive = $search !== '' || $hasCatalogFilters || $sortBy !== 'ctr' || $sortDir !== 'desc';
    @endphp

    <div id="ecom-tracker-dashboard-content" class="etd-page etd-page--product-performance-list etd-page--ctr-summary">

        @include('ecommerce.ctr_summary.filter-drawer', ['search' => $search])

        <header class="etd-page-header">
            <div class="etd-page-header-bar">
                <div class="etd-page-header-main">
                    <div class="etd-page-header-left-stack">
                        <h1 class="etd-page-title">CTR Summary</h1>
                        <div class="etd-page-meta">
                            <span class="etd-meta-item">Rolled-up category CTR</span>
                        </div>
                        <p class="etd-page-range mb-0">
                            {{ number_format($summaries->total()) }} products in this view
                        </p>
                    </div>
                </div>
                <div class="etd-page-header-toolbar">
                    <div class="etd-header-toolbar-row">
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
                                class="etd-header-btn etd-header-btn--icon-only js-ecom-dashboard-filter-open {{ ($search !== '' || $hasCatalogFilters) ? 'etd-header-btn--filtered' : '' }}"
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
                                <td class="text-right tabular-nums">{{ number_format($row['sold_7_days']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($row['total_sold']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-10 text-center text-slate-500 dark:text-slate-400">
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
