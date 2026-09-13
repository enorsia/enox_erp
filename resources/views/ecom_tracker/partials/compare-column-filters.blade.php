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
      x-ref="filterForm"
      class="etd-compare-column-filters"
      x-show="filtersOpen"
      x-transition:enter="transition ease-out duration-150"
      x-transition:enter-start="opacity-0 translate-y-1"
      x-transition:enter-end="opacity-100 translate-y-0"
      x-transition:leave="transition ease-in duration-100"
      x-transition:leave-start="opacity-100 translate-y-0"
      x-transition:leave-end="opacity-0 translate-y-1"
      x-effect="if (filtersOpen) { $nextTick(() => { $nextTick(() => window.refreshEtdFilterControls?.($el)) }) }"
      @click.stop
      @if (! ($filtersOpenDefault ?? false)) style="display: none" @endif>
    <div class="etd-compare-column-filters__head">
        <p class="etd-compare-column-filters__title">{{ $sideLabel }} filters</p>
        <button type="button"
                class="etd-compare-column-filters__close"
                @click="closeFilters()"
                aria-label="Close filters">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="etd-compare-column-filters__body">
    @foreach ($baseQuery as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach

    <input type="hidden"
           name="{{ $prefix }}period"
           :value="presetKey === 'custom' || (dateFrom && dateTo) ? 'custom' : '{{ $period }}'">

    <div class="etd-compare-column-filters__section etd-compare-column-filters__dates"
         data-etd-date-range-single>
        <label class="etd-filter-compact-field">
            <span class="etd-filter-compact-label">Date range</span>
            <input type="text"
                   class="etd-flatpickr-date-range f-input w-full etd-compare-column-filters__input"
                   data-default-from="{{ $dateFrom }}"
                   data-default-to="{{ $dateTo }}"
                   placeholder="Select date range"
                   readonly
                   aria-label="Date range">
            <input type="hidden"
                   :name="presetKey === 'custom' || (dateFrom && dateTo) ? '{{ $prefix }}date_from' : null"
                   x-model="dateFrom"
                   data-range="from"
                   value="{{ $dateFrom }}">
            <input type="hidden"
                   :name="presetKey === 'custom' || (dateFrom && dateTo) ? '{{ $prefix }}date_to' : null"
                   x-model="dateTo"
                   data-range="to"
                   value="{{ $dateTo }}">
        </label>
    </div>

    <div class="etd-compare-column-filters__section">
        <p class="etd-compare-column-filters__section-label">Sessions &amp; audience</p>

        <div class="etd-compare-column-filters__grid">
        <label class="etd-filter-compact-field">
            <span class="etd-filter-compact-label">Device</span>
            <select name="{{ $prefix }}device_type" class="f-input w-full etd-compare-column-filters__input">
                <option value="" @selected(blank($filters['device_type'] ?? null))>All</option>
                @foreach (['desktop', 'mobile', 'tablet'] as $device)
                    <option value="{{ $device }}" @selected(($filters['device_type'] ?? '') === $device)>{{ ucfirst($device) }}</option>
                @endforeach
            </select>
        </label>

        <label class="etd-filter-compact-field">
            <span class="etd-filter-compact-label">Logged in</span>
            <select name="{{ $prefix }}logged_in" class="f-input w-full etd-compare-column-filters__input">
                <option value="" @selected(blank($filters['logged_in'] ?? null))>All</option>
                <option value="1" @selected(($filters['logged_in'] ?? '') === '1')>Logged in</option>
                <option value="0" @selected(($filters['logged_in'] ?? '') === '0')>Guest</option>
            </select>
        </label>

        <label class="etd-filter-compact-field">
            <span class="etd-filter-compact-label">Has order</span>
            <select name="{{ $prefix }}has_order" class="f-input w-full etd-compare-column-filters__input">
                <option value="" @selected(blank($filters['has_order'] ?? null))>All</option>
                <option value="1" @selected(($filters['has_order'] ?? '') === '1')>With order</option>
                <option value="0" @selected(($filters['has_order'] ?? '') === '0')>No order</option>
            </select>
        </label>

        <label class="etd-filter-compact-field">
            <span class="etd-filter-compact-label">Visitor type</span>
            <select name="{{ $prefix }}visitor_type" class="f-input w-full etd-compare-column-filters__input">
                <option value="" @selected(blank($filters['visitor_type'] ?? null))>All</option>
                <option value="human" @selected(($filters['visitor_type'] ?? '') === 'human')>Real visitors</option>
                <option value="bot" @selected(($filters['visitor_type'] ?? '') === 'bot')>Automated traffic</option>
                <option value="unclassified" @selected(($filters['visitor_type'] ?? '') === 'unclassified')>Not classified</option>
            </select>
        </label>

        <label class="etd-filter-compact-field">
            <span class="etd-filter-compact-label">UTM source</span>
            <input type="text"
                   name="{{ $prefix }}utm_source"
                   value="{{ $filters['utm_source'] ?? '' }}"
                   class="f-input w-full etd-compare-column-filters__input"
                   placeholder="e.g. google">
        </label>

        <label class="etd-filter-compact-field">
            <span class="etd-filter-compact-label">UTM medium</span>
            <input type="text"
                   name="{{ $prefix }}utm_medium"
                   value="{{ $filters['utm_medium'] ?? '' }}"
                   class="f-input w-full etd-compare-column-filters__input"
                   placeholder="e.g. cpc">
        </label>
        </div>
    </div>
    </div>

    <div class="etd-compare-column-filters__actions">
        <button type="submit" class="etd-header-btn etd-header-btn--primary etd-compare-column-filters__apply">Apply filters</button>
    </div>
</form>
