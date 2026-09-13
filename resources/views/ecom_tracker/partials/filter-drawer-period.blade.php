@php
    use App\Support\EcomTrackerViewData;
    use App\Support\TrackerTime;

    $period = ($period ?? '24h') === '90d' ? '30d' : ($period ?? '24h');
    $activePreset = match ($period) {
        'yesterday', '7d', '30d', 'custom', 'all' => $period,
        default => '24h',
    };
    $dayNav = ($showDayNavigation ?? true) && isset($range)
        ? EcomTrackerViewData::dashboardDayNavigation($baseQuery ?? [], $range, $routeName ?? 'admin.ecom-tracker.dashboard')
        : null;
    $periodOptions = [
        '24h' => TrackerTime::todayPresetButtonLabel(),
        'yesterday' => TrackerTime::yesterdayPresetButtonLabel(),
        '7d' => '7 days',
        '30d' => '30 days',
        'custom' => 'Custom range',
    ];
    $useActivitySection = ($sectionStyle ?? 'default') === 'activity';
    $dateFieldIdPrefix = $useActivitySection ? 'activity-filter' : 'filter';
    $fromLocal = isset($range['from']) ? TrackerTime::toLocal($range['from'])?->toDateString() : null;
    $toLocal = isset($range['to']) ? TrackerTime::toLocal($range['to'])?->toDateString() : null;
    $todayLocal = TrackerTime::localNow()->startOfDay()->toDateString();
    $yesterdayLocal = TrackerTime::localNow()->subDay()->startOfDay()->toDateString();
    $periodNavConfig = ($dayNav && $fromLocal && $toLocal) ? [
        'period' => $period,
        'rangeFrom' => $fromLocal,
        'rangeTo' => $toLocal,
        'today' => $todayLocal,
        'yesterday' => $yesterdayLocal,
        'todayLabel' => TrackerTime::todayPresetLabel(),
        'yesterdayLabel' => TrackerTime::yesterdayPresetLabel(),
        'initialLabel' => $range['label'] ?? '',
        'canGoNext' => $dayNav['can_go_next'],
    ] : null;
@endphp

<section @class([
    'etd-filter-period',
    'etd-activity-filter-section etd-activity-filter-section--full' => $useActivitySection,
])
         @if ($periodNavConfig)
             x-data="etdFilterPeriod(@js($periodNavConfig))"
         @else
             x-data="{ drawerPeriod: @js($period) }"
         @endif>
    <p class="etd-activity-filter-section-title">Date range</p>

    @if ($dayNav)
        <div class="etd-filter-period__nav">
            <button type="button"
                    class="etd-filter-period__nav-btn"
                    aria-label="Previous day"
                    title="Previous day"
                    @if ($periodNavConfig) @click="shiftDay(-1)" @endif>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <span class="etd-filter-period__nav-label"
                  @if ($periodNavConfig) x-text="navLabel" @endif>{{ $range['label'] ?? '' }}</span>
            <button type="button"
                    class="etd-filter-period__nav-btn"
                    aria-label="Next day"
                    title="Next day"
                    @if ($periodNavConfig)
                        x-show="canGoNext"
                        x-cloak
                        @click="shiftDay(1)"
                    @endif
                    @unless ($periodNavConfig) hidden @endunless>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <span class="etd-filter-period__nav-btn is-disabled"
                  aria-disabled="true"
                  title="Next day unavailable"
                  @if ($periodNavConfig)
                      x-show="!canGoNext"
                      x-cloak
                  @else
                      @unless ($dayNav['can_go_next']) hidden @endunless
                  @endif>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </span>
        </div>
    @endif

    <div class="etd-filter-period__fields">
        <label class="etd-filter-compact-field">
            <span class="etd-filter-compact-label">Period</span>
            <select name="period"
                    class="tom-select etd-tom-select w-full"
                    data-placeholder="Select period"
                    @change="drawerPeriod = $event.target.value">
                @if ($periodAllowsAll ?? false)
                    <option value="" @selected(! request()->filled('period'))>All</option>
                @endif
                @foreach ($periodOptions as $periodKey => $periodOptionLabel)
                    <option value="{{ $periodKey }}" @selected($period === $periodKey)>{{ $periodOptionLabel }}</option>
                @endforeach
            </select>
        </label>

        <div x-show="drawerPeriod === 'custom'"
             x-collapse
             x-effect="syncEtdFlatpickrEnabled($el, drawerPeriod === 'custom')"
             @class([
                 'etd-date-range etd-filter-period__custom',
                 'etd-activity-filter-grid' => $useActivitySection,
                 'grid grid-cols-1 sm:grid-cols-2 gap-2' => ! $useActivitySection,
             ])
             data-etd-date-range>
            <label class="etd-filter-compact-field" for="{{ $dateFieldIdPrefix }}-date-from">
                <span class="etd-filter-compact-label">From</span>
                <input type="text"
                       id="{{ $dateFieldIdPrefix }}-date-from"
                       name="date_from"
                       value="{{ $dateFrom }}"
                       data-range="from"
                       data-default="{{ $dateFrom }}"
                       placeholder="Select date"
                       readonly
                       class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
            </label>
            <label class="etd-filter-compact-field" for="{{ $dateFieldIdPrefix }}-date-to">
                <span class="etd-filter-compact-label">To</span>
                <input type="text"
                       id="{{ $dateFieldIdPrefix }}-date-to"
                       name="date_to"
                       value="{{ $dateTo }}"
                       data-range="to"
                       data-default="{{ $dateTo }}"
                       placeholder="Select date"
                       readonly
                       class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
            </label>
        </div>

        @if ($useActivitySection)
            <p class="etd-filter-period__timezone">
                @include('ecom_tracker.partials.timezone-notice')
            </p>
        @endif
    </div>
</section>
