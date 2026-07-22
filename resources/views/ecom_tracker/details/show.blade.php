@extends('layouts.app')

@section('title', $title)

@section('content')
@php
    $range = $detail['range'];
    $data = $detail['data'];
    $period = $filters['period'] ?? '24h';
    $resetQuery = array_filter(['back' => request('back')]);
    $queryParams = request()->only(['period', 'date_from', 'date_to', 'device_type', 'logged_in', 'has_order', 'country', 'utm_source', 'utm_medium', 'search', 'category', 'color', 'size', 'sort_by', 'activity', 'has_purchases', 'has_views', 'has_adds', 'event_scenario']);
    $chartPayload = match ($section) {
        'trend' => ['trend' => $data],
        'devices' => ['devices' => $data],
        'engagement' => ['engagement' => $data],
        default => [],
    };
    $dashboardBack = request('back') ? urldecode(request('back')) : route('admin.ecom-tracker.dashboard', $queryParams);
    $breadcrumbs = [
        ['label' => 'Store performance', 'url' => $dashboardBack],
        ['label' => $title],
    ];
@endphp

<div class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-tracker.dashboard.details', $section),
        'resetUrl' => route('admin.ecom-tracker.dashboard.details', array_merge(['section' => $section], $resetQuery)),
        'showDashboardFilters' => true,
        'showSessionFilters' => true,
        'showProductFilters' => $section === 'products',
        'productFiltersHeading' => $section === 'products' ? 'Product catalog' : null,
        'productFilterOptions' => $section === 'products' ? ($data['filter_options'] ?? []) : [],
        'eventScenarioOptions' => $eventScenarioOptions ?? [],
        'productSortGroups' => $productSortGroups ?? [],
        'productActivityOptions' => $productActivityOptions ?? [],
        'currentProductSort' => $currentProductSort ?? 'top_revenue',
        'period' => $period,
        'dateFrom' => $filters['date_from'] ?? '',
        'dateTo' => $filters['date_to'] ?? '',
    ])

    @include('ecom_tracker.partials.detail-header', [
        'title' => $title,
        'subtitle' => $range['label'] ?? null,
        'defaultBackRoute' => 'admin.ecom-tracker.dashboard',
        'activeFilterCount' => $activeFilterCount,
        'breadcrumbs' => $breadcrumbs,
        'compact' => true,
    ])

    <div @class(['etd-panel', 'etd-panel--compact' => $section === 'products'])>
        @include('ecom_tracker.details.sections.'.$section, ['data' => $data, 'range' => $range, 'paginator' => $paginator])
    </div>

    @if ($paginator)
        @include('layouts.pagination', ['paginator' => $paginator])
    @endif
</div>

@endsection

@if (in_array($section, ['trend', 'devices', 'engagement'], true))
    @push('js')
        <script>window.ecomTrackerDashboardData = @json($chartPayload);</script>
        @vite('resources/js/pages/ecom-tracker-dashboard.js')
    @endpush
@endif
