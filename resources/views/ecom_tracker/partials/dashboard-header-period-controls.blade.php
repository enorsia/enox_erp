@php
    use App\Support\EcomTrackerViewData;
    use App\Support\TrackerTime;

    $period = ($period ?? '24h') === '90d' ? '30d' : ($period ?? '24h');
    $activePreset = match ($period) {
        'yesterday', '7d', '30d', 'custom' => $period,
        default => '24h',
    };
    $routeName = $routeName ?? 'admin.ecom-tracker.dashboard';

    $presetUrl = function (string $preset) use ($baseQuery, $routeName) {
        $query = $baseQuery ?? [];
        unset($query['date_from'], $query['date_to']);
        $query['period'] = $preset;

        return route($routeName, $query);
    };

    $dayNav = isset($range)
        ? EcomTrackerViewData::dashboardDayNavigation($baseQuery ?? [], $range, $routeName)
        : null;
@endphp

@if ($dayNav)
<div class="etd-header-period-nav etd-print-hide">
    <a href="{{ $dayNav['previous_url'] }}"
       class="etd-segmented-btn etd-date-nav-btn no-underline"
       aria-label="Previous day"
       title="Previous day">
        <svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>

    <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
        <a href="{{ $presetUrl('24h') }}" class="etd-segmented-btn {{ $activePreset === '24h' ? 'active' : '' }} no-underline" aria-label="{{ TrackerTime::todayPresetLabel() }}">{{ TrackerTime::todayPresetButtonLabel() }}</a>
        <a href="{{ $presetUrl('yesterday') }}" class="etd-segmented-btn {{ $activePreset === 'yesterday' ? 'active' : '' }} no-underline" aria-label="{{ TrackerTime::yesterdayPresetLabel() }}">{{ TrackerTime::yesterdayPresetButtonLabel() }}</a>
        <a href="{{ $presetUrl('7d') }}" class="etd-segmented-btn {{ $activePreset === '7d' ? 'active' : '' }} no-underline" aria-label="Last 7 days">7d</a>
        <a href="{{ $presetUrl('30d') }}" class="etd-segmented-btn {{ $activePreset === '30d' ? 'active' : '' }} no-underline" aria-label="Last 30 days">30d</a>
        <button type="button"
                class="etd-segmented-btn"
                :class="{ 'active': presetKey === 'custom' }"
                aria-label="Custom date range"
                @click="toggleCustom()">Custom</button>
    </div>

    @if ($dayNav['can_go_next'])
        <a href="{{ $dayNav['next_url'] }}"
           class="etd-segmented-btn etd-date-nav-btn no-underline"
           aria-label="Next day"
           title="Next day">
            <svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
    @else
        <span class="etd-segmented-btn etd-date-nav-btn is-disabled"
              aria-disabled="true"
              aria-label="Next day"
              title="Next day unavailable">
            <svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </span>
    @endif

    <div x-show="presetKey === 'custom'"
         x-cloak
         x-effect="if (presetKey === 'custom') { $nextTick(() => window.refreshEtdFilterControls?.($el)) }"
         class="etd-custom-dates etd-custom-dates--header"
         data-etd-date-range-single
         @if ($activePreset !== 'custom') style="display: none" @endif>
        <input type="text"
               class="etd-flatpickr-date-range f-input etd-date-input etd-date-input--range"
               data-default-from="{{ $dateFrom ?? '' }}"
               data-default-to="{{ $dateTo ?? '' }}"
               placeholder="Select date range"
               readonly
               aria-label="Custom date range">
        <input type="hidden"
               x-model="dateFrom"
               data-range="from"
               value="{{ $dateFrom ?? '' }}">
        <input type="hidden"
               x-model="dateTo"
               data-range="to"
               value="{{ $dateTo ?? '' }}">
        <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply" @click="applyCustom()">Apply</button>
    </div>
</div>
@endif
