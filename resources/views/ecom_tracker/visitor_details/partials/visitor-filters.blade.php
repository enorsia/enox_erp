@include('ecom_tracker.partials.utm-filters')

<div>
    <label class="block text-[12px] text-slate-500 mb-1">Search visitor ID</label>
    <input type="text" name="search" value="{{ request('search') }}" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
</div>
<div>
    <label class="block text-[12px] text-slate-500 mb-1">Device</label>
    <select name="device_type" class="tom-select etd-tom-select w-full" data-placeholder="All">
        <option value="" @selected(request('device_type', '') === '')>All</option>
        @foreach (['desktop', 'mobile', 'tablet'] as $device)
            <option value="{{ $device }}" {{ request('device_type') === $device ? 'selected' : '' }}>{{ ucfirst($device) }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="block text-[12px] text-slate-500 mb-1">Logged in</label>
    <select name="logged_in" class="tom-select etd-tom-select w-full" data-placeholder="All">
        <option value="" @selected(request('logged_in', '') === '')>All</option>
        <option value="1" {{ request('logged_in') === '1' ? 'selected' : '' }}>Yes</option>
        <option value="0" {{ request('logged_in') === '0' ? 'selected' : '' }}>Guest</option>
    </select>
</div>
<div>
    <label class="block text-[12px] text-slate-500 mb-1">Has order</label>
    <select name="has_order" class="tom-select etd-tom-select w-full" data-placeholder="All">
        <option value="" @selected(request('has_order', '') === '')>All</option>
        <option value="1" {{ request('has_order') === '1' ? 'selected' : '' }}>Yes</option>
        <option value="0" {{ request('has_order') === '0' ? 'selected' : '' }}>No</option>
    </select>
</div>
