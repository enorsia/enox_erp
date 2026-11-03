@extends('layouts.app')

@section('title', 'Ranking Products')

@section('content')
    @php
        $sortingSalesDays = max(1, (int) ($sortingSalesDays ?? 30));
        $settingDaysSoldLabel = $sortingSalesDays.' days sold';
        $metricColumnTips = [
            'click' => 'Total product clicks on the category grid (rollup window from tracking CTR summaries).',
            'impression' => 'How many times this product tile was counted in view on category listing pages (tracking_ctr_summaries total_impression rollup).',
            'ctr' => 'Click divided by impression for this placement. Ratio — multiply by 100 for %.',
            'ctr_average' => 'Category rollup CTR for this product placement in tracking_ctr_summaries.',
            'total_sold' => 'Total sold units from Enorsia product ranking settings.',
            'total_sold_rolling' => "Units sold in the last {$sortingSalesDays} days from site product sorting settings (Enorsia).",
        ];
        $statusColumnDefs = [
            ['key' => 'is_new_in', 'label' => 'New in', 'type' => 'flag'],
            ['key' => 'pin', 'label' => 'Pin Position', 'type' => 'pin'],
            ['key' => 'is_selling_fast', 'label' => 'Selling fast', 'type' => 'flag'],
            ['key' => 'is_stock_out', 'label' => 'Stock out', 'type' => 'flag'],
        ];
        $productTotal = (int) $rows->total();
        $filtersActive = ($search ?? '') !== '' || ($contextKey ?? '') !== '';
    @endphp

    <div id="ecom-tracker-dashboard-content" class="etd-page etd-page--product-performance-list etd-page--ctr-summary etd-page--ranking-products">
        <div id="ranking-products-page" class="hidden" aria-hidden="true"></div>

        @include('ecommerce.ranking_products.filter-drawer')

        <header class="etd-page-header">
            <div class="etd-page-header-bar">
                <div class="etd-page-header-main">
                    <div class="etd-page-header-left-stack etd-ctr-summary-header">
                        <h1 class="etd-ctr-summary-page-title text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 m-0">
                            <span class="w-8 h-8 bg-accent-400/15 rounded-lg flex items-center justify-center shrink-0" aria-hidden="true">
                                <svg class="w-4 h-4 text-accent-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h10M4 14h14M4 18h8"/>
                                </svg>
                            </span>
                            Product Ranking
                        </h1>
                        <p class="etd-ctr-summary-page-caption text-[12px] leading-snug text-slate-500 dark:text-slate-400 mt-0.5 mb-0 pl-10">
                            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $listingScopeLabel }}</span>
                            <span class="text-slate-300 dark:text-slate-600" aria-hidden="true"> · </span>
                            {{ number_format($productTotal) }} {{ $productTotal === 1 ? 'row' : 'rows' }}
                        </p>
                    </div>
                </div>
                <div class="etd-page-header-toolbar">
                    <div class="etd-header-toolbar-row">
                        <div class="etd-header-toolbar-icon-group etd-header-toolbar-icon-group--actions">
                            @can('ecommerce.ranking_products.export')
                                @if (filled($exportUrl ?? null))
                                    <a href="{{ $exportUrl }}"
                                       class="etd-header-btn etd-topbar-btn--export etd-print-hide no-underline"
                                       title="Export all rows in this view to Excel">
                                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="etd-header-btn-text">Export Excel</span>
                                    </a>
                                @endif
                            @endcan
                            @can('ecommerce.ctr_summary.index')
                                <a href="{{ route('admin.ecommerce.ctr-summary.index') }}"
                                   class="etd-header-btn etd-header-btn--icon no-underline etd-print-hide"
                                   title="CTR Summary">
                                    <span class="etd-header-btn-text">CTR Summary</span>
                                </a>
                            @endcan
                            @include('ecom_tracker.partials.header-reset-button', [
                                'url' => $resetUrl,
                                'active' => $filtersActive,
                            ])
                            <button type="button"
                                id="ecom-dashboard-filter-open"
                                class="etd-header-btn etd-header-btn--icon-only js-ecom-dashboard-filter-open {{ $filtersActive ? 'etd-header-btn--filtered' : '' }}"
                                aria-label="Filters"
                                aria-expanded="false">
                                <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        @if (filled($apiError ?? null))
            <div class="mx-4 mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100">
                {{ $apiError }}
            </div>
        @elseif (filled(session('error')))
            <div class="mx-4 mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100">
                {{ session('error') }}
            </div>
        @endif

        <div class="etd-panel etd-print-unit etd-ctr-summary-list">
            @include('ecommerce.ranking_products.context-selector')

            <div class="etd-ctr-summary-table-scroll overflow-x-auto" tabindex="0" role="region" aria-label="Ranking products table">
                <table class="etd-table etd-table--ctr-summary w-full text-[13px] border-collapse">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap etd-ctr-summary-col--label etd-ctr-summary-col--listing">Listing</th>
                            <th class="whitespace-nowrap etd-ctr-summary-col--label etd-ctr-summary-col--rank">Rank</th>
                            <th class="whitespace-nowrap etd-ctr-summary-col--label">Product code</th>
                            <th class="whitespace-nowrap etd-ctr-summary-col--num">
                                @include('ecom_tracker.partials.column-header-with-tip', [
                                    'label' => 'CTR',
                                    'tip' => $metricColumnTips['ctr'],
                                    'align' => 'center',
                                    'iconBefore' => true,
                                ])
                            </th>
                            <th class="whitespace-nowrap etd-ctr-summary-col--num">
                                @include('ecom_tracker.partials.column-header-with-tip', [
                                    'label' => 'CTR average',
                                    'tip' => $metricColumnTips['ctr_average'],
                                    'align' => 'center',
                                    'iconBefore' => true,
                                ])
                            </th>
                            <th class="whitespace-nowrap etd-ctr-summary-col--sold">
                                @include('ecom_tracker.partials.column-header-with-tip', [
                                    'label' => $settingDaysSoldLabel,
                                    'tip' => $metricColumnTips['total_sold_rolling'],
                                    'align' => 'center',
                                    'iconBefore' => true,
                                ])
                            </th>
                            <th class="whitespace-nowrap etd-ctr-summary-col--sold">
                                @include('ecom_tracker.partials.column-header-with-tip', [
                                    'label' => 'Total sold',
                                    'tip' => $metricColumnTips['total_sold'],
                                    'align' => 'center',
                                    'iconBefore' => true,
                                ])
                            </th>
                            <th class="whitespace-nowrap etd-ctr-summary-col--num">
                                @include('ecom_tracker.partials.column-header-with-tip', [
                                    'label' => 'Click',
                                    'tip' => $metricColumnTips['click'],
                                    'align' => 'center',
                                    'iconBefore' => true,
                                ])
                            </th>
                            <th class="whitespace-nowrap etd-ctr-summary-col--num">
                                @include('ecom_tracker.partials.column-header-with-tip', [
                                    'label' => 'Impression',
                                    'tip' => $metricColumnTips['impression'],
                                    'align' => 'center',
                                    'iconBefore' => true,
                                ])
                            </th>
                            @foreach ($statusColumnDefs as $statusCol)
                                <th class="whitespace-nowrap etd-ctr-summary-col--num text-slate-500 dark:text-slate-400">{{ $statusCol['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="etd-ctr-summary-row">
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--label etd-ctr-summary-col--listing" data-label="Listing">{{ $row['context_label'] }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--label etd-ctr-summary-col--rank tabular-nums" data-label="Rank">{{ number_format($row['serial_number']) }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--label etd-ctr-summary-col--product font-mono text-[12px]" data-label="Product code">{{ $row['product_code'] }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--metric etd-ctr-summary-col--num tabular-nums max-sm:!text-left sm:!text-center" data-label="CTR">{{ number_format($row['ctr'], 2, '.', '') }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--metric etd-ctr-summary-col--num tabular-nums max-sm:!text-left sm:!text-center" data-label="CTR average">{{ number_format($row['ctr_average'], 2, '.', '') }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--metric etd-ctr-summary-col--sold tabular-nums max-sm:!text-left sm:!text-center" data-label="{{ $settingDaysSoldLabel }}">{{ number_format($row['total_sold_rolling']) }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--metric etd-ctr-summary-col--sold tabular-nums max-sm:!text-left sm:!text-center" data-label="Total sold">{{ number_format($row['total_sold']) }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--metric etd-ctr-summary-col--num tabular-nums max-sm:!text-left sm:!text-center" data-label="Click">{{ number_format($row['click']) }}</td>
                                <td class="etd-ctr-summary-col etd-ctr-summary-col--metric etd-ctr-summary-col--num tabular-nums max-sm:!text-left sm:!text-center" data-label="Impression">{{ number_format($row['impression']) }}</td>
                                @foreach ($statusColumnDefs as $statusCol)
                                    <td class="etd-ctr-summary-col etd-ctr-summary-col--metric etd-ctr-summary-col--num tabular-nums max-sm:!text-left sm:!text-center" data-label="{{ $statusCol['label'] }}">
                                        @if ($statusCol['type'] === 'pin')
                                            @if (($row['pin_serial'] ?? null) !== null)
                                                <span class="text-emerald-600 dark:text-emerald-400">{{ number_format((int) $row['pin_serial']) }}</span>
                                            @else
                                                <span class="text-slate-400 dark:text-slate-500">—</span>
                                            @endif
                                        @elseif ((int) ($row[$statusCol['key']] ?? 0) === 1)
                                            <span class="text-emerald-600 dark:text-emerald-400">Yes</span>
                                        @else
                                            <span class="text-slate-400 dark:text-slate-500">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr class="etd-ctr-summary-empty">
                                <td colspan="13" class="py-10 text-center text-slate-500 dark:text-slate-400">
                                    No ranking products for this listing context.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="etd-product-performance-list__pager etd-print-hide">
                @include('ecommerce.ranking_products.pagination', [
                    'paginator' => $rows,
                    'itemLabel' => 'products',
                    'paginationQuery' => $paginationQuery ?? [],
                ])
            </div>
        </div>

    </div>
@endsection
