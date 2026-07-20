<div>
    <label class="block text-[12px] text-slate-500 mb-1">Search visitor ID</label>
    <input type="text" name="search" value="{{ request('search') }}" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
</div>
<div>
    <label class="block text-[12px] text-slate-500 mb-1">Device</label>
    <select name="device_type" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
        <option value="">All</option>
        @foreach (['desktop', 'mobile', 'tablet'] as $device)
            <option value="{{ $device }}" {{ request('device_type') === $device ? 'selected' : '' }}>{{ ucfirst($device) }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="block text-[12px] text-slate-500 mb-1">Logged in</label>
    <select name="logged_in" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
        <option value="">All</option>
        <option value="1" {{ request('logged_in') === '1' ? 'selected' : '' }}>Yes</option>
        <option value="0" {{ request('logged_in') === '0' ? 'selected' : '' }}>Guest</option>
    </select>
</div>
<div>
    <label class="block text-[12px] text-slate-500 mb-1">Has order</label>
    <select name="has_order" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
        <option value="">All</option>
        <option value="1" {{ request('has_order') === '1' ? 'selected' : '' }}>Yes</option>
        <option value="0" {{ request('has_order') === '0' ? 'selected' : '' }}>No</option>
    </select>
</div>
