@php
    use App\Support\EcomTrackerViewData;
    use App\Support\TrackerTime;

    $period = $period === '90d' ? '30d' : $period;
    $activePreset = match ($period) {
        'yesterday', '7d', '30d', 'custom' => $period,
        default => '24h',
    };
    $basePreset = in_array($period, ['24h', 'yesterday', '7d', '30d'], true) ? $period : '24h';
    $dayNav = EcomTrackerViewData::dashboardDayNavigation($baseQuery, $range, $routeName ?? 'admin.ecom-tracker.dashboard');
    $presetUrl = fn (string $preset) => route($routeName ?? 'admin.ecom-tracker.dashboard', array_merge($baseQuery, ['period' => $preset]));
@endphp

<div class="etd-date-nav">
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
</div>
