@props(['current' => 'dashboard'])

<div class="etd-tracker-nav">
    <a href="{{ route('admin.ecom-tracker.dashboard') }}"
       class="etd-tracker-nav-link {{ $current === 'dashboard' ? 'active' : '' }}">Store performance</a>
    <a href="{{ route('admin.ecom-tracker.visitors') }}"
       class="etd-tracker-nav-link {{ $current === 'visitors' ? 'active' : '' }}">Visitor analytics</a>
    @can('general.ecom_activity.index')
        <a href="{{ route('admin.ecom-activity.index') }}"
           class="etd-tracker-nav-link {{ $current === 'activity' ? 'active' : '' }}">User activity</a>
    @endcan
</div>
