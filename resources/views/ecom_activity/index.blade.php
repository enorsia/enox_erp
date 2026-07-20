@extends('layouts.app')

@section('title', 'User Activity')

@section('content')
@php
    $activeFilterCount = collect(['search', 'device_type', 'logged_in', 'has_order', 'date_from', 'date_to'])
        ->filter(fn (string $key) => filled(request($key)))
        ->count();
@endphp

<div class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-activity.index'),
        'resetUrl' => route('admin.ecom-activity.index'),
        'showActivityFilters' => true,
    ])

    @include('ecom_tracker.partials.tracker-nav', ['current' => 'activity'])

    <div class="etd-topbar">
        <div class="etd-topbar-intro">
            <h1 class="etd-page-title">User activity</h1>
            <p class="etd-page-desc">Browse individual visitor sessions and funnel actions</p>
            @include('ecom_tracker.partials.timezone-notice')
            @if ($activeFilterCount > 0)
                <p class="etd-filter-active-note">Filters applied — open Filters to change or reset.</p>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap shrink-0">
            <button type="button"
                    @click="drawerOpen = true"
                    class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilterCount > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                Filters
                @if ($activeFilterCount > 0)
                    <span class="bg-accent-400 text-white text-[9px] font-bold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilterCount }}</span>
                @endif
            </button>
        </div>
    </div>

    <div class="etd-panel">
        <div class="etd-table-scroll">
            <table class="etd-table w-full">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>User</th>
                        <th>Device</th>
                        <th>IP</th>
                        <th>Source</th>
                        <th class="etd-num">Actions</th>
                        <th>Duration</th>
                        <th>Last active</th>
                        <th class="etd-col-action">View</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td>
                                <span class="etd-chip" title="{{ $session->session_id }}">{{ Str::limit($session->session_id, 14) }}</span>
                                <div class="etd-subtle mt-0.5">{{ \App\Support\TrackerTime::toLocal($session->created_at)?->format('d M Y, H:i') }}</div>
                            </td>
                            <td>
                                @if ($session->user_name || $session->user_email)
                                    <div>{{ $session->user_name ?: '—' }}</div>
                                    <div class="etd-subtle">{{ $session->user_email }}</div>
                                @elseif ($session->is_logged_in && $session->user_id)
                                    <span class="etd-badge mid">User #{{ $session->user_id }}</span>
                                @else
                                    <span class="etd-badge low">Guest</span>
                                @endif
                            </td>
                            <td>
                                {{ ucfirst($session->device_type ?? '—') }}
                                <div class="etd-subtle">{{ $session->browser }} · {{ $session->os }}</div>
                            </td>
                            <td>{{ $session->ip ?? '—' }}</td>
                            <td class="etd-subtle">
                                @if ($session->utm_source)
                                    {{ $session->utm_source }}/{{ $session->utm_medium }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="etd-num">{{ $session->actions_count }}</td>
                            <td>{{ format_duration((int) ($session->session_duration_seconds ?? 0)) }}</td>
                            <td>{{ \App\Support\TrackerTime::toLocal($session->last_active_at)?->diffForHumans() ?? '—' }}</td>
                            <td class="etd-col-action">
                                @can('ecom_tracker.activity.show')
                                    <a href="{{ route('admin.ecom-activity.show', $session->session_id) }}" class="etd-link">View session</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-slate-500 py-10">No visitor sessions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('layouts.pagination', ['paginator' => $sessions])
</div>
@endsection
