@extends('layouts.app')

@section('title', $title)

@section('content')
@php
    use App\Support\TrackerTime;
    $window = $filters['window'] ?? '24h';
    $hasCustomRange = filled($filters['datetime_from'] ?? null) && filled($filters['datetime_to'] ?? null);
    $datetimeFromValue = filled($filters['datetime_from'] ?? null) ? TrackerTime::toLocal($filters['datetime_from'])?->format('Y-m-d\TH:i') : '';
    $datetimeToValue = filled($filters['datetime_to'] ?? null) ? TrackerTime::toLocal($filters['datetime_to'])?->format('Y-m-d\TH:i') : '';
    $presetWindows = ['3h' => '3 hours', '6h' => '6 hours', '12h' => '12 hours', '24h' => '24 hours', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', '1y' => '1 year'];
    $resetQuery = array_filter(['back' => request('back')]);
    $queryParams = request()->only(['window', 'datetime_from', 'datetime_to', 'search', 'device_type', 'logged_in', 'has_order', 'utm_source', 'utm_medium', 'sort_by']);
    $visitorsBack = request('back') ? urldecode(request('back')) : route('admin.ecom-tracker.visitors', $queryParams);
    $breadcrumbs = [
        ['label' => 'Visitor analytics', 'url' => $visitorsBack],
        ['label' => $title],
    ];
    $activityLink = fn (string $visitorId) => route('admin.ecom-activity.index', ['search' => $visitorId]);
@endphp

<div class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-tracker.visitors.details', $section),
        'resetUrl' => route('admin.ecom-tracker.visitors.details', array_merge(['section' => $section], $resetQuery)),
        'presetWindows' => $presetWindows,
        'window' => $window,
        'hasCustomRange' => $hasCustomRange,
        'datetimeFromValue' => $datetimeFromValue,
        'datetimeToValue' => $datetimeToValue,
        'showVisitorFilters' => $section === 'visitors',
    ])

    @include('ecom_tracker.partials.detail-header', [
        'title' => $title,
        'subtitle' => $range['label'] ?? null,
        'defaultBackRoute' => 'admin.ecom-tracker.visitors',
        'activeFilterCount' => $activeFilterCount,
        'breadcrumbs' => $breadcrumbs,
        'sortOptions' => $visitorSortOptions ?? [],
        'currentSort' => $currentSort ?? null,
        'sortAction' => $section === 'visitors' ? route('admin.ecom-tracker.visitors.details', $section) : null,
    ])

    <div class="etd-panel">
        @include('ecom_tracker.visitor_details.sections.'.$section, ['data' => $data, 'range' => $range, 'activityLink' => $activityLink])
    </div>

    @if ($section === 'visitors' && ($data['visitors']->hasPages() ?? false))
        @include('layouts.pagination', ['paginator' => $data['visitors']])
    @endif
</div>

@endsection

@if (in_array($section, ['trend', 'new-returning'], true))
    @push('js')
        <script>window.visitorAnalyticsData = @json($data);</script>
        @vite('resources/js/pages/visitor-analytics.js')
    @endpush
@endif
