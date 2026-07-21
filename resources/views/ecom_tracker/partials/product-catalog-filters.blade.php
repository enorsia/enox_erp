@props([
    'filterOptions' => ['categories' => [], 'colors' => [], 'sizes' => []],
    'eventScenarioOptions' => [],
    'sortGroups' => [],
    'activityOptions' => [],
    'currentSort' => 'top_revenue',
])

@php
    $selectedScenario = request('event_scenario', '');
    $selectedSort = request('sort_by', '');
    $selectedActivity = request('activity', '');
    $tomSelectClass = 'tom-select etd-tom-select w-full';
@endphp

<div class="etd-product-filters-compact">
    <label class="etd-filter-compact-field" for="product-catalog-search">
        <span class="etd-filter-compact-label">Search</span>
        <input type="search"
               id="product-catalog-search"
               name="search"
               value="{{ request('search') }}"
               placeholder="Product name or SKU"
               class="etd-filter-input etd-filter-input--sm">
    </label>

    <label class="etd-filter-compact-field">
        <span class="etd-filter-compact-label">Color</span>
        <select id="product-catalog-color" name="color" class="{{ $tomSelectClass }}" data-placeholder="All">
            <option value="" @selected(request('color', '') === '')>All</option>
            @foreach ($filterOptions['colors'] ?? [] as $color)
                <option value="{{ $color }}" @selected(request('color') === $color)>{{ $color }}</option>
            @endforeach
        </select>
    </label>

    <label class="etd-filter-compact-field">
        <span class="etd-filter-compact-label">Size</span>
        <select id="product-catalog-size" name="size" class="{{ $tomSelectClass }}" data-placeholder="All">
            <option value="" @selected(request('size', '') === '')>All</option>
            @foreach ($filterOptions['sizes'] ?? [] as $size)
                <option value="{{ $size }}" @selected(request('size') === $size)>{{ $size }}</option>
            @endforeach
        </select>
    </label>

    <label class="etd-filter-compact-field">
        <span class="etd-filter-compact-label">Sort</span>
        <select id="product-catalog-sort" name="sort_by" class="{{ $tomSelectClass }}" data-placeholder="All">
            <option value="" @selected($selectedSort === '')>All</option>
            @foreach ($sortGroups as $group)
                <optgroup label="{{ $group['label'] }}">
                    @foreach ($group['options'] as $value => $option)
                        <option value="{{ $value }}" @selected($selectedSort === $value)>{{ $option['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </label>

    <label class="etd-filter-compact-field">
        <span class="etd-filter-compact-label">Funnel</span>
        <select id="product-catalog-funnel" name="event_scenario" class="{{ $tomSelectClass }}" data-placeholder="All">
            @foreach ($eventScenarioOptions as $value => $label)
                <option value="{{ $value }}" @selected($selectedScenario === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label class="etd-filter-compact-field">
        <span class="etd-filter-compact-label">Activity</span>
        <select id="product-catalog-activity" name="activity" class="{{ $tomSelectClass }}" data-placeholder="All">
            @foreach ($activityOptions as $value => $label)
                <option value="{{ $value }}" @selected($selectedActivity === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
</div>
