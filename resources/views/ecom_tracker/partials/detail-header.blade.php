@props([
    'title',
    'subtitle' => null,
    'defaultBackRoute',
    'exportUrl' => null,
    'activeFilterCount' => 0,
    'breadcrumbs' => [],
])

<div class="etd-topbar">
    <div class="etd-topbar-intro">
        @if (count($breadcrumbs) > 0)
            @include('ecom_tracker.partials.breadcrumbs', ['items' => $breadcrumbs])
        @else
            <div class="flex items-center gap-3 mb-2">
                <a href="{{ request('back') ? urldecode(request('back')) : route($defaultBackRoute) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors no-underline">
                    ← Back
                </a>
            </div>
        @endif
        <h1 class="etd-page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="etd-page-desc">{{ $subtitle }}</p>
        @endif
        @include('ecom_tracker.partials.timezone-notice')
    </div>

    <div class="flex items-center gap-2 flex-wrap shrink-0">
        @if ($exportUrl)
            <a href="{{ $exportUrl }}"
               class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 transition-colors font-medium no-underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </a>
        @endif
        <button type="button"
                @click="drawerOpen = true"
                class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilterCount > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
            Filters
            @if ($activeFilterCount > 0)
                <span class="bg-accent-400 text-white text-[9px] font-bold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilterCount }}</span>
            @endif
        </button>
    </div>
</div>
