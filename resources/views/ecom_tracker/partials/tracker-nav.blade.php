@props(['current' => 'dashboard'])

<div class="etd-tracker-nav">
    @can('ecom_tracker.dashboard.index')
    <a href="{{ route('admin.ecom-tracker.dashboard') }}"
       class="etd-tracker-nav-link {{ $current === 'dashboard' ? 'active' : '' }}">Dashboard</a>
    @endcan
    @can('ecom_tracker.visitors.index')
    <a href="{{ route('admin.ecom-tracker.visitors') }}"
       class="etd-tracker-nav-link {{ $current === 'visitors' ? 'active' : '' }}">Visitor analytics</a>
    @endcan
    @can('ecom_tracker.activity.index')
        <a href="{{ route('admin.ecom-activity.index') }}"
           class="etd-tracker-nav-link {{ $current === 'activity' ? 'active' : '' }}">User activity</a>
    @endcan
    @can('ecom_tracker.bot_traffic.index')
        <a href="{{ route('admin.ecom-tracker.bot-traffic') }}"
           class="etd-tracker-nav-link {{ $current === 'bot-traffic' ? 'active' : '' }}">Bot traffic</a>
    @endcan
</div>
