@php
    use App\Support\EcomTrackerViewData;
    use App\Support\TrackerTime;

    $side = $side ?? 'left';
    $otherSide = $side === 'right' ? 'left' : 'right';
    $period = $period === '90d' ? '30d' : $period;
    $activePreset = match ($period) {
        'yesterday', '7d', '30d', 'custom' => $period,
        default => '24h',
    };

    $otherFilters = $otherFilters ?? [];
    $baseQuery = array_merge(
        EcomTrackerViewData::compareSideQuery($otherSide, $otherFilters),
        filled($backUrl ?? null) ? ['back' => $backUrl] : (request()->filled('back') ? ['back' => request('back')] : []),
    );

    $sideFilters = array_merge($filters ?? [], ['period' => $period, 'date_from' => $dateFrom ?? '', 'date_to' => $dateTo ?? '']);
    $prefix = EcomTrackerViewData::compareSidePrefix($side);
    $sideQueryForPreset = function (string $preset) use ($sideFilters, $side, $baseQuery) {
        $next = collect($sideFilters)->except(['date_from', 'date_to'])->all();
        $next['period'] = $preset;

        if ($preset !== 'custom') {
            unset($next['date_from'], $next['date_to']);
        }

        return array_merge(
            $baseQuery,
            EcomTrackerViewData::compareSideQuery($side, array_filter($next, fn ($value) => filled($value) || $value === '24h')),
        );
    };

    $presetUrl = fn (string $preset) => route('admin.ecom-tracker.dashboard.compare', $sideQueryForPreset($preset));

    $currentSideFilters = array_filter(
        $sideFilters,
        fn ($value, $key) => $key === 'period' || filled($value),
        ARRAY_FILTER_USE_BOTH,
    );

    $dayNav = EcomTrackerViewData::dashboardDayNavigation(
        array_merge(
            $baseQuery,
            EcomTrackerViewData::compareSideQuery($side, $currentSideFilters),
        ),
        $range,
        'admin.ecom-tracker.dashboard.compare',
        "{$prefix}period",
        "{$prefix}date_from",
        "{$prefix}date_to",
    );
@endphp

<div class="etd-compare-period-nav">
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
