@php
    $exportModalTitle = $export_modal_title ?? 'Export Sales Report';
    $exportModalId = $export_modal_id ?? 'export-modal';
    $exportStartBtnId = $export_start_btn_id ?? 'export-start-btn';
    $exportModalDateRangeId = $export_modal_date_range_id ?? 'export-modal-date-range';
    $exportNotifyBrowserId = $export_notify_browser_id ?? 'export-notify-browser';
    $exportFormatInputName = $export_format_input_name ?? 'export_format';
    $exportModalOpenFn = $export_modal_open_fn ?? 'openExportModal';
    $exportModalCloseFn = $export_modal_close_fn ?? 'closeExportModal';
    $exportModalShowDateRange = $export_modal_show_date_range ?? true;
    $exportModalShowNotify = $export_modal_show_notify ?? true;
    $exportModalFilterLabel = $export_modal_filter_label ?? null;
    $exportModalFilterDefault = $export_modal_filter_default ?? 'All products';
@endphp

<div id="{{ $exportModalId }}" class="export-report-modal fixed inset-0 z-[9999] flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="window.{{ $exportModalCloseFn }} && window.{{ $exportModalCloseFn }}()"></div>
    <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">{{ $exportModalTitle }}</h3>
            <button type="button" onclick="window.{{ $exportModalCloseFn }} && window.{{ $exportModalCloseFn }}()"
                class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-5 py-4 space-y-4">
            @if ($exportModalShowDateRange)
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Date range</p>
                    <p id="{{ $exportModalDateRangeId }}" class="text-[13px] text-slate-700 dark:text-slate-200">All dates</p>
                    <p id="export-modal-row-estimate" class="text-[12px] text-slate-400 dark:text-slate-500 mt-1">Export runs in the background. You can keep working.</p>
                </div>
            @elseif ($exportModalFilterLabel)
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">{{ $exportModalFilterLabel }}</p>
                    <p id="{{ $exportModalDateRangeId }}" class="text-[13px] text-slate-700 dark:text-slate-200">{{ $exportModalFilterDefault }}</p>
                    <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-1">Export runs in the background. You can keep working.</p>
                </div>
            @endif

            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-2">Format</p>
                <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors mb-2">
                    <input type="radio" name="{{ $exportFormatInputName }}" value="xlsx" checked class="mt-0.5 accent-blue-600">
                    <div>
                        <p class="text-[13px] font-medium text-slate-700 dark:text-slate-200">Excel (.xlsx)</p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Best for pivoting &amp; analysis</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                    <input type="radio" name="{{ $exportFormatInputName }}" value="csv" class="mt-0.5 accent-blue-600">
                    <div>
                        <p class="text-[13px] font-medium text-slate-700 dark:text-slate-200">CSV (.csv)</p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Fastest — raw data, any size</p>
                    </div>
                </label>
            </div>

            @if ($exportModalShowNotify)
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" id="{{ $exportNotifyBrowserId }}" checked class="rounded accent-blue-600">
                    <span class="text-[13px] text-slate-600 dark:text-slate-300">Notify me in this browser when it's ready</span>
                </label>
            @endif
        </div>

        <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700">
            <button type="button" onclick="window.{{ $exportModalCloseFn }} && window.{{ $exportModalCloseFn }}()"
                class="flex-1 py-2.5 text-[13px] border border-slate-200 dark:border-slate-600 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">
                Cancel
            </button>
            <button type="button" id="{{ $exportStartBtnId }}"
                class="flex-1 py-2.5 text-[13px] rounded-xl bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-semibold transition-all shadow-sm">
                Start Export
            </button>
        </div>
    </div>
</div>
