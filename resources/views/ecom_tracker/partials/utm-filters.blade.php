@php
    use App\Models\TrackerUtmFilter;

    $utmSources = TrackerUtmFilter::sources();
    $utmMediums = TrackerUtmFilter::mediums();
    $selectedSource = TrackerUtmFilter::resolveSource(request('utm_source')) ?? '';
    $selectedMedium = TrackerUtmFilter::resolveMedium(request('utm_medium')) ?? '';
@endphp

<div>
    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Traffic source</p>
    <div class="grid grid-cols-1 gap-2">
        <div>
            <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">UTM source</label>
            <select name="utm_source"
                    class="tom-select etd-tom-select w-full"
                    data-placeholder="All">
                <option value="" @selected($selectedSource === '')>All</option>
                @foreach ($utmSources as $value => $label)
                    <option value="{{ $value }}" @selected($selectedSource === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">UTM medium</label>
            <select name="utm_medium"
                    class="tom-select etd-tom-select w-full"
                    data-placeholder="All">
                <option value="" @selected($selectedMedium === '')>All</option>
                @foreach ($utmMediums as $value => $label)
                    <option value="{{ $value }}" @selected($selectedMedium === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<hr class="border-slate-100 dark:border-slate-700"/>
