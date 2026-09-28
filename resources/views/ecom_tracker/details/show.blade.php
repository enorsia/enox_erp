@extends('layouts.app')

@section('title', $title)

@section('content')
@php
    $range = $detail['range'];
    $data = $detail['data'];
    $period = $filters['period'] ?? '24h';
    $queryParams = request()->only(['period', 'date_from', 'date_to']);
    $chartPayload = match ($section) {
        'devices' => ['devices' => $data],
        'engagement' => ['engagement' => $data],
        default => [],
    };
    $dashboardBack = \App\Support\EcomTrackerViewData::resolveBackUrl(
        request('back'),
        route('admin.ecom-tracker.dashboard', $queryParams),
    );
    $breadcrumbs = [
        ['label' => 'Store performance', 'url' => $dashboardBack],
        ['label' => $title],
    ];
@endphp

<div class="etd-page">
    @include('ecom_tracker.partials.detail-header', [
        'title' => $title,
        'subtitle' => $range['label'] ?? null,
        'defaultBackRoute' => 'admin.ecom-tracker.dashboard',
        'activeFilterCount' => $activeFilterCount,
        'breadcrumbs' => $breadcrumbs,
        'compact' => true,
        'showFilterButton' => false,
    ])

    <div @class(['etd-panel', 'etd-panel--compact' => $section === 'products'])>
        @include('ecom_tracker.details.sections.'.$section, ['data' => $data, 'range' => $range, 'paginator' => $paginator, 'filters' => $filters])
    </div>

    @if ($paginator)
        @include('layouts.pagination', ['paginator' => $paginator])
    @endif
</div>

@endsection

@if (in_array($section, ['devices', 'engagement'], true))
    @push('js')
        <script>window.ecomTrackerDashboardData = @json($chartPayload);</script>
        @vite('resources/js/pages/ecom-tracker-dashboard.js')
    @endpush
@endif
