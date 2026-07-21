{{-- Reusable filter side drawer. Requires parent x-data with drawerOpen. --}}
@props([
    'action',
    'resetUrl' => null,
    'presetWindows' => [],
    'window' => '24h',
    'hasCustomRange' => false,
    'datetimeFromValue' => '',
    'datetimeToValue' => '',
    'showDashboardFilters' => false,
    'showSessionFilters' => false,
    'showVisitorFilters' => false,
    'showActivityFilters' => false,
    'showProductFilters' => false,
    'productFilterOptions' => ['categories' => [], 'colors' => [], 'sizes' => []],
    'eventScenarioOptions' => [],
    'productSortGroups' => [],
    'productActivityOptions' => [],
    'currentProductSort' => 'top_revenue',
    'period' => '24h',
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
     x-effect="if (drawerOpen) { $nextTick(() => window.refreshEtdFilterControls && window.refreshEtdFilterControls($el)) }"
     class="fixed top-0 right-0 bottom-0 w-full sm:w-[340px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl etd-filter-drawer"
     style="display:none;">
    <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 dark:border-slate-700 shrink-0">
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
        @if ($showVisitorFilters && request('sort_by'))
            <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
        @endif

        <div class="flex-1 overflow-y-auto px-5 py-2.5 space-y-3 etd-filter-drawer__body">
            @include('ecom_tracker.partials.timezone-notice')
            @if ($showDashboardFilters)
                <div x-data="{ drawerPeriod: @js($period) }">
                    <label class="etd-filter-compact-field">
                        <span class="etd-filter-compact-label">Period</span>
                        <select name="period"
                                class="tom-select etd-tom-select w-full"
                                data-placeholder="All"
                                @change="drawerPeriod = $event.target.value">
                            <option value="" @selected(! request()->filled('period'))>All</option>
                            @foreach (['24h' => '24 hours', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'custom' => 'Custom'] as $periodKey => $periodOptionLabel)
                                <option value="{{ $periodKey }}" @selected($period === $periodKey)>{{ $periodOptionLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div x-show="drawerPeriod === 'custom'"
                         x-collapse
                         x-effect="syncEtdFlatpickrEnabled($el, drawerPeriod === 'custom')"
                         class="etd-date-range grid grid-cols-2 gap-2 mt-2"
                         data-etd-date-range>
                        <div>
                            <label class="etd-filter-compact-label" for="filter-date-from">From</label>
                            <input type="text"
                                   id="filter-date-from"
                                   name="date_from"
                                   value="{{ $dateFrom }}"
                                   data-range="from"
                                   data-default="{{ $dateFrom }}"
                                   placeholder="Select date"
                                   readonly
                                   class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
                        </div>
                        <div>
                            <label class="etd-filter-compact-label" for="filter-date-to">To</label>
                            <input type="text"
                                   id="filter-date-to"
                                   name="date_to"
                                   value="{{ $dateTo }}"
                                   data-range="to"
                                   data-default="{{ $dateTo }}"
                                   placeholder="Select date"
                                   readonly
                                   class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
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
                    <label class="etd-filter-compact-field">
                        <span class="etd-filter-compact-label">Quick window</span>
                        <select name="window"
                                class="tom-select etd-tom-select w-full"
                                data-placeholder="All"
                                @change="drawerWindow = $event.target.value">
                            <option value="" @selected(! request()->filled('window'))>All</option>
                            @foreach (array_merge($presetWindows, ['custom' => 'Custom']) as $windowKey => $windowOptionLabel)
                                <option value="{{ $windowKey }}" @selected($drawerWindow === $windowKey)>{{ $windowOptionLabel }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div x-show="drawerWindow === 'custom'"
                         x-collapse
                         x-effect="syncEtdFlatpickrEnabled($el, drawerWindow === 'custom')"
                         class="etd-date-range space-y-2 mt-2"
                         data-etd-date-range>
                        <div>
                            <label class="etd-filter-compact-label" for="filter-datetime-from">From</label>
                            <input type="text"
                                   id="filter-datetime-from"
                                   name="datetime_from"
                                   value="{{ $datetimeFromValue }}"
                                   data-range="from"
                                   data-default="{{ $datetimeFromValue }}"
                                   placeholder="Select date & time"
                                   readonly
                                   class="etd-flatpickr-datetime etd-filter-input etd-filter-input--sm w-full">
                        </div>
                        <div>
                            <label class="etd-filter-compact-label" for="filter-datetime-to">To</label>
                            <input type="text"
                                   id="filter-datetime-to"
                                   name="datetime_to"
                                   value="{{ $datetimeToValue }}"
                                   data-range="to"
                                   data-default="{{ $datetimeToValue }}"
                                   placeholder="Select date & time"
                                   readonly
                                   class="etd-flatpickr-datetime etd-filter-input etd-filter-input--sm w-full">
                        </div>
                    </div>
                </div>
            @endif

            @if ($showSessionFilters || $showVisitorFilters || $showProductFilters)
                <hr class="etd-filter-divider"/>
                @if ($showProductFilters)
                    <div class="etd-filter-product-wrap">
                    @include('ecom_tracker.partials.product-catalog-filters', [
                        'filterOptions' => $productFilterOptions,
                        'eventScenarioOptions' => $eventScenarioOptions,
                        'sortGroups' => $productSortGroups,
                        'activityOptions' => $productActivityOptions,
                        'currentSort' => $currentProductSort,
                    ])
                    </div>
                    <hr class="etd-filter-divider"/>
                @endif
                @if ($showSessionFilters)
                    @include('ecom_tracker.partials.session-filters')
                @endif
                @if ($showVisitorFilters)
                    @include('ecom_tracker.visitor_details.partials.visitor-filters')
                @endif
            @endif
        </div>

        <div class="flex gap-2.5 px-5 py-3 border-t border-slate-200 dark:border-slate-700 shrink-0">
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
