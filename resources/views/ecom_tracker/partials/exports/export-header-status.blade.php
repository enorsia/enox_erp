@php
    $exportKey = $export_key ?? 'sales';
    $export = $export ?? ($sales_export ?? null);
    $isLoading = $export && in_array($export->status, ['queued', 'processing'], true);
    $isReady = $export && $export->status === 'completed' && $export->isDownloadReady();
    $showStatus = $isLoading || $isReady;
    $downloadFilename = $export?->downloadDisplayFilename() ?? 'Download';
    $downloadCompletedAt = ($isReady && $export?->completed_at)
        ? $export->completed_at->timezone(config('app.timezone'))->format('M d, Y h:i A')
        : null;

    $downloadLabel = $downloadFilename;
    if ($downloadCompletedAt) {
        $downloadLabel .= ' · '.$downloadCompletedAt;
    }

    $progressLabel = '';
    $loadingMessage = 'Download generating…';
    if ($isLoading && $export) {
        $processed = (int) $export->processed_rows;
        $total = (int) $export->total_rows;
        if ($total > 0) {
            $progressLabel = min($processed, $total).'/'.$total;
            if ($processed >= $total) {
                $loadingMessage = 'Making Excel…';
            }
        } elseif ($processed > 0) {
            $progressLabel = $processed.' rows';
        } elseif ($export->status === 'queued') {
            $progressLabel = 'Queued';
            $loadingMessage = 'Preparing export…';
        }
    }
@endphp

<div id="{{ $exportKey }}-export-status"
    class="items-center{{ $showStatus ? ' is-export-active' : '' }}"
    @if($export)
        data-export-id="{{ $export->id }}"
        data-initial-export='@json($export->toFrontendArray())'
    @endif>
    <div id="{{ $exportKey }}-export-loading"
        class="items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 shadow-sm{{ $isLoading ? ' is-export-loading' : '' }}">
        <svg class="w-4 h-4 animate-spin text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span id="{{ $exportKey }}-export-loading-message" class="text-[12px] font-medium whitespace-nowrap">{{ $loadingMessage }}</span>
        <span id="{{ $exportKey }}-export-progress" class="text-[11px] font-medium text-slate-500 dark:text-slate-400 tabular-nums whitespace-nowrap">{{ $progressLabel ? ' · '.$progressLabel : '' }}</span>
    </div>

    <div id="{{ $exportKey }}-export-ready-group"
        class="items-stretch rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-sm overflow-visible{{ $isReady ? ' is-export-ready' : '' }}">
        <a id="{{ $exportKey }}-export-download"
            href="{{ $isReady ? ($export->signedDownloadUrl() ?? '#') : '#' }}"
            @if($isReady) download="{{ $export->downloadDisplayFilename() }}" @endif
            @if($isReady && filled($downloadLabel)) title="{{ $downloadLabel }}" @endif
            role="button"
            class="export-download-btn inline-flex items-center gap-2 px-3 py-2 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 border-r border-slate-200 dark:border-slate-600 transition-colors min-w-0 rounded-l-[11px]">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <span id="{{ $exportKey }}-export-filename" class="export-download-btn__label text-[12px] font-semibold truncate">{{ $downloadLabel }}</span>
            @if ($isReady && filled($downloadLabel))
                <span class="export-download-tooltip">{{ $downloadLabel }}</span>
            @endif
        </a>

        <button type="button" id="{{ $exportKey }}-export-dismiss"
            class="export-dismiss-btn"
            aria-label="Remove export"
            @if($export) data-export-id="{{ $export->id }}" @endif>
            <svg class="export-dismiss-btn__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/>
            </svg>
            <span class="export-dismiss-tooltip">Remove this export and delete the file</span>
        </button>
    </div>
</div>
