@extends('layouts.app')

@section('title', 'Sales Report Export')

@section('content')
<div id="sales-report-page-content"
     x-data="salesReportPage()"
     @keydown.escape.window="exportOpen = false">

    {{-- Export modal --}}
    <div x-show="exportOpen" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]" @click="exportOpen = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl border border-slate-200/80 dark:border-slate-700 max-h-[90vh] flex flex-col overflow-hidden"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             @click.stop>

            {{-- Header --}}
            <div class="shrink-0 px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-sm shadow-emerald-500/20 shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Export to Excel</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Choose which report tables and columns to include</p>
                            <p class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 mt-1.5" x-text="exportSummary()"></p>
                        </div>
                    </div>
                    <button type="button" @click="exportOpen = false"
                            class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 dark:hover:text-slate-200 flex items-center justify-center transition-colors shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-slate-50/50 dark:bg-slate-900/20">
                <template x-for="section in sections" :key="section.key">
                    <div class="rounded-xl border bg-white dark:bg-slate-800 shadow-sm transition-all duration-200"
                         :class="tables[section.key]
                             ? 'border-slate-200 dark:border-slate-700'
                             : 'border-slate-200/60 dark:border-slate-700/60 opacity-60'">

                        {{-- Section header --}}
                        <div class="flex items-center gap-3 px-3.5 py-3">
                            <label class="flex items-center shrink-0 cursor-pointer" @click.stop>
                                <input type="checkbox"
                                       class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30 cursor-pointer"
                                       :checked="tables[section.key]"
                                       @change="setTableIncluded(section.key, $event.target.checked)">
                            </label>

                            <button type="button" @click="toggleExpanded(section.key)"
                                    class="flex-1 flex items-center gap-2 min-w-0 text-left group">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100" x-text="section.label"></span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                              :class="sectionSelectedCount(section.key) > 0
                                                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                  : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'"
                                              x-text="sectionSelectedCount(section.key) + ' / ' + sectionTotalCount(section.key)"></span>
                                    </div>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 line-clamp-1" x-text="section.desc"></p>
                                </div>
                                <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300 transition-all duration-200 shrink-0"
                                     :class="expanded[section.key] ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div class="flex items-center gap-1 shrink-0" @click.stop>
                                <button type="button" @click="selectAllColumns(section.key)"
                                        class="px-2 py-1 text-[10px] font-medium rounded-md text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors">
                                    All
                                </button>
                                <button type="button" @click="clearColumns(section.key)"
                                        class="px-2 py-1 text-[10px] font-medium rounded-md text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors">
                                    None
                                </button>
                            </div>
                        </div>

                        {{-- Column groups --}}
                        <div x-show="expanded[section.key]" x-collapse class="border-t border-slate-100 dark:border-slate-700/80">
                            <div class="p-3 space-y-2.5">
                                {{-- Single-checkbox groups: 2 per row --}}
                                <div x-show="singleColumnGroups(section).length > 0" class="grid grid-cols-2 gap-1.5">
                                    <template x-for="group in singleColumnGroups(section)" :key="group.header + '-single'">
                                        <template x-for="col in group.columns" :key="col.key">
                                            <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-all duration-150 select-none"
                                                   :class="isColumnSelected(section.key, col.key)
                                                       ? 'border-emerald-300 bg-emerald-50/90 shadow-sm ring-1 ring-emerald-500/10 dark:border-emerald-700/60 dark:bg-emerald-950/30'
                                                       : 'border-slate-200/80 bg-white hover:border-slate-300 hover:shadow-sm dark:border-slate-600/80 dark:bg-slate-800/80 dark:hover:border-slate-500'"
                                                   :title="columnLabel(col)">
                                                <input type="checkbox"
                                                       class="w-3.5 h-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30 cursor-pointer shrink-0"
                                                       :checked="isColumnSelected(section.key, col.key)"
                                                       @change="toggleColumn(section.key, col.key)">
                                                <span class="min-w-0 flex-1 leading-tight">
                                                    <span class="block text-[11px] font-medium truncate"
                                                          :class="isColumnSelected(section.key, col.key)
                                                              ? 'text-emerald-800 dark:text-emerald-200'
                                                              : 'text-slate-700 dark:text-slate-300'"
                                                          x-text="columnChipLabel(col)"></span>
                                                </span>
                                            </label>
                                        </template>
                                    </template>
                                </div>

                                {{-- Multi-checkbox groups --}}
                                <template x-for="group in multiColumnGroups(section)" :key="group.header">
                                    <div class="rounded-lg border border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-900/30 p-2.5">
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 truncate" x-text="group.header"></span>
                                                <span class="text-[10px] text-slate-400 dark:text-slate-500 shrink-0"
                                                      x-text="'(' + groupSelectedCount(section.key, group) + '/' + group.columns.length + ')'"></span>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <button type="button" @click="toggleGroupColumns(section.key, group, true)"
                                                        class="text-[10px] font-medium text-emerald-600 hover:underline">All</button>
                                                <span class="text-slate-300 text-[10px]">·</span>
                                                <button type="button" @click="toggleGroupColumns(section.key, group, false)"
                                                        class="text-[10px] font-medium text-slate-400 hover:underline">None</button>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                                            <template x-for="col in group.columns" :key="col.key">
                                                <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-all duration-150 select-none"
                                                       :class="isColumnSelected(section.key, col.key)
                                                           ? 'border-emerald-300 bg-emerald-50/90 shadow-sm ring-1 ring-emerald-500/10 dark:border-emerald-700/60 dark:bg-emerald-950/30'
                                                           : 'border-slate-200/80 bg-white hover:border-slate-300 hover:shadow-sm dark:border-slate-600/80 dark:bg-slate-800/80 dark:hover:border-slate-500'"
                                                       :title="columnLabel(col)">
                                                    <input type="checkbox"
                                                           class="w-3.5 h-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30 cursor-pointer shrink-0"
                                                           :checked="isColumnSelected(section.key, col.key)"
                                                           @change="toggleColumn(section.key, col.key)">
                                                    <span class="min-w-0 flex-1 leading-tight">
                                                        <span x-show="columnChipMeta(col)"
                                                              class="block text-[9px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500 truncate"
                                                              x-text="columnChipMeta(col)"></span>
                                                        <span class="block text-[11px] font-medium truncate"
                                                              :class="isColumnSelected(section.key, col.key)
                                                                  ? 'text-emerald-800 dark:text-emerald-200'
                                                                  : 'text-slate-700 dark:text-slate-300'"
                                                              x-text="columnChipLabel(col)"></span>
                                                    </span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="shrink-0 px-4 py-3.5 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex items-center gap-2">
                <button type="button" @click="exportOpen = false"
                        class="flex-1 py-2.5 text-sm font-medium rounded-xl border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    Cancel
                </button>
                <a :href="exportUrl()"
                   :class="canExport() ? 'hover:bg-emerald-600 shadow-emerald-500/20' : 'opacity-40 pointer-events-none'"
                   class="flex-[1.4] inline-flex items-center justify-center gap-2 py-2.5 text-sm font-semibold rounded-xl bg-emerald-500 text-white shadow-sm transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Excel
                </a>
            </div>
        </div>
    </div>

    <div class="p-5 lg:p-6 space-y-5">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-8 h-8 bg-emerald-400/15 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </span>
                    Sales Report
                </h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5 ml-10">{{ $range['label'] }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap sm:justify-end">
                <a href="{{ route('admin.daily-sales.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                <button type="button" @click="exportOpen = true"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 font-medium hover:bg-emerald-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Excel
                </button>
            </div>
        </div>

        {{-- Period filter --}}
        <div class="an-card p-5">
            <p class="sec-heading mb-4">Filter by Period</p>
            <div class="flex flex-wrap items-end gap-3">
                @include('sales.partials.report_period_fields', ['inForm' => false])
                <button type="button" @click="submitPeriod()"
                        class="px-5 py-2 bg-accent-400 hover:bg-accent-600 text-white text-sm font-semibold rounded-lg transition-colors">
                    Apply Period
                </button>
                <a href="{{ $reset_period_url }}"
                   class="px-5 py-2 border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors">
                    Reset
                </a>
            </div>
        </div>

        {{-- KPI stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach($stats as $stat)
                <div class="sr-stat-card sr-stat-{{ $stat['tone'] }}">
                    <p class="sr-stat-label">{{ $stat['label'] }}</p>
                    <p class="sr-stat-value {{ $stat['value_class'] ?? '' }}">{{ $stat['value'] }}</p>
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
                Select a period above, then switch view tabs for Totals, Weekly, All Data, and Return Breakdown.
            </div>
        </div>

        {{-- Report data viewer --}}
        <div class="an-card overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <p class="sec-heading mb-1">Report Data</p>
                        <h2 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">{{ $range['label'] }}</h2>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                            Showing {{ $visible_count }} rows
                            @if($active_filter_count > 0)
                                <span class="text-accent-600">· {{ $active_filter_count }} filter{{ $active_filter_count > 1 ? 's' : '' }} active</span>
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

            @if($show_daily_month_tabs)
                <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/20">
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2.5">Month</p>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($daily_month_tabs as $tab)
                            <a href="{{ $tab['url'] }}"
                               class="sr-month-pill {{ $tab['active'] ? 'active' : '' }}">
                                {{ $tab['label'] }}
                                <span class="sr-view-count">{{ $tab['count'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="sr-table-wrap">
                @if($view === 'weekly')
                    @include('sales.partials.report_table_weekly')
                @elseif($view === 'daily')
                    @include('sales.partials.report_table_daily')
                @elseif($view === 'returns')
                    @include('sales.partials.report_table_returns')
                @else
                    @include('sales.partials.report_table_summary')
                @endif
            </div>
        </div>

    </div>
</div>

@push('js')
<script>
window.salesReportExportConfig = {
    period: @json($filters['period'] ?? 'this_month'),
    fromYM: @json(($filters['period'] ?? 'this_month') === 'custom'
        ? ($filters['from_year_month'] ?? $period_display['from_year_month'])
        : $period_display['from_year_month']),
    toYM: @json(($filters['period'] ?? 'this_month') === 'custom'
        ? ($filters['to_year_month'] ?? $period_display['to_year_month'])
        : $period_display['to_year_month']),
    sections: @json($export_sections),
    columns: @json($export_column_defaults),
    exportBaseUrl: @json(route('admin.sales.analytics.export')),
};
</script>
@endpush
@endsection
