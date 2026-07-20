{{-- Reusable filter side drawer. Requires parent x-data with drawerOpen. --}}
@props([
    'action',
    'resetUrl' => null,
    'presetWindows' => [],
    'window' => '7d',
    'hasCustomRange' => false,
    'datetimeFromValue' => '',
    'datetimeToValue' => '',
    'showDashboardFilters' => false,
    'showSessionFilters' => false,
    'showVisitorFilters' => false,
    'showActivityFilters' => false,
    'period' => '30d',
    'dateFrom' => '',
    'dateTo' => '',
])

<div x-show="drawerOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="drawerOpen = false"
     class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]"
     style="display:none;"></div>

<div x-show="drawerOpen"
     x-transition:enter="transition ease-out duration-250"
     x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full"
     class="fixed top-0 right-0 bottom-0 w-full sm:w-[340px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
     style="display:none;">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
        <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100">
            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
            Filters
        </div>
        <button type="button" @click="drawerOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <form method="GET" action="{{ $action }}" class="flex-1 flex flex-col overflow-hidden">
        @if (request('back'))
            <input type="hidden" name="back" value="{{ request('back') }}">
        @endif

        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
            @include('ecom_tracker.partials.timezone-notice')
            @if ($showDashboardFilters)
                <div x-data="{ drawerPeriod: @js($period) }">
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Period</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'custom' => 'Custom'] as $key => $label)
                            <label class="flex items-center gap-2 px-3 py-2.5 rounded-lg border cursor-pointer transition-colors"
                                   :class="drawerPeriod === @js($key) ? 'border-accent-400 bg-accent-400/10 text-accent-700 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300'">
                                <input type="radio" name="period" value="{{ $key }}" x-model="drawerPeriod" class="text-accent-400 border-slate-300">
                                <span class="text-[13px] font-medium">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div x-show="drawerPeriod === 'custom'" x-collapse class="grid grid-cols-2 gap-2 mt-3">
                        <div>
                            <label class="block text-[12px] text-slate-500 mb-1">From</label>
                            <input type="date" name="date_from" value="{{ $dateFrom }}"
                                   :disabled="drawerPeriod !== 'custom'"
                                   class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
                        </div>
                        <div>
                            <label class="block text-[12px] text-slate-500 mb-1">To</label>
                            <input type="date" name="date_to" value="{{ $dateTo }}"
                                   :disabled="drawerPeriod !== 'custom'"
                                   class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
                        </div>
                    </div>
                </div>
            @elseif ($showActivityFilters)
                @include('ecom_activity.partials.activity-filters')
            @else
                @php
                    $drawerWindow = $hasCustomRange ? 'custom' : $window;
                @endphp
                <div x-data="{ drawerWindow: @js($drawerWindow) }">
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Quick window</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (array_merge($presetWindows, ['custom' => 'Custom']) as $key => $label)
                            <label class="flex items-center gap-2 px-3 py-2.5 rounded-lg border cursor-pointer transition-colors"
                                   :class="drawerWindow === @js($key) ? 'border-accent-400 bg-accent-400/10 text-accent-700 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300'">
                                <input type="radio" name="window" value="{{ $key }}" x-model="drawerWindow" class="text-accent-400 border-slate-300">
                                <span class="text-[13px] font-medium">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div x-show="drawerWindow === 'custom'" x-collapse class="mt-4 space-y-3">
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Custom date range</p>
                        <div>
                            <label class="block text-[12px] text-slate-500 mb-1">From</label>
                            <input type="datetime-local" name="datetime_from" value="{{ $datetimeFromValue }}"
                                   :disabled="drawerWindow !== 'custom'"
                                   class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
                        </div>
                        <div>
                            <label class="block text-[12px] text-slate-500 mb-1">To</label>
                            <input type="datetime-local" name="datetime_to" value="{{ $datetimeToValue }}"
                                   :disabled="drawerWindow !== 'custom'"
                                   class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
                        </div>
                    </div>
                </div>
            @endif

            @if ($showSessionFilters || $showVisitorFilters)
                <hr class="border-slate-100 dark:border-slate-700"/>
                @if ($showSessionFilters)
                    @include('ecom_tracker.partials.session-filters')
                @endif
                @if ($showVisitorFilters)
                    @include('ecom_tracker.visitor_details.partials.visitor-filters')
                @endif
            @endif
        </div>

        <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 shrink-0">
            <a href="{{ $resetUrl ?? $action }}"
               class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">
                Reset
            </a>
            <button type="submit" class="flex-[2] py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                Apply Filters
            </button>
        </div>
    </form>
</div>
