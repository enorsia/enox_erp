@php
    use App\Support\EcomTrackerViewData;
@endphp

@can('ecom_tracker.dashboard.index')
    @if ($showDashboardLink ?? false)
        <a href="{{ $dashboardUrl ?? EcomTrackerViewData::dashboardShortcutUrl(request()) }}"
           class="etd-header-btn no-underline">Tracking</a>
    @endif
@endcan

@can('ecom_tracker.dashboard.index')
    @if ($showComparisonLink ?? false)
        <a href="{{ EcomTrackerViewData::compareShortcutUrl(request()) }}"
           class="etd-header-btn no-underline">Comparison</a>
    @endif
@endcan

@can('ecom_tracker.activity.index')
    @if ($showUserActivityLink ?? false)
        <a href="{{ EcomTrackerViewData::activityShortcutUrl(request()) }}"
           class="etd-header-btn no-underline">User Activity</a>
    @endif
@endcan
