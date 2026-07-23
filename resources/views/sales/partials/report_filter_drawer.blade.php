{{-- Filter drawer backdrop --}}
<div x-show="drawerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @click="drawerOpen = false"
     class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]" style="display:none;"></div>

{{-- Filter drawer panel --}}
<div x-show="drawerOpen"
     x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
     class="fixed top-0 right-0 bottom-0 w-full sm:w-[360px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
     style="display:none;">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
        <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100">
            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
            Report Filters
        </div>
        <button type="button" @click="drawerOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <form method="get" action="{{ route('admin.sales.analytics.report') }}" @submit="onDrawerSubmit" class="flex-1 flex flex-col overflow-hidden">
        @if($report_filters['month'] !== '')
            <input type="hidden" name="month" value="{{ $report_filters['month'] }}">
        @endif

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
            {{-- Period (synced with body card) --}}
            <div>
                <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-3">Filter by Period</p>
                <div class="space-y-3">
                    @include('sales.partials.report_period_fields', ['inForm' => true])
                </div>
            </div>
            <hr class="border-slate-100 dark:border-slate-700"/>

            <div>
                <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Platform</p>
                <select name="platform_id" class="f-input custom-select">
                    <option value="">All Platforms</option>
                    @foreach($filter_options['platforms'] as $p)
                        <option value="{{ $p['id'] }}" {{ (string) $report_filters['platform_id'] === (string) $p['id'] ? 'selected' : '' }}>{{ $p['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 shrink-0">
            <a href="{{ $reset_report_url }}" class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">Reset</a>
            <button type="submit" class="flex-[2] py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">Apply Filters</button>
        </div>
    </form>
</div>
