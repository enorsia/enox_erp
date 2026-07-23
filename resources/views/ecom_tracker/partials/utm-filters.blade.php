<div>
    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Traffic source</p>
    <div class="grid grid-cols-1 gap-2">
        <div>
            <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">UTM source</label>
            <select name="utm_source"
                    class="tom-select etd-tom-select w-full"
                    data-placeholder="All">
                <option value="" @selected($selected_source === '')>All</option>
                @foreach ($sources as $value => $label)
                    <option value="{{ $value }}" @selected($selected_source === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">UTM medium</label>
            <select name="utm_medium"
                    class="tom-select etd-tom-select w-full"
                    data-placeholder="All">
                <option value="" @selected($selected_medium === '')>All</option>
                @foreach ($mediums as $value => $label)
                    <option value="{{ $value }}" @selected($selected_medium === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<hr class="border-slate-100 dark:border-slate-700"/>
