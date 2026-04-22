@extends('layouts.app')

@section('title', 'View Admin User')

@section('content')
    <div id="user-page-content"></div>
    <div class="max-w-6xl mx-auto px-5 py-6">
        <!-- PAGE HEADER -->
        <div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">{{ $user->name ?? '' }}</h1>
                    @if ($user->status)
                        <span class="badge-custom badge-green">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                            Active
                        </span>
                    @else
                        <span class="badge-custom badge-red">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            Inactive
                        </span>
                    @endif
                    @if ($user?->roles?->isNotEmpty())
                        <span class="badge-custom badge-blue">{{ strtoupper($user->roles->first()->name) }}</span>
                    @else
                        <span class="badge-custom badge-amber">No role</span>
                    @endif
                </div>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1.5 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                    Joined: {{ $user->created_at ? $user->created_at->format('d M Y, h:i A') : 'N/A' }}
                    &nbsp;·&nbsp;
                    Last updated: {{ $user->updated_at ? $user->updated_at->format('d M Y, h:i A') : 'N/A' }}
                </p>
            </div>

            <!-- Action buttons -->
            <div class="flex gap-2 flex-wrap">
                @can('authentication.users.edit')
                <a href="{{ route('admin.users.edit', $user->id) }}"
                   class="action-btn border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </a>
                @endcan
                <a href="{{ route('admin.users.index') }}"
                   class="action-btn border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back to list
                </a>
            </div>
        </div>

        <!-- CONTENT GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

            <!-- LEFT -->
            <div class="space-y-5">

                <!-- Profile Card -->
                <div class="info-card">
                    <div class="card-title-custom">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        Profile Information
                    </div>

                    <div class="flex items-center gap-4 mb-5">
                        <div class="w-20 h-20 rounded-full overflow-hidden bg-slate-100 dark:bg-slate-700 border-2 border-slate-200 dark:border-slate-600 flex-shrink-0">
                            <img class="w-full h-full object-cover"
                                 src="{{ $user->avatar ? cloudflareImage($user->avatar, 200) : cloudflareImage('eca4fbfc-baba-4ac2-0966-e8a13d097700', 200) }}"
                                 alt="Avatar">
                        </div>
                        <div>
                            <p class="text-lg font-semibold text-slate-700 dark:text-slate-200">{{ $user->name ?? '' }}</p>
                            <p class="text-sm text-accent-400">{{ $user->email ?? '' }}</p>
                            @if($user->designation)
                                <p class="text-xs text-slate-400 mt-0.5">{{ $user->designation }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
                        <div class="kv">
                            <p class="row-label">Full Name</p>
                            <p class="row-value">{{ $user->name ?? '' }}</p>
                        </div>
                        <div class="kv">
                            <p class="row-label">Email Address</p>
                            <p class="row-value text-accent-400">{{ $user->email ?? '' }}</p>
                        </div>
                        <div class="kv">
                            <p class="row-label">Designation</p>
                            <p class="row-value">{{ $user->designation ?? 'N/A' }}</p>
                        </div>
                        <div class="kv">
                            <p class="row-label">Role</p>
                            <p class="row-value">
                                @if ($user?->roles?->isNotEmpty())
                                    <span class="badge-custom badge-blue">{{ strtoupper($user->roles->first()->name) }}</span>
                                @else
                                    <span class="badge-custom badge-amber">No role found</span>
                                @endif
                            </p>
                        </div>
                        <div class="kv">
                            <p class="row-label">Status</p>
                            <p class="row-value">
                                @if ($user->status)
                                    <span class="badge-custom badge-green">Active</span>
                                @else
                                    <span class="badge-custom badge-red">Inactive</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="space-y-5">

                <!-- Account Details -->
                <div class="info-card">
                    <div class="card-title-custom">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Account Details
                    </div>
                    <div class="kv">
                        <p class="row-label">Joined At</p>
                        <p class="row-value">{{ $user->created_at ? $user->created_at->format('d M Y, h:i A') : 'N/A' }}</p>
                    </div>
                    <div class="kv">
                        <p class="row-label">Last Modified</p>
                        <p class="row-value">{{ $user->updated_at ? $user->updated_at->diffForHumans() : 'No modifications' }}</p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="info-card">
                    <div class="card-title-custom">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Quick Actions
                    </div>
                    <div class="space-y-2">
                        @can('authentication.users.edit')
                        <a href="{{ route('admin.users.edit', $user->id) }}"
                           class="w-full py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Edit User
                        </a>
                        @endcan
                        @can('authentication.users.delete')
                        <button onclick="deleteData({{ $user->id }})"
                                class="w-full py-2.5 text-[13px] rounded-lg border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 font-medium transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete User
                        </button>
                        <form id="delete-form-{{ $user->id }}" method="POST"
                              action="{{ route('admin.users.destroy', $user->id) }}" style="display: none;">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
