<div>
    <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">Search</label>
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Session ID, visitor ID, name, email or IP…"
           class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
</div>

<hr class="border-slate-100 dark:border-slate-700"/>

<div>
    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Date range</p>
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label class="block text-[12px] text-slate-500 mb-1">From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
        </div>
        <div>
            <label class="block text-[12px] text-slate-500 mb-1">To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
        </div>
    </div>
</div>

<hr class="border-slate-100 dark:border-slate-700"/>

@include('ecom_tracker.partials.session-filters')
