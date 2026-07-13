@extends('layouts.app')

@section('title', 'User Activity')

@section('content')
    <div class="p-5 lg:p-6">

        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">User Activity</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Ecommerce visitor sessions and funnel actions</p>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.ecom-activity.index') }}">
            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 mb-5">
                <div class="relative w-full sm:flex-1 sm:min-w-0">
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                         fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" placeholder="Search session ID, name, email or IP…"
                           value="{{ request('search') }}"
                           class="w-full pl-8 pr-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-accent-400 transition-colors"/>
                </div>

                <select name="device_type" class="h-9 px-3 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200">
                    <option value="">All devices</option>
                    @foreach (['desktop', 'mobile', 'tablet'] as $device)
                        <option value="{{ $device }}" {{ request('device_type') === $device ? 'selected' : '' }}>{{ ucfirst($device) }}</option>
                    @endforeach
                </select>

                <select name="logged_in" class="h-9 px-3 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200">
                    <option value="">All users</option>
                    <option value="1" {{ request('logged_in') === '1' ? 'selected' : '' }}>Logged in</option>
                    <option value="0" {{ request('logged_in') === '0' ? 'selected' : '' }}>Guest</option>
                </select>

                <select name="has_order" class="h-9 px-3 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200">
                    <option value="">All sessions</option>
                    <option value="1" {{ request('has_order') === '1' ? 'selected' : '' }}>With order</option>
                    <option value="0" {{ request('has_order') === '0' ? 'selected' : '' }}>No order</option>
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="h-9 px-3 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"/>

                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="h-9 px-3 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"/>

                <div class="flex items-center gap-2 shrink-0">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        Search
                    </button>
                    <a href="{{ route('admin.ecom-activity.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[13px]">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Session</th>
                            <th class="px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">User</th>
                            <th class="px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Device</th>
                            <th class="px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">IP</th>
                            <th class="px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">UTM</th>
                            <th class="px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Actions</th>
                            <th class="px-4 py-3 font-semibold text-slate-500 dark:text-slate-400">Last active</th>
                            <th class="px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 text-right">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @forelse ($sessions as $session)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-mono text-[12px] text-slate-700 dark:text-slate-200" title="{{ $session->session_id }}">
                                        {{ Str::limit($session->session_id, 14) }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ $session->created_at?->format('d M Y, h:i A') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($session->user_name || $session->user_email)
                                        <div class="text-slate-700 dark:text-slate-200">{{ $session->user_name ?: '—' }}</div>
                                        <div class="text-[11px] text-slate-400">{{ $session->user_email }}</div>
                                    @elseif ($session->is_logged_in && $session->user_id)
                                        <span class="badge-custom badge-green">User #{{ $session->user_id }}</span>
                                    @else
                                        <span class="badge-custom badge-amber">Guest</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                    {{ ucfirst($session->device_type ?? '—') }}
                                    <div class="text-[11px] text-slate-400">{{ $session->browser }} · {{ $session->os }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $session->ip ?? '—' }}</td>
                                <td class="px-4 py-3 text-[11px] text-slate-500 dark:text-slate-400">
                                    @if ($session->utm_source)
                                        {{ $session->utm_source }}/{{ $session->utm_medium }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center justify-center min-w-[28px] h-7 px-2 rounded-lg bg-accent-400/10 text-accent-600 dark:text-accent-300 font-semibold text-[12px]">
                                        {{ $session->actions_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                    {{ $session->last_active_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('general.ecom_activity.show')
                                        <a href="{{ route('admin.ecom-activity.show', $session->session_id) }}"
                                           class="inline-flex items-center gap-1.5 px-3 h-8 text-[12px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                                            View
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-slate-400 dark:text-slate-500">
                                    No visitor sessions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('layouts.pagination', ['paginator' => $sessions])
    </div>
@endsection
