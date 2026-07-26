@php
    $filterOptionCounts = $filterOptionCounts ?? [];
    $includeVisitorTrust = $includeVisitorTrust ?? true;
    $countLabel = static function (string $value, string $label, array $counts): string {
        return isset($counts[$value]) ? "{$label} ({$counts[$value]})" : $label;
    };
@endphp

@include('ecom_tracker.partials.utm-filters', [
    'utmFilterState' => $utmFilterState ?? null,
])

<div>
    <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">Device</label>
    <select name="device_type" class="tom-select etd-tom-select w-full" data-placeholder="All">
        <option value="" @selected(request('device_type', '') === '')>All</option>
        @foreach (['desktop', 'mobile', 'tablet'] as $device)
            @php($deviceLabel = $countLabel($device, ucfirst($device), $filterOptionCounts['device_type'] ?? []))
            <option value="{{ $device }}" {{ request('device_type') === $device ? 'selected' : '' }}>{{ $deviceLabel }}</option>
        @endforeach
    </select>
</div>

<div>
    <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">Logged in</label>
    <select name="logged_in" class="tom-select etd-tom-select w-full" data-placeholder="All">
        <option value="" @selected(request('logged_in', '') === '')>All</option>
        <option value="1" {{ request('logged_in') === '1' ? 'selected' : '' }}>{{ $countLabel('1', 'Logged in', $filterOptionCounts['logged_in'] ?? []) }}</option>
        <option value="0" {{ request('logged_in') === '0' ? 'selected' : '' }}>{{ $countLabel('0', 'Guest', $filterOptionCounts['logged_in'] ?? []) }}</option>
    </select>
</div>

<div>
    <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">Has order</label>
    <select name="has_order" class="tom-select etd-tom-select w-full" data-placeholder="All">
        <option value="" @selected(request('has_order', '') === '')>All</option>
        <option value="1" {{ request('has_order') === '1' ? 'selected' : '' }}>{{ $countLabel('1', 'With order', $filterOptionCounts['has_order'] ?? []) }}</option>
        <option value="0" {{ request('has_order') === '0' ? 'selected' : '' }}>{{ $countLabel('0', 'No order', $filterOptionCounts['has_order'] ?? []) }}</option>
    </select>
</div>

@if ($includeVisitorTrust)
<div>
    <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">Visitor trust</label>
    <select name="visitor_type" class="tom-select etd-tom-select w-full" data-placeholder="All">
        <option value="" @selected(request('visitor_type', '') === '')>All</option>
        <option value="human" {{ request('visitor_type') === 'human' ? 'selected' : '' }}>{{ $countLabel('human', 'Real visitors', $filterOptionCounts['visitor_type'] ?? []) }}</option>
        <option value="bot" {{ request('visitor_type') === 'bot' ? 'selected' : '' }}>{{ $countLabel('bot', 'Automated traffic', $filterOptionCounts['visitor_type'] ?? []) }}</option>
        <option value="unclassified" {{ request('visitor_type') === 'unclassified' ? 'selected' : '' }}>{{ $countLabel('unclassified', 'Not classified', $filterOptionCounts['visitor_type'] ?? []) }}</option>
    </select>
    <p class="text-[10px] text-slate-400 mt-1 mb-0">Filter by how we classified each session's traffic.</p>
</div>
@endif

<div>
    <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">Country</label>
    <input type="text" name="country" value="{{ request('country') }}" placeholder="GB for UK visitors" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
</div>
