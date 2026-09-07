@php
    use App\Support\EcomActivityFocus;
    use App\Support\TrackerMultiSelectFilter;

    $includeDateRange = $includeDateRange ?? true;
    $filterOptionCounts = $filterOptionCounts ?? [];
    $utmFilterState = $utmFilterState ?? null;
    $includeSessionSearch = $includeSessionSearch ?? true;
    $categoryFilterOptions = $categoryFilterOptions ?? ['departments' => [], 'categories_by_department' => []];
    $funnelOptions = EcomActivityFocus::sidebarFunnelFilterOptions();
    $selectedFunnels = EcomActivityFocus::drawerFunnelSelectedValues(request());
    $selectedDevices = TrackerMultiSelectFilter::allowedValues(request('device_type'), ['desktop', 'mobile', 'tablet']);
    $selectedDurations = TrackerMultiSelectFilter::requestValues(request(), 'duration_bucket');
    $selectedSources = $utmFilterState['selected_sources'] ?? TrackerMultiSelectFilter::requestValues(request(), 'utm_source');
    $selectedMediums = $utmFilterState['selected_mediums'] ?? TrackerMultiSelectFilter::requestValues(request(), 'utm_medium');
    $countLabel = static function (string $value, string $label, array $counts): string {
        return isset($counts[$value]) ? "{$label} ({$counts[$value]})" : $label;
    };
    $tomSelectClass = 'tom-select etd-tom-select w-full';
    $isSelected = static fn (array $selected, string $value): bool => in_array($value, $selected, true);
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
                <select name="funnel[]" multiple class="{{ $tomSelectClass }}" data-placeholder="All">
                    @foreach ($funnelOptions as $value => $label)
                        @continue($value === '')
                        <option value="{{ $value }}" @selected($isSelected($selectedFunnels, $value))>{{ $label }}</option>
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
                <select name="device_type[]" multiple class="{{ $tomSelectClass }}" data-placeholder="All">
                    @foreach (['desktop', 'mobile', 'tablet'] as $device)
                        <option value="{{ $device }}" @selected($isSelected($selectedDevices, $device))>{{ $countLabel($device, ucfirst($device), $filterOptionCounts['device_type'] ?? []) }}</option>
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
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">Duration</span>
                <select name="duration_bucket[]" multiple class="{{ $tomSelectClass }}" data-placeholder="All">
                    @foreach (\App\Support\SessionDurationBuckets::optionLabels() as $value => $label)
                        @continue($value === '')
                        <option value="{{ $value }}" @selected($isSelected($selectedDurations, (string) $value))>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="etd-activity-filter-section">
        <p class="etd-activity-filter-section-title">Traffic source</p>
        @php
            $sources = $utmFilterState['sources'] ?? [];
            $mediums = $utmFilterState['mediums'] ?? [];
        @endphp
        <div class="etd-activity-filter-grid">
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">UTM source</span>
                <select name="utm_source[]" multiple class="{{ $tomSelectClass }}" data-placeholder="All">
                    @foreach ($sources as $value => $label)
                        <option value="{{ $value }}" @selected($isSelected($selectedSources, $value))>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="etd-filter-compact-field">
                <span class="etd-filter-compact-label">UTM medium</span>
                <select name="utm_medium[]" multiple class="{{ $tomSelectClass }}" data-placeholder="All">
                    @foreach ($mediums as $value => $label)
                        <option value="{{ $value }}" @selected($isSelected($selectedMediums, $value))>{{ $label }}</option>
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
