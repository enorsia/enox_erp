@extends('layouts.app')

@section('title', 'Admin Users')

@section('content')
    <div id="user-page-content"></div>
    <div class="p-5 lg:p-6">
        <!-- ── PAGE HEADER ── -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Admin Users</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage all admin users and their roles
                </p>
            </div>
            @can('authentication.users.create')
                <a href="{{ route('admin.users.create') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    Create User
                </a>
            @endcan
        </div>

        <!-- ── FILTER TOOLBAR ── -->
        <form method="get" action="{{ route('admin.users.index') }}">
            <div class="flex flex-wrap items-center gap-2.5 mb-5">

                <!-- Left 50/50 wrapper -->
                <div class="flex flex-1 gap-2.5 min-w-0">

                    <!-- Search (50%) -->
                    <div class="relative w-1/2">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                             fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" name="search" placeholder="Search by name or email..."
                               value="{{ request('search') }}"
                               class="w-full pl-9 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-accent-400 dark:focus:border-accent-400 transition-colors"/>

                    </div>

                    <!-- Role filter (50%) -->
                    <div class="w-1/2">
                        <select name="role_id" class="tom-select w-full" data-placeholder="Select Role">
                            <option value="">Select Role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>
                                    {{ $role->name ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <!-- Search button -->
                <button type="submit"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/>
                        <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                    </svg>
                    Search
                </button>

                <!-- Reset -->
                <a href="{{ route('admin.users.index') }}"
                   class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset
                </a>
            </div>
        </form>

        <!-- ── USER CARDS LIST ── -->
        <div class="flex flex-col gap-3">
            @if (!$users->isEmpty())
                @foreach ($users as $key => $user)
                    <div class="order-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 grid grid-cols-[44px_1fr_auto] sm:grid-cols-[48px_1fr_auto] gap-3 items-center">
                        <!-- Avatar -->
                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-full overflow-hidden bg-slate-100 dark:bg-slate-700 flex-shrink-0">
                            <img class="w-full h-full object-cover"
                                 src="{{ $user->avatar ? cloudflareImage($user->avatar, 48) : cloudflareImage('eca4fbfc-baba-4ac2-0966-e8a13d097700', 48) }}"
                                 alt="Avatar">
                        </div>

                        <!-- Info -->
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $user->name ?? '' }}</span>
                                @if ($user?->roles?->isNotEmpty())
                                    <span class="badge-custom badge-blue">{{ strtoupper($user->roles->first()->name) }}</span>
                                @else
                                    <span class="badge-custom badge-amber">No role</span>
                                @endif
                                @if ($user->status)
                                    <span class="badge-custom badge-green">Active</span>
                                @else
                                    <span class="badge-custom badge-red">Inactive</span>
                                @endif
                            </div>
                            <p class="text-[12px] text-slate-400 dark:text-slate-500">{{ $user->email ?? '' }}</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                Joined {{ $user->created_at ? $user->created_at->diffForHumans() : '' }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-1 flex-shrink-0">
                            @can('authentication.users.show')
                                <a href="{{ route('admin.users.show', $user->id) }}"
                                   class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                   title="View">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                            @endcan

                            @can('authentication.users.edit')
                                <a href="{{ route('admin.users.edit', $user->id) }}"
                                   class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                   title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                            @endcan

                            @can('authentication.users.delete')
                                <button onclick="deleteData({{ $user->id }})"
                                        class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors"
                                        title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                <form id="delete-form-{{ $user->id }}" method="POST"
                                      action="{{ route('admin.users.destroy', $user->id) }}" style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500">No users found.</p>
                </div>
            @endif
        </div>

        <!-- ── PAGINATION ── -->
        @if($users->hasPages())
            <div class="mt-5 flex justify-center">
                <div class="flex items-center gap-1">
                    @if($users->onFirstPage())
                        <span class="px-3 py-2 text-[13px] text-slate-300 dark:text-slate-600 cursor-not-allowed">← Prev
                        </span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}"
                           class="px-3 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:text-accent-400 transition-colors">←
                            Prev</a>
                    @endif

                    @foreach ($users->getUrlRange(1, $users->lastPage()) as $page => $url)
                        @if ($page == $users->currentPage())
                            <span
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-accent-400 text-white text-[13px] font-semibold">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}"
                               class="w-8 h-8 flex items-center justify-center rounded-lg text-[13px] text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}"
                           class="px-3 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:text-accent-400 transition-colors">Next
                        →</a>
                    @else
                        <span class="px-3 py-2 text-[13px] text-slate-300 dark:text-slate-600 cursor-not-allowed">Next
                        →</span>
                    @endif
                </div>
            </div>
        @endif

    </div>
@endsection
