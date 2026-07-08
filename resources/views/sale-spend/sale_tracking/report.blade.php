@extends('layouts.app')

@section('title', 'Ads Performance Report')

@section('content')
<div id="ads-performance-report-content" class="p-5 lg:p-6 space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="w-8 h-8 bg-emerald-400/15 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                {{ $title }}
            </h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5 ml-10">{{ $period_label }}</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap sm:justify-end">
            <a href="{{ $back_url }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Ads Performance
            </a>
            <a href="{{ $export_url }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 font-medium hover:bg-emerald-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export Excel
            </a>
        </div>
    </div>

    @include('sale-spend.sale_tracking.partials.report_filters')

    {{-- KPI stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach($stats as $stat)
            <div class="sr-stat-card sr-stat-{{ $stat['tone'] }}">
                <p class="sr-stat-label">{{ $stat['label'] }}</p>
                <p class="sr-stat-value">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Active filter tags --}}
    @if(count($active_filter_tags) > 0)
        <div class="flex flex-wrap gap-2">
            @foreach($active_filter_tags as $tag)
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">{{ $tag['label'] }}:</span> {{ $tag['value'] }}
                    <a href="{{ $tag['url'] }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- How to use --}}
    <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800/40 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-4 h-4 text-blue-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="text-[12px] text-blue-700 dark:text-blue-400">
            <span class="font-semibold">How to use:</span>
            Same data as the Excel export — switch tabs for Ad Performance detail, Monthly Summary, platform engagement, and interactive charts.
        </div>
    </div>

    {{-- Report data viewer --}}
    <div class="an-card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <p class="sec-heading mb-1">Report Data</p>
                    <h2 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Ad Performance Tracking</h2>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                        Showing {{ $visible_count }} {{ $view === 'platforms' ? 'platforms' : ($view === 'charts' ? 'chart sections' : 'rows') }}
                        @if(count($active_filter_tags) > 0)
                            <span class="text-accent-600">· {{ count($active_filter_tags) }} filter{{ count($active_filter_tags) > 1 ? 's' : '' }} active</span>
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach($view_tabs as $tab)
                        <a href="{{ $tab['url'] }}"
                           class="sr-view-pill {{ $tab['active'] ? 'active' : '' }}">
                            {{ $tab['label'] }}
                            <span class="sr-view-count">{{ $row_counts[$tab['key']] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="sr-table-wrap">
            @if($is_empty)
                <div class="px-4 py-16 text-center text-[13px] text-slate-400 dark:text-slate-500">
                    No data found for the selected filters.
                </div>
            @elseif($view === 'summary')
                @include('sale-spend.sale_tracking.partials.report_table_summary')
            @elseif($view === 'platforms')
                @include('sale-spend.sale_tracking.partials.report_table_platforms')
            @elseif($view === 'charts')
                @include('sale-spend.sale_tracking.partials.report_charts')
            @else
                @include('sale-spend.sale_tracking.partials.report_table_performance')
            @endif
        </div>
    </div>

</div>

@push('js')
<script>
window.adsPerformanceChartData = @json($chart_data);
window.adsPerformanceChartView = @json($view);
window.adsPerformanceDefaultEngagement = @json($selected_engagement_slug ?? 'all');
window.adsPerformancePlatformData = {
    all: @json($platform_sections_all),
    sections: @json($platform_sections),
};
</script>
@endpush
@endsection
