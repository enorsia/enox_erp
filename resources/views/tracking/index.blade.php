@extends('layouts.app')

@section('title', 'User Tracking')

@section('content')
    <div class="p-5 lg:p-6">

        {{-- ── PAGE HEADER ── --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">User Tracking</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">
                    {{ number_format($total) }} unique visitors tracked via Enox Analytics
                </p>
            </div>
        </div>

        {{-- ── SEARCH ── --}}
        <form method="GET" action="{{ route('admin.tracking.index') }}">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-5">
                <div class="relative flex-1">
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                         fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/>
                        <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" placeholder="Search by visitor ID, IP, country or city…"
                           value="{{ $search }}"
                           class="w-full pl-8 pr-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-accent-400 transition-colors"/>
                </div>
                <div class="flex gap-2 shrink-0">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors whitespace-nowrap">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        Search
                    </button>
                    @if($search)
                        <a href="{{ route('admin.tracking.index') }}"
                           class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap">
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- ── TABLE ── --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80">
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">#</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Visitor ID</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Device / Browser</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Location</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Sessions</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Events</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">First Seen</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Last Seen</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Orders</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($users as $i => $user)
                        @php
                            $rowNum     = ($page - 1) * $perPage + $i + 1;
                            $profile    = $user['profile'] ?? null;
                            $firstName  = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
                            $email      = $profile['email'] ?? '';
                            $phone      = $profile['phone'] ?? '';
                            $isGuest    = empty($firstName) && empty($email);
                            $guestLabel = 'Guest ' . $rowNum;
                            $deviceIcon = match(strtolower($user['device_type'] ?? '')) {
                                'mobile'  => '📱',
                                'tablet'  => '📟',
                                default   => '🖥️',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                            <td class="px-4 py-3 text-slate-400 dark:text-slate-500">{{ $rowNum }}</td>

                            {{-- Visitor ID --}}
                            <td class="px-4 py-3 max-w-[200px]">
                                @if(!$isGuest)
                                    {{-- Identified user --}}
                                    @if($firstName)
                                        <div class="font-semibold text-slate-800 dark:text-slate-100 truncate" title="{{ $firstName }}">{{ $firstName }}</div>
                                    @endif
                                    @if($email)
                                        <div class="text-[12px] text-indigo-500 dark:text-indigo-400 truncate mt-0.5" title="{{ $email }}">{{ $email }}</div>
                                    @endif
                                    @if($phone)
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $phone }}</div>
                                    @endif
                                @else
                                    {{-- Anonymous / Guest --}}
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="font-semibold text-slate-500 dark:text-slate-400">{{ $guestLabel }}</span>
                                    </div>
                                    @if($user['ip_address'])
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 font-mono">{{ $user['ip_address'] }}</div>
                                    @endif
                                    @if(!empty($user['country']))
                                        <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $user['country'] }}</div>
                                    @endif
                                @endif
                            </td>

                            {{-- Device --}}
                            <td class="px-4 py-3">
                                <span class="text-base mr-1">{{ $deviceIcon }}</span>
                                <span class="text-slate-600 dark:text-slate-300">{{ $user['browser'] ?: '—' }}</span>
                                @if($user['os'])
                                    <span class="text-[11px] text-slate-400 dark:text-slate-500 ml-1">({{ $user['os'] }})</span>
                                @endif
                            </td>

                            {{-- Location --}}
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                {{ implode(', ', array_filter([$user['city'], $user['country']])) ?: '—' }}
                            </td>

                            {{-- Sessions --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                                    {{ number_format($user['total_sessions']) }}
                                </span>
                            </td>

                            {{-- Events --}}
                            <td class="px-4 py-3">
                                <span class="font-medium text-slate-700 dark:text-slate-200">{{ number_format($user['total_events']) }}</span>
                            </td>

                            {{-- First Seen --}}
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap text-[12px]">
                                {{ $user['first_seen'] ? \Carbon\Carbon::parse($user['first_seen'])->format('d M Y, H:i') : '—' }}
                            </td>

                            {{-- Last Seen --}}
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400 whitespace-nowrap text-[12px]">
                                {{ $user['last_seen'] ? \Carbon\Carbon::parse($user['last_seen'])->format('d M Y, H:i') : '—' }}
                            </td>

                            {{-- Orders --}}
                            <td class="px-4 py-3">
                                @if($user['orders'] > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">
                                        {{ $user['orders'] }} order{{ $user['orders'] != 1 ? 's' : '' }}
                                    </span>
                                @else
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @endif
                            </td>

                            {{-- Action --}}
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.tracking.journey', ['anonymousId' => $user['anonymous_id']]) }}"
                                   class="inline-flex items-center gap-1.5 px-3 h-7 text-[12px] font-semibold rounded-lg bg-accent-400/10 hover:bg-accent-400/20 text-accent-600 dark:text-accent-300 transition-colors whitespace-nowrap">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View Journey
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-slate-400 dark:text-slate-500 text-sm">
                                No visitors found.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── PAGINATION ── --}}
        @if($totalPages > 1)
            <div class="flex items-center justify-between mt-5 gap-4 flex-wrap">
                <p class="text-[13px] text-slate-500 dark:text-slate-400">
                    Showing {{ ($page - 1) * $perPage + 1 }}–{{ min($page * $perPage, $total) }} of {{ number_format($total) }} visitors
                </p>
                <div class="flex items-center gap-1">
                    @if($page > 1)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}"
                           class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-[13px]">‹</a>
                    @endif

                    @for($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}"
                           class="w-8 h-8 flex items-center justify-center rounded-lg border text-[13px] transition-colors
                                  {{ $p === $page
                                     ? 'border-accent-400 bg-accent-400 text-white font-semibold'
                                     : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                            {{ $p }}
                        </a>
                    @endfor

                    @if($page < $totalPages)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}"
                           class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-[13px]">›</a>
                    @endif
                </div>
            </div>
        @endif

    </div>
@endsection

