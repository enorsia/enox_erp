@extends('layouts.app')

@section('title', 'Activity Logs')

@section('content')
    <div class="p-5 lg:p-6">

        <!-- ── PAGE HEADER ── -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Activity Logs</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Track all user actions and system events
                </p>
            </div>
        </div>

        <!-- ── FILTER TOOLBAR ── -->
        <form method="GET" action="{{ route('admin.activity-logs.index') }}">
            {{--
                Mobile (<640px):
                  Row 1 — search input (full width)
                  Row 2 — user select + date_from + date_to (flex-wrap, each flex-1 min-w-[120px])
                  Row 3 — Search & Reset buttons
                sm+ (≥640px): single row — search flex-1, user w-36, dates auto, buttons auto
            --}}
            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 mb-5">

                <!-- Search — full width on mobile, flex-1 on sm+ -->
                <div class="relative w-full sm:flex-1 sm:min-w-0">
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                         fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" placeholder="Search description…"
                           value="{{ request('search') }}"
                           class="w-full pl-8 pr-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-accent-400 dark:focus:border-accent-400 transition-colors"/>
                </div>

                <!-- Secondary controls: user + dates — flex-wrap row on mobile, sm:contents on sm+ -->
                <div class="flex flex-wrap items-center gap-2 sm:contents">

                    <!-- User filter -->
                    <div class="flex-1 min-w-[120px] sm:flex-none sm:w-36">
                        <select name="user_id" class="tom-select w-full h-9" data-placeholder="All Users">
                            <option value="">All Users</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Date From -->
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="flex-1 min-w-[120px] sm:flex-none h-9 px-3 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"/>

                    <!-- Date To -->
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="flex-1 min-w-[120px] sm:flex-none h-9 px-3 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"/>

                </div>

                <!-- Buttons -->
                <div class="flex items-center gap-2 shrink-0">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors whitespace-nowrap">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        <span>Search</span>
                    </button>
                    <a href="{{ route('admin.activity-logs.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset</span>
                    </a>
                </div>

            </div>
        </form>

        <!-- ── ACTIVITY LOG CARDS ── -->
        <div class="flex flex-col gap-3">
            @if (!$activities->isEmpty())
                @foreach ($activities as $key => $activity)
                    @php
                        $eventColor = match($activity->event ?? '') {
                            'created' => 'badge-green',
                            'updated' => 'badge-blue',
                            'deleted' => 'badge-red',
                            default   => 'badge-amber',
                        };
                    @endphp
                    <div class="order-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">

                            <!-- Info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate max-w-[400px]">
                                        {{ $activity->description ?? 'N/A' }}
                                    </span>
                                    @if($activity->event)
                                        <span class="badge-custom {{ $eventColor }}">{{ ucfirst($activity->event) }}</span>
                                    @endif
                                    @if($activity->subject)
                                        <span class="badge-custom badge-blue">{{ class_basename($activity->subject_type) }}</span>
                                    @endif
                                </div>

                                <!-- Causer -->
                                @if($activity->causer)
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <div class="w-6 h-6 rounded-full overflow-hidden bg-slate-100 dark:bg-slate-700 flex-shrink-0">
                                            <img class="w-full h-full object-cover"
                                                 src="{{ $activity->causer->avatar ? cloudflareImage($activity->causer->avatar, 32) : cloudflareImage('eca4fbfc-baba-4ac2-0966-e8a13d097700', 32) }}"
                                                 alt="Avatar">
                                        </div>
                                        <div>
                                            <span class="text-[12px] font-medium text-slate-700 dark:text-slate-200">{{ $activity->causer->name ?? '' }}</span>
                                            <span class="text-[11px] text-slate-400 dark:text-slate-500 ml-1.5">{{ $activity->causer->email ?? '' }}</span>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-1">System</p>
                                @endif

                                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                                    {{ $activity->created_at ? $activity->created_at->format('d M Y, h:i A') : '' }}
                                    <span class="ml-1">({{ $activity->created_at ? $activity->created_at->diffForHumans() : '' }})</span>
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-1 flex-shrink-0">
                                @can('authentication.activity_logs.show')
                                    <a href="{{ route('admin.activity-logs.show', $activity->id) }}"
                                       class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                       title="View">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500">No activity logs found.</p>
                </div>
            @endif
        </div>

        <!-- ── PAGINATION ── -->
        @include('master.pagination', ['paginator' => $activities])

    </div>
@endsection

