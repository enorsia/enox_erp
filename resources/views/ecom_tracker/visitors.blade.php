@extends('layouts.app')

@section('title', 'Visitor Analytics')

@section('content')
@php
    $a = $analytics;
    $window = $filters['window'] ?? '7d';
    $hasCustomRange = filled($filters['datetime_from'] ?? null) && filled($filters['datetime_to'] ?? null);
    $activeFilterCount = $hasCustomRange
        ? 1
        : ((request()->has('window') && $window !== '7d') ? 1 : 0);
    $datetimeFromValue = filled($filters['datetime_from'] ?? null)
        ? \Carbon\Carbon::parse($filters['datetime_from'])->format('Y-m-d\TH:i')
        : '';
    $datetimeToValue = filled($filters['datetime_to'] ?? null)
        ? \Carbon\Carbon::parse($filters['datetime_to'])->format('Y-m-d\TH:i')
        : '';
    $presetWindows = [
        '3h' => '3 hours',
        '6h' => '6 hours',
        '12h' => '12 hours',
        '24h' => '24 hours',
        '7d' => '7 days',
        '30d' => '30 days',
        '90d' => '90 days',
        '1y' => '1 year',
    ];
@endphp

<div class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">

    {{-- Filter drawer backdrop --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="drawerOpen = false"
         class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]"
         style="display:none;"></div>

    {{-- Filter drawer --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 bottom-0 w-full sm:w-[340px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
         style="display:none;">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100">
                <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                Filters
            </div>
            <button type="button" @click="drawerOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="GET" action="{{ route('admin.ecom-tracker.visitors') }}" class="flex-1 flex flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Quick window</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($presetWindows as $key => $label)
                            <label class="flex items-center gap-2 px-3 py-2.5 rounded-lg border cursor-pointer transition-colors {{ ! $hasCustomRange && $window === $key ? 'border-accent-400 bg-accent-400/10 text-accent-700 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300' }}">
                                <input type="radio"
                                       name="window"
                                       value="{{ $key }}"
                                       class="text-accent-400 border-slate-300"
                                       {{ ! $hasCustomRange && $window === $key ? 'checked' : '' }}>
                                <span class="text-[13px] font-medium">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <hr class="border-slate-100 dark:border-slate-700"/>

                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Custom date range</p>
                    <p class="text-[12px] text-slate-500 dark:text-slate-400 mb-3">From and to override the quick window above.</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">From</label>
                            <input type="datetime-local"
                                   name="datetime_from"
                                   value="{{ $datetimeFromValue }}"
                                   class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400">
                        </div>
                        <div>
                            <label class="block text-[12px] text-slate-500 dark:text-slate-400 mb-1">To</label>
                            <input type="datetime-local"
                                   name="datetime_to"
                                   value="{{ $datetimeToValue }}"
                                   class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 shrink-0">
                <a href="{{ route('admin.ecom-tracker.visitors') }}"
                   class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">
                    Reset
                </a>
                <button type="submit"
                        class="flex-[2] py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <div class="etd-topbar">
        <div class="etd-topbar-intro">
            <h1 class="etd-page-title">Visitor analytics</h1>
            <p class="etd-page-desc">{{ $filters['window_label'] ?? 'Last 7 days' }}</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap shrink-0">
            <a href="{{ route('admin.ecom-tracker.visitors.export', request()->query()) }}"
               class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 transition-colors font-medium no-underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </a>
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

    @if ($activeFilterCount > 0)
        <div class="flex flex-wrap gap-2 mb-5">
            @if ($hasCustomRange)
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">Range:</span> {{ $filters['window_label'] }}
                    <a href="{{ request()->fullUrlWithQuery(['datetime_from' => null, 'datetime_to' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @elseif (request()->has('window'))
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">Window:</span> {{ $filters['window_label'] }}
                    <a href="{{ route('admin.ecom-tracker.visitors') }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @endif
            <a href="{{ route('admin.ecom-tracker.visitors') }}"
               class="flex items-center gap-1 text-[11px] text-slate-400 hover:text-red-500 px-2 py-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear all
            </a>
        </div>
    @endif

    <div class="etd-kpi-grid mb-5">
        <div class="etd-kpi">
            <div class="etd-kpi-label">Active visitors</div>
            <div class="etd-kpi-value">{{ number_format($a['summary']['active_visitors']) }}</div>
        </div>
        <div class="etd-kpi">
            <div class="etd-kpi-label">New visitors</div>
            <div class="etd-kpi-value">{{ number_format($a['summary']['new_visitors']) }}</div>
        </div>
        <div class="etd-kpi">
            <div class="etd-kpi-label">Sessions</div>
            <div class="etd-kpi-value">{{ number_format($a['summary']['sessions']) }}</div>
        </div>
        <div class="etd-kpi">
            <div class="etd-kpi-label">Avg session duration</div>
            <div class="etd-kpi-value">{{ $a['summary']['avg_session_duration_label'] }}</div>
        </div>
        <div class="etd-kpi">
            <div class="etd-kpi-label">Avg visitor stay</div>
            <div class="etd-kpi-value">{{ $a['summary']['avg_visitor_stay_label'] }}</div>
        </div>
        <div class="etd-kpi">
            <div class="etd-kpi-label">Total time on site</div>
            <div class="etd-kpi-value">{{ $a['summary']['total_stay_label'] }}</div>
        </div>
    </div>

    <div class="etd-panel mb-5">
        <h2 class="etd-panel-title">Rolling windows</h2>
        <div class="overflow-x-auto">
            <table class="etd-table w-full">
                <thead>
                    <tr>
                        <th>Window</th>
                        <th class="etd-num">Active</th>
                        <th class="etd-num">New</th>
                        <th class="etd-num">Sessions</th>
                        <th class="etd-num">Avg stay</th>
                        <th class="etd-num">Total stay</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($a['rolling_windows'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="etd-num">{{ number_format($row['active_visitors']) }}</td>
                            <td class="etd-num">{{ number_format($row['new_visitors']) }}</td>
                            <td class="etd-num">{{ number_format($row['sessions']) }}</td>
                            <td class="etd-num">{{ $row['avg_stay_label'] }}</td>
                            <td class="etd-num">{{ $row['total_stay_label'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="etd-panel mb-5">
        <h2 class="etd-panel-title">Session duration distribution</h2>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            @foreach ($a['duration_buckets'] as $bucket)
                <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3 text-center">
                    <div class="text-2xl font-semibold">{{ number_format($bucket['count']) }}</div>
                    <div class="text-xs text-slate-500 mt-1">{{ $bucket['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="etd-panel">
        <h2 class="etd-panel-title">Per-visitor stay time</h2>
        <div class="overflow-x-auto">
            <table class="etd-table w-full">
                <thead>
                    <tr>
                        <th>Visitor ID</th>
                        <th class="etd-num">Sessions</th>
                        <th class="etd-num">Total stay</th>
                        <th class="etd-num">Avg / session</th>
                        <th>First seen</th>
                        <th>Last active</th>
                        <th>Device</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($a['visitors'] as $visitor)
                        <tr>
                            <td>
                                <code class="text-xs" title="{{ $visitor['visitor_id'] }}">{{ Str::limit($visitor['visitor_id'], 12) }}</code>
                            </td>
                            <td class="etd-num">{{ $visitor['session_count'] }}</td>
                            <td class="etd-num">{{ $visitor['total_stay_label'] }}</td>
                            <td class="etd-num">{{ $visitor['avg_stay_label'] }}</td>
                            <td>{{ $visitor['first_seen_at'] ? \Carbon\Carbon::parse($visitor['first_seen_at'])->format('d M Y, H:i') : '—' }}</td>
                            <td>{{ $visitor['last_active_at'] ? \Carbon\Carbon::parse($visitor['last_active_at'])->diffForHumans() : '—' }}</td>
                            <td>{{ trim(($visitor['device_type'] ?? '') . ' · ' . ($visitor['browser'] ?? ''), ' ·') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-slate-500 py-8">No visitors in this window.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($a['visitors']->hasPages())
            <div class="mt-4">
                {{ $a['visitors']->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
