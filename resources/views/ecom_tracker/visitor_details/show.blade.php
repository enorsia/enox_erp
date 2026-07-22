@extends('layouts.app')

@section('title', $title)

@section('content')
@php
    $presetWindows = $page['presetWindows'];
    $window = $page['window'];
    $hasCustomRange = $page['hasCustomRange'];
    $datetimeFromValue = $page['datetimeFromValue'];
    $datetimeToValue = $page['datetimeToValue'];
    $breadcrumbs = $page['breadcrumbs'];
    $activityLink = $page['activityLink'];
    $resetQuery = array_filter(['back' => request('back')]);
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

    @include('ecom_tracker.partials.visitor-page-header', [
        'title' => $title,
        'rangeLabel' => $page['rangeLabel'],
        'activeWindow' => $page['activeWindow'],
        'datetimeFromValue' => $datetimeFromValue,
        'datetimeToValue' => $datetimeToValue,
        'activeFilterCount' => $page['activeFilterCount'],
        'resetUrl' => $page['resetUrl'],
        'exportUrl' => $page['exportUrl'],
        'resetActive' => $page['resetActive'],
        'breadcrumbs' => $breadcrumbs,
        'backUrl' => $page['backUrl'],
        'sortOptions' => $visitorSortOptions ?? [],
        'currentSort' => $currentSort ?? null,
        'sortAction' => $section === 'visitors' ? route('admin.ecom-tracker.visitors.details', $section) : null,
    ])

    <div class="etd-panel">
        @include('ecom_tracker.visitor_details.sections.'.$section, ['data' => $data, 'range' => $range, 'activityLink' => $activityLink, 'paginator' => $paginator ?? null])
    </div>

    @if ($paginator ?? null)
        @include('layouts.pagination', ['paginator' => $paginator])
    @elseif ($section === 'visitors' && ($data['visitors']->hasPages() ?? false))
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
