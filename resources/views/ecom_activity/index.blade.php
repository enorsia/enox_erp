@extends('layouts.app')

@section('title', 'User Activity')

@section('content')

<div class="etd-page etd-page--activity" id="ecom-activity-page-content">

@include('ecom_activity.filter-drawer')

<header class="etd-page-header">
    <div class="etd-page-header-bar">
        <div class="etd-page-header-main">
            <div class="etd-page-header-left">
                <div class="flex items-center flex-wrap gap-x-2 gap-y-1">
                    <h1 class="etd-page-title">User activity</h1>
                    <span class="etd-header-sep" aria-hidden="true">·</span>
                    <span class="etd-page-range">{{ match ($data['period']) { 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom', default => 'Today' } }}</span>
                    <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
                    <div class="etd-page-meta">
                        <span class="etd-meta-item">
                            <svg class="etd-meta-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                            All times (store timezone)
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="etd-page-header-toolbar">
            <div class="etd-header-toolbar-row">
                <a href="/admin/ecom-tracker/dashboard" class="etd-header-btn etd-header-btn--icon no-underline">
                    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span class="etd-header-btn-text">Tracking</span>
                </a>
                <div class="etd-header-period-nav etd-print-hide">
                    <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true" aria-label="Previous day"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></span>
                    <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
                        <a href="{{ route('admin.ecom-activity.index', ['period' => '24h']) }}" class="etd-segmented-btn {{ $data['period'] === '24h' ? 'active' : '' }} no-underline">Today</a>
                        <a href="{{ route('admin.ecom-activity.index', ['period' => 'yesterday']) }}" class="etd-segmented-btn {{ $data['period'] === 'yesterday' ? 'active' : '' }} no-underline">Yesterday</a>
                        <a href="{{ route('admin.ecom-activity.index', ['period' => '7d']) }}" class="etd-segmented-btn {{ $data['period'] === '7d' ? 'active' : '' }} no-underline">7d</a>
                        <a href="{{ route('admin.ecom-activity.index', ['period' => '30d']) }}" class="etd-segmented-btn {{ $data['period'] === '30d' ? 'active' : '' }} no-underline">30d</a>
                        <button type="button" class="etd-segmented-btn js-ecom-activity-period-custom-toggle {{ $data['period'] === 'custom' ? 'active' : '' }}" aria-label="Custom date range">Custom</button>
                    </div>
                    <div id="ecom-activity-header-custom-dates"
                         class="etd-custom-dates etd-custom-dates--header @if($data['period'] !== 'custom') etd-custom-dates--closed @endif"
                         data-etd-date-range-single>
                        <input type="text"
                               class="etd-flatpickr-date-range f-input etd-date-input etd-date-input--range"
                               data-default-from="{{ $data['dateFrom'] }}"
                               data-default-to="{{ $data['dateTo'] }}"
                               placeholder="Select date range"
                               readonly
                               aria-label="Custom date range">
                        <input type="hidden" data-range="from" id="ecom-activity-header-date-from" value="{{ $data['dateFrom'] }}">
                        <input type="hidden" data-range="to" id="ecom-activity-header-date-to" value="{{ $data['dateTo'] }}">
                        <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply js-ecom-activity-header-custom-apply">Apply</button>
                    </div>
                    <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true" aria-label="Next day"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></span>
                </div>
                <a href="{{ route('admin.ecom-activity.index') }}" class="etd-header-btn etd-header-btn--icon-only no-underline" aria-label="Reset filters" title="Reset all filters">
                    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </a>
                <button type="button" id="ecom-activity-filter-open" class="etd-header-btn etd-header-btn--icon-only js-ecom-activity-filter-open" aria-label="Filters" aria-expanded="false">
                    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                </button>
                <button type="button" class="etd-header-btn etd-header-btn--icon-only js-activity-export-open" aria-label="Export" title="Export">
                    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 16.5V3m0 0L7.5 7.5M12 3l4.5 4.5M4.5 19.5h15"/></svg>
                </button>
            </div>
        </div>
    </div>
    <div class="etd-page-header-meta-row etd-page-header-meta-row--empty">
        <div class="etd-page-header-meta-row__left">
            <p class="etd-visitor-quality-summary text-[12px] text-slate-500 dark:text-slate-400 mb-0">
                <span class="font-medium text-slate-700 dark:text-slate-200">0</span> real visitors ·
                <span class="font-medium text-slate-700 dark:text-slate-200">0</span> automated ·
                <span class="font-medium text-slate-700 dark:text-slate-200">0</span> not classified
            </p>
        </div>
        <div class="etd-page-header-meta-row__right"></div>
    </div>
</header>

<div class="etd-activity-table-block" data-etd-activity-table-block>
    <div class="etd-panel">
        <div class="etd-activity-table-toolbar">
            <label class="etd-activity-sort-field">
                <span class="etd-activity-sort-label">Sort by</span>
                <select data-etd-activity-sort-select class="etd-activity-sort-select tom-select etd-tom-select">
                    <option value="last_active" selected>Last active</option>
                    <option value="session_start">Session start</option>
                    <option value="duration">Duration</option>
                    <option value="actions">Actions</option>
                </select>
            </label>
        </div>
        <div class="etd-activity-table-shell" data-etd-activity-table-shell>
            <div class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--activity" style="--etd-activity-focus-cols: 0" data-etd-activity-table-viewport>
                <table class="etd-table etd-table--activity w-full">
                    <thead>
                        <tr>
                            <th class="etd-col-session"><a href="#" data-etd-activity-sort class="etd-sort-th-link"><span class="etd-sort-th-label">Session</span></a></th>
                            <th class="etd-col-user">User</th>
                            <th class="etd-col-trust">Visitor trust</th>
                            <th class="etd-col-commerce"><a href="#" data-etd-activity-sort class="etd-sort-th-link"><span class="etd-sort-th-label">Commerce</span></a></th>
                            <th class="etd-col-actions etd-num"><a href="#" data-etd-activity-sort class="etd-sort-th-link etd-sort-th-link--center"><span class="etd-sort-th-label">Actions</span></a></th>
                            <th class="etd-col-duration etd-activity-col--optional"><a href="#" data-etd-activity-sort class="etd-sort-th-link"><span class="etd-sort-th-label">Duration</span></a></th>
                            <th class="etd-col-last-active etd-activity-col--optional"><a href="#" data-etd-activity-sort class="etd-sort-th-link"><span class="etd-sort-th-label">Last active</span></a></th>
                            <th class="etd-col-action">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="etd-activity-empty-row">
                            <td colspan="8" class="text-center text-slate-500 py-10">No visitor sessions found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="etd-activity-pagination"></div>
</div>

</div>

<div id="activity-export-modal" class="export-report-modal fixed inset-0 z-[9999] flex items-center justify-center p-4" role="dialog" aria-modal="true" style="display:none;">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm js-activity-export-close"></div>
    <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Export User Activity</h3>
            <button type="button" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 js-activity-export-close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-5 py-4 space-y-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Current filters</p>
                <p id="activity-export-modal-filter-summary" class="text-[13px] text-slate-700 dark:text-slate-200">Today</p>
                <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-1">Export runs in the background. You can keep working.</p>
            </div>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-2">Format</p>
                <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer mb-2">
                    <input type="radio" name="activity_export_format" value="xlsx" checked class="mt-0.5 accent-blue-600">
                    <div><p class="text-[13px] font-medium">Excel (.xlsx)</p><p class="text-[11px] text-slate-400">Best for pivoting &amp; analysis</p></div>
                </label>
                <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer">
                    <input type="radio" name="activity_export_format" value="csv" class="mt-0.5 accent-blue-600">
                    <div><p class="text-[13px] font-medium">CSV (.csv)</p><p class="text-[11px] text-slate-400">Fastest — raw data</p></div>
                </label>
            </div>
        </div>
        <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700">
            <button type="button" class="flex-1 py-2.5 text-[13px] border rounded-xl bg-slate-50 dark:bg-slate-700 js-activity-export-close">Cancel</button>
            <button type="button" id="activity-export-start-btn" class="flex-1 py-2.5 text-[13px] rounded-xl bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold">Start Export</button>
        </div>
    </div>
</div>
@endsection
