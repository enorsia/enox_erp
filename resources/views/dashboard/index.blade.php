@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="p-5 lg:p-6">

        <!-- ── WELCOME BANNER ── -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6 mb-5 flex flex-col sm:flex-row items-center gap-5">
            <div class="w-16 h-16 rounded-xl bg-accent-400/10 dark:bg-accent-400/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-8 h-8 text-accent-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l4-8 4 4 4-8 4 5"/>
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Welcome to {{ config('app.name') }}</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">
                    Hello, <span class="text-slate-700 dark:text-slate-200 font-medium">{{ auth()->user()->name ?? 'Admin' }}</span>! Here's a quick overview of your portal.
                </p>
            </div>
            <div class="sm:ml-auto text-right flex-shrink-0">
                <p class="text-[11px] text-slate-400 dark:text-slate-500">Today</p>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ now()->format('d M Y') }}</p>
            </div>
        </div>

        <!-- ── QUICK ACCESS CARDS ── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">

            <!-- Users -->
            @can('authentication.users.index')
            <a href="{{ route('admin.users.index') }}"
               class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 hover:border-accent-200 dark:hover:border-accent-700 transition-colors group">
                <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 group-hover:text-accent-400 transition-colors">Admin Users</p>
                <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-0.5">Manage user accounts</p>
            </a>
            @endcan

            <!-- Roles -->
            @can('authentication.roles.index')
            <a href="{{ route('admin.roles.index') }}"
               class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 hover:border-accent-200 dark:hover:border-accent-700 transition-colors group">
                <div class="w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 group-hover:text-accent-400 transition-colors">Roles & Permissions</p>
                <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-0.5">Configure access control</p>
            </a>
            @endcan

            <!-- Platforms -->
            @can('settings.platforms.index')
            <a href="{{ route('admin.platforms.index') }}"
               class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 hover:border-accent-200 dark:hover:border-accent-700 transition-colors group">
                <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M20 7H4a1 1 0 00-1 1v10a1 1 0 001 1h16a1 1 0 001-1V8a1 1 0 00-1-1zM9 11h6M9 15h4"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 group-hover:text-accent-400 transition-colors">Platforms</p>
                <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-0.5">Manage sales platforms</p>
            </a>
            @endcan

            <!-- Activity Logs -->
            @can('authentication.activity_logs.index')
            <a href="{{ route('admin.activity-logs.index') }}"
               class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 hover:border-accent-200 dark:hover:border-accent-700 transition-colors group">
                <div class="w-10 h-10 rounded-lg bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-accent-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 group-hover:text-accent-400 transition-colors">Activity Logs</p>
                <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-0.5">View system activity</p>
            </a>
            @endcan

        </div>

        <!-- ── MAIN MODULE CARDS ── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            @canany([
                ...array_keys(Cache::get('permissions.available', [])['grouped']['general_chart'] ?? []),
                ...array_keys(Cache::get('permissions.available', [])['grouped']['general_fabrication'] ?? []),
                ...array_keys(Cache::get('permissions.available', [])['grouped']['general_expense'] ?? []),
                ...array_keys(Cache::get('permissions.available', [])['grouped']['general_forecasting'] ?? []),
                ...array_keys(Cache::get('permissions.available', [])['grouped']['general_discounts'] ?? [])
            ])
                <!-- Selling Chart -->
                <a href="{{ route('admin.selling_chart.index') }}"
                   class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 hover:border-accent-200 dark:hover:border-accent-700 transition-colors group">
                    <div class="w-10 h-10 rounded-lg bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center mb-3">
                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M20 7H4a1 1 0 00-1 1v10a1 1 0 001 1h16a1 1 0 001-1V8a1 1 0 00-1-1zM9 11h6M9 15h4"/></svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 group-hover:text-accent-400 transition-colors">Selling Chart</p>
                    <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-0.5">Manage selling charts and data</p>
                </a>
            @endcanany

            <!-- Profile -->
            <a href="{{ route('admin.profile') }}"
               class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 hover:border-accent-200 dark:hover:border-accent-700 transition-colors group">
                <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 group-hover:text-accent-400 transition-colors">My Profile</p>
                <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-0.5">View and edit your profile</p>
            </a>

        </div>

    </div>
@endsection
