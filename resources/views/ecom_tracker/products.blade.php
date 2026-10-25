@extends('layouts.app')

@section('title', 'Product performance')

@php
    $activityLink = $data['activityLink'];
    $productTotals = $products['totals'];
    $productRows = $paginator->items();
    $productsRoute = 'admin.ecom-tracker.dashboard.products';
@endphp

@section('content')

<div id="ecom-tracker-dashboard-content" class="etd-page etd-page--product-performance-list">

@include('ecom_tracker.filter-drawer-products')

<header class="etd-page-header">
    <div class="etd-page-header-bar">
        <div class="etd-page-header-main">
            <div class="etd-page-header-left-stack">
                <h1 class="etd-page-title">Product performance</h1>
                <div class="etd-page-meta">
                    <span class="etd-meta-item">All times (store timezone)</span>
                </div>
                <p class="etd-page-range">{{ match ($data['period']) { 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom', default => 'Today' } }} ({{ $data['rangeLabel'] }})</p>
                @if ($productTotals['product_count'] > 0)
                    <p class="etd-page-range text-slate-500 dark:text-slate-400 text-sm mb-0">
                        {{ number_format($productTotals['views']) }} views across {{ number_format($productTotals['product_count']) }} products
                    </p>
                @endif
            </div>
        </div>
        <div class="etd-page-header-toolbar">
            <div class="etd-header-toolbar-row">
                <a href="{{ $data['dashboardUrl'] }}" class="etd-header-btn etd-header-btn--icon no-underline">
                    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span class="etd-header-btn-text">Dashboard</span>
                </a>
                <a href="{{ $activityLink() }}" class="etd-header-btn etd-header-btn--icon no-underline">
                    <span class="etd-header-btn-text">Activity</span>
                </a>
                <div class="etd-header-period-nav etd-print-hide">
                    @if ($data['prevUrl'])
                        <a href="{{ $data['prevUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Previous period" title="Previous period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></a>
                    @else
                        <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></span>
                    @endif
                    <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
                        @foreach (['24h' => 'Today', 'yesterday' => 'Yesterday', '7d' => '7d', '30d' => '30d'] as $periodKey => $periodLabel)
                            <a href="{{ ($data['periodPresetUrl'] ?? fn (string $key) => route($productsRoute, ['period' => $key]))($periodKey) }}" class="etd-segmented-btn {{ $data['period'] === $periodKey ? 'active' : '' }} no-underline">{{ $periodLabel }}</a>
                        @endforeach
                        <button type="button" class="etd-segmented-btn js-ecom-dashboard-period-custom-toggle {{ $data['period'] === 'custom' ? 'active' : '' }}" aria-label="Custom date range">Custom</button>
                    </div>
                    <div id="ecom-dashboard-header-custom-dates"
                         class="etd-custom-dates etd-custom-dates--header @if($data['period'] !== 'custom') etd-custom-dates--closed @endif"
                         data-etd-date-range-single>
                        <input type="text" class="etd-flatpickr-date-range f-input etd-date-input etd-date-input--range" data-default-from="{{ $data['dateFrom'] }}" data-default-to="{{ $data['dateTo'] }}" placeholder="Select date range" readonly aria-label="Custom date range">
                        <input type="hidden" data-range="from" id="ecom-dashboard-header-date-from" value="{{ $data['dateFrom'] }}">
                        <input type="hidden" data-range="to" id="ecom-dashboard-header-date-to" value="{{ $data['dateTo'] }}">
                        <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply js-ecom-dashboard-header-custom-apply">Apply</button>
                    </div>
                    @if ($data['nextUrl'])
                        <a href="{{ $data['nextUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Next period" title="Next period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></a>
                    @else
                        <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></span>
                    @endif
                </div>
                <div class="etd-header-toolbar-icon-group etd-header-toolbar-icon-group--actions">
                    @include('ecom_tracker.partials.header-reset-button', [
                        'url' => route($productsRoute),
                        'active' => $data['period'] !== '24h',
                    ])
                    @include('ecom_tracker.partials.header-print-button')
                    <button type="button" id="ecom-dashboard-filter-open" class="etd-header-btn etd-header-btn--icon-only js-ecom-dashboard-filter-open" aria-label="Filters" aria-expanded="false">
                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="etd-panel etd-print-unit etd-product-performance-list" id="products-all">
    @include('ecom_tracker.partials.product-performance-table', [
        'products' => $productRows,
        'productActivityLink' => fn (array $query) => $activityLink(array_filter([
            'focus' => 'products',
            'product_code' => (string) ($query['product_code'] ?? ''),
        ])),
    ])
    <div class="etd-product-performance-list__pager etd-print-hide">
        @include('ecom_activity.partials.pagination', [
            'paginator' => $paginator,
            'itemLabel' => 'products',
        ])
    </div>
</div>

</div>

@endsection
