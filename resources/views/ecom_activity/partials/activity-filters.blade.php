@php
    use App\Support\EcomActivityFocus;

    $includeDateRange = $includeDateRange ?? true;
    $filterOptionCounts = $filterOptionCounts ?? [];
    $utmFilterState = $utmFilterState ?? null;
    $includeSessionSearch = $includeSessionSearch ?? true;
    $categoryFilterOptions = $categoryFilterOptions ?? ['departments' => [], 'categories_by_department' => []];
    $funnelOptions = EcomActivityFocus::sidebarFunnelFilterOptions();
    $selectedFunnel = EcomActivityFocus::drawerFunnelSelectedValue(request());
    $countLabel = static function (string $value, string $label, array $counts): string {
        return isset($counts[$value]) ? "{$label} ({$counts[$value]})" : $label;
    };
    $tomSelectClass = 'tom-select etd-tom-select w-full';
@endphp

<div class="etd-activity-filter-sections">
    @if ($includeSessionSearch)
        <section class="etd-activity-filter-section etd-activity-filter-section--full">
            <p class="etd-activity-filter-section-title">Search</p>
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">Keyword</span>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Session, visitor, email, phone, IP, product, SKU, category, department, UTM…"
                       class="etd-filter-input etd-filter-input--sm w-full">
            </label>
        </section>
    @endif

    @if ($includeDateRange)
        <section class="etd-activity-filter-section etd-activity-filter-section--full">
            <p class="etd-activity-filter-section-title">Date range</p>
            <div class="etd-activity-filter-grid">
                <label class="etd-filter-compact-field" for="activity-date-from">
                    <span class="etd-filter-compact-label">From</span>
                    <input type="text"
                           id="activity-date-from"
                           name="date_from"
                           value="{{ request('date_from') }}"
                           data-range="from"
                           data-default="{{ request('date_from') }}"
                           placeholder="Select date"
                           readonly
                           class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
                </label>
                <label class="etd-filter-compact-field" for="activity-date-to">
                    <span class="etd-filter-compact-label">To</span>
                    <input type="text"
                           id="activity-date-to"
                           name="date_to"
                           value="{{ request('date_to') }}"
                           data-range="to"
                           data-default="{{ request('date_to') }}"
                           placeholder="Select date"
                           readonly
                           class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
                </label>
            </div>
        </section>
    @endif

    <section class="etd-activity-filter-section">
        <p class="etd-activity-filter-section-title">Funnel</p>
        <div class="etd-activity-filter-grid">
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">Funnel stage</span>
                <select name="funnel" class="{{ $tomSelectClass }}" data-placeholder="All">
                    @foreach ($funnelOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedFunnel === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">Has order</span>
                <select name="has_order" class="{{ $tomSelectClass }}" data-placeholder="All">
                    <option value="" @selected(request('has_order', '') === '')>All</option>
                    <option value="1" @selected(request('has_order') === '1')>{{ $countLabel('1', 'With order', $filterOptionCounts['has_order'] ?? []) }}</option>
                    <option value="0" @selected(request('has_order') === '0')>{{ $countLabel('0', 'No order', $filterOptionCounts['has_order'] ?? []) }}</option>
                </select>
            </label>
        </div>
    </section>

    <section class="etd-activity-filter-section">
        <p class="etd-activity-filter-section-title">Session</p>
        <div class="etd-activity-filter-grid">
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">Device</span>
                <select name="device_type" class="{{ $tomSelectClass }}" data-placeholder="All">
                    <option value="" @selected(request('device_type', '') === '')>All</option>
                    @foreach (['desktop', 'mobile', 'tablet'] as $device)
                        <option value="{{ $device }}" @selected(request('device_type') === $device)>{{ $countLabel($device, ucfirst($device), $filterOptionCounts['device_type'] ?? []) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">Logged in</span>
                <select name="logged_in" class="{{ $tomSelectClass }}" data-placeholder="All">
                    <option value="" @selected(request('logged_in', '') === '')>All</option>
                    <option value="1" @selected(request('logged_in') === '1')>{{ $countLabel('1', 'Logged in', $filterOptionCounts['logged_in'] ?? []) }}</option>
                    <option value="0" @selected(request('logged_in') === '0')>{{ $countLabel('0', 'Guest', $filterOptionCounts['logged_in'] ?? []) }}</option>
                </select>
            </label>
        </div>
    </section>

    <section class="etd-activity-filter-section">
        <p class="etd-activity-filter-section-title">Traffic source</p>
        @php
            $sources = $utmFilterState['sources'] ?? [];
            $mediums = $utmFilterState['mediums'] ?? [];
            $selectedSource = $utmFilterState['selected_source'] ?? '';
            $selectedMedium = $utmFilterState['selected_medium'] ?? '';
        @endphp
        <div class="etd-activity-filter-grid">
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">UTM source</span>
                <select name="utm_source" class="{{ $tomSelectClass }}" data-placeholder="All">
                    <option value="" @selected($selectedSource === '')>All</option>
                    @foreach ($sources as $value => $label)
                        <option value="{{ $value }}" @selected($selectedSource === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">UTM medium</span>
                <select name="utm_medium" class="{{ $tomSelectClass }}" data-placeholder="All">
                    <option value="" @selected($selectedMedium === '')>All</option>
                    @foreach ($mediums as $value => $label)
                        <option value="{{ $value }}" @selected($selectedMedium === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="etd-activity-filter-section">
        @include('ecom_tracker.partials.catalog-department-category-filters', [
            'filterOptions' => $categoryFilterOptions,
            'sectionHeading' => 'Product / category',
            'layout' => 'grid',
        ])
    </section>
</div>
