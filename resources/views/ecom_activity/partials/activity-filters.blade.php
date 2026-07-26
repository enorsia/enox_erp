@php($includeDateRange = $includeDateRange ?? true)
@php($filterOptionCounts = $filterOptionCounts ?? [])
@php($utmFilterState = $utmFilterState ?? null)
@php($includeVisitorTrust = $includeVisitorTrust ?? true)

<div>
    <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">Search</label>
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Session, visitor, UTM, URL, name, email or IP…"
           class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
</div>

@if ($includeDateRange)
    <hr class="border-slate-100 dark:border-slate-700"/>

    <div>
        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Date range</p>
        <div class="etd-date-range grid grid-cols-2 gap-2" data-etd-date-range>
            <div>
                <label class="etd-filter-compact-label" for="activity-date-from">From</label>
                <input type="text"
                       id="activity-date-from"
                       name="date_from"
                       value="{{ request('date_from') }}"
                       data-range="from"
                       data-default="{{ request('date_from') }}"
                       placeholder="Select date"
                       readonly
                       class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
            </div>
            <div>
                <label class="etd-filter-compact-label" for="activity-date-to">To</label>
                <input type="text"
                       id="activity-date-to"
                       name="date_to"
                       value="{{ request('date_to') }}"
                       data-range="to"
                       data-default="{{ request('date_to') }}"
                       placeholder="Select date"
                       readonly
                       class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
            </div>
        </div>
    </div>

    <hr class="border-slate-100 dark:border-slate-700"/>
@else
    <hr class="border-slate-100 dark:border-slate-700"/>
@endif

@include('ecom_tracker.partials.session-filters', [
    'filterOptionCounts' => $filterOptionCounts,
    'utmFilterState' => $utmFilterState,
    'includeVisitorTrust' => $includeVisitorTrust,
])
