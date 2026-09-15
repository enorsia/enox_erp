@php
    use App\Support\EcomTrackerViewData;
@endphp

@can('ecom_tracker.dashboard.index')
    @if ($showDashboardLink ?? false)
        <a href="{{ $dashboardUrl ?? EcomTrackerViewData::dashboardShortcutUrl(request()) }}"
           class="etd-header-btn etd-header-btn--icon no-underline">
            <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span class="etd-header-btn-text">Tracking</span>
        </a>
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
