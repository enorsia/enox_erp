@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $user = Auth::user();
    $londonNow = now()->timezone('Europe/London');
    $hour = (int) $londonNow->format('H');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

<div id="enox_home" class="hidden"></div>
<div class="page-content">

    <div class="max-w-5xl mx-auto">

        {{-- ─── Welcome Hero ─── --}}
        <div class="section-card mb-5 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-72 h-72 bg-accent-400/5 dark:bg-accent-400/10 rounded-full -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-56 h-56 bg-accent-400/5 dark:bg-accent-400/10 rounded-full translate-y-1/2 -translate-x-1/4 pointer-events-none"></div>

            <div class="relative grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-8 items-center">
                <div>
                    <p class="text-[12px] font-medium text-accent-400 uppercase tracking-wider mb-1">{{ $greeting }}</p>
                    <h1 class="text-xl sm:text-2xl font-semibold text-slate-800 dark:text-slate-100 leading-tight">
                        Welcome back, {{ $user->name }}
                    </h1>
                    <p class="text-[13px] text-slate-500 dark:text-slate-400 mt-2 max-w-lg leading-relaxed">
                        You are signed in to your admin workspace. Use the sidebar to open the modules available for your account.
                    </p>

                    <div class="flex flex-wrap gap-3 mt-5">
                        @if($user->designation)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                <svg class="w-3.5 h-3.5 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.084-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                {{ $user->designation }}
                            </span>
                        @endif
                        @if($user->status)
                            <span class="badge badge-success">
                                <svg class="w-3 h-3 status-dot-active" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                Active Account
                            </span>
                        @endif
                    </div>
                </div>

                {{-- ─── London Clock ─── --}}
                @php
                    $sec = (int) $londonNow->format('s');
                    $min = (int) $londonNow->format('i');
                    $hr  = (int) $londonNow->format('H');
                    $secondDeg = $sec * 6;
                    $minuteDeg = $min * 6 + $sec * 0.1;
                    $hourDeg   = ($hr % 12) * 30 + $min * 0.5;
                @endphp
                <div class="london-clock-card mx-auto lg:mx-0">
                    <div class="london-clock" id="londonClock" aria-label="London time clock">
                        <div class="london-clock__ring"></div>
                        @foreach ([12 => '12', 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10', 11 => '11'] as $pos => $label)
                            <span class="london-clock__marker {{ in_array($pos, [12, 3, 6, 9]) ? 'london-clock__marker--major' : '' }}" style="--i: {{ $pos }}"></span>
                            @if(in_array($pos, [12, 3, 6, 9]))
                                <span class="london-clock__number" style="--i: {{ $pos }}">{{ $label }}</span>
                            @endif
                        @endforeach
                        <div class="london-clock__hand london-clock__hand--hour" id="londonHourHand" style="transform: translateX(-50%) rotate({{ $hourDeg }}deg)"></div>
                        <div class="london-clock__hand london-clock__hand--minute" id="londonMinuteHand" style="transform: translateX(-50%) rotate({{ $minuteDeg }}deg)"></div>
                        <div class="london-clock__hand london-clock__hand--second" id="londonSecondHand" style="transform: translateX(-50%) rotate({{ $secondDeg }}deg)"></div>
                        <div class="london-clock__center"></div>
                    </div>
                    <p class="london-clock__digital" id="londonDigitalTime">{{ $londonNow->format('H:i:s') }}</p>
                    <p class="london-clock__date" id="londonDigitalDate">{{ $londonNow->format('l, d M Y') }}</p>
                    <p class="london-clock__zone">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        London, United Kingdom · <span id="londonTzLabel">GMT</span>
                    </p>
                </div>
            </div>
        </div>

        {{-- ─── Info Cards ─── --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="info-card !mb-0">
                <div class="card-title">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                    </svg>
                    Your Workspace
                </div>
                <p class="text-[12px] text-slate-500 dark:text-slate-400 leading-relaxed mb-4">
                    This panel is permission-based. Every user lands here after login, and only the modules assigned to your role appear in the sidebar.
                </p>
                <ul class="space-y-3">
                    <li class="flex items-start gap-2.5 text-[12px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        <span class="w-5 h-5 rounded-full bg-accent-400/10 text-accent-400 text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">1</span>
                        Open the sidebar to browse modules available for your account.
                    </li>
                    <li class="flex items-start gap-2.5 text-[12px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        <span class="w-5 h-5 rounded-full bg-accent-400/10 text-accent-400 text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">2</span>
                        Your profile and password settings are always available from the header menu.
                    </li>
                    <li class="flex items-start gap-2.5 text-[12px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        <span class="w-5 h-5 rounded-full bg-accent-400/10 text-accent-400 text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">3</span>
                        Use the dark mode toggle in the header whenever you need a more comfortable view.
                    </li>
                </ul>
            </div>

            <div class="info-card !mb-0">
                <div class="card-title">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                    </svg>
                    Account Overview
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="kv !mb-0">
                        <p class="row-label">Signed in as</p>
                        <p class="row-value break-all">{{ $user->email }}</p>
                    </div>
                    <div class="kv !mb-0">
                        <p class="row-label">Functioning as</p>
                        <p class="row-value">{{ Str::limit($user?->designation ?? '-', 35, '...') }}</p>
                    </div>
                    <div class="kv !mb-0">
                        <p class="row-label">Account Status</p>
                        @if($user->status)
                            <span class="badge badge-success mt-1">Active</span>
                        @else
                            <span class="badge badge-danger mt-1">Inactive</span>
                        @endif
                    </div>
                    <div class="kv !mb-0">
                        <p class="row-label">Member Since</p>
                        <p class="row-value">{{ $user->created_at ? $user->created_at->format('d M Y') : '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
