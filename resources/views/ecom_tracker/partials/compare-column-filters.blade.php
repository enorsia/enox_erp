@props([
    'side' => 'left',
    'filters' => [],
    'otherFilters' => [],
    'backUrl' => null,
])

@php
    use App\Support\EcomTrackerViewData;

    $prefix = EcomTrackerViewData::compareSidePrefix($side);
    $sideLabel = $side === 'right' ? 'Period B' : 'Period A';
    $period = $filters['period'] ?? '24h';
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $activePreset = match ($period) {
        'yesterday', '7d', '30d', 'custom' => $period,
        default => '24h',
    };
    $basePreset = in_array($period, ['24h', 'yesterday', '7d', '30d'], true) ? $period : '24h';

    $baseQuery = array_merge(
        EcomTrackerViewData::compareSideQuery($side === 'right' ? 'left' : 'right', $otherFilters),
        filled($backUrl) ? ['back' => $backUrl] : (request()->filled('back') ? ['back' => request('back')] : []),
    );
@endphp

<form method="GET"
      action="{{ route('admin.ecom-tracker.dashboard.compare') }}"
      class="etd-compare-column-filters"
      x-show="filtersOpen"
      x-collapse
      @if (! ($filtersOpenDefault ?? false)) style="display: none" @endif>
    @foreach ($baseQuery as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach

    <input type="hidden" name="{{ $prefix }}period" value="{{ $period }}">
    @if ($period === 'custom')
        <input type="hidden" name="{{ $prefix }}date_from" value="{{ $dateFrom }}">
        <input type="hidden" name="{{ $prefix }}date_to" value="{{ $dateTo }}">
    @endif

    <p class="etd-kpi-section-label mb-2">Sessions &amp; audience</p>

    <div class="etd-compare-column-filters__grid">
        <div>
            <label class="etd-filter-compact-label">Device</label>
            <select name="{{ $prefix }}device_type" class="f-input w-full">
                <option value="" @selected(blank($filters['device_type'] ?? null))>All</option>
                @foreach (['desktop', 'mobile', 'tablet'] as $device)
                    <option value="{{ $device }}" @selected(($filters['device_type'] ?? '') === $device)>{{ ucfirst($device) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="etd-filter-compact-label">Logged in</label>
            <select name="{{ $prefix }}logged_in" class="f-input w-full">
                <option value="" @selected(blank($filters['logged_in'] ?? null))>All</option>
                <option value="1" @selected(($filters['logged_in'] ?? '') === '1')>Logged in</option>
                <option value="0" @selected(($filters['logged_in'] ?? '') === '0')>Guest</option>
            </select>
        </div>

        <div>
            <label class="etd-filter-compact-label">Has order</label>
            <select name="{{ $prefix }}has_order" class="f-input w-full">
                <option value="" @selected(blank($filters['has_order'] ?? null))>All</option>
                <option value="1" @selected(($filters['has_order'] ?? '') === '1')>With order</option>
                <option value="0" @selected(($filters['has_order'] ?? '') === '0')>No order</option>
            </select>
        </div>

        <div>
            <label class="etd-filter-compact-label">Visitor type</label>
            <select name="{{ $prefix }}visitor_type" class="f-input w-full">
                <option value="" @selected(blank($filters['visitor_type'] ?? null))>All</option>
                <option value="human" @selected(($filters['visitor_type'] ?? '') === 'human')>Real visitors</option>
                <option value="bot" @selected(($filters['visitor_type'] ?? '') === 'bot')>Automated traffic</option>
                <option value="unclassified" @selected(($filters['visitor_type'] ?? '') === 'unclassified')>Not classified</option>
            </select>
        </div>

        <div>
            <label class="etd-filter-compact-label">UTM source</label>
            <input type="text"
                   name="{{ $prefix }}utm_source"
                   value="{{ $filters['utm_source'] ?? '' }}"
                   class="f-input w-full"
                   placeholder="e.g. google">
        </div>

        <div>
            <label class="etd-filter-compact-label">UTM medium</label>
            <input type="text"
                   name="{{ $prefix }}utm_medium"
                   value="{{ $filters['utm_medium'] ?? '' }}"
                   class="f-input w-full"
                   placeholder="e.g. cpc">
        </div>
    </div>

    <div class="etd-compare-column-filters__actions">
        <button type="submit" class="etd-header-btn etd-header-btn--primary">Apply filters</button>
    </div>
</form>
