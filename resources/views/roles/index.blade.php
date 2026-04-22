@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <div class="p-5 lg:p-6">

        <!-- ── PAGE HEADER ── -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Roles & Permissions</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage roles and their assigned permissions
                </p>
            </div>
            @can('authentication.roles.create')
                <a href="{{ route('admin.roles.create') }}"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    Create Role
                </a>
            @endcan
        </div>

        <!-- ── FILTER TOOLBAR ── -->
        <form method="GET" action="{{ route('admin.roles.index') }}">
            <div class="flex flex-wrap items-center gap-2.5 mb-5">
                <div class="relative flex-1 min-w-[180px]">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                         fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" placeholder="Search roles..."
                           value="{{ request('search') }}"
                           class="w-full pl-9 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-accent-400 dark:focus:border-accent-400 transition-colors"/>
                </div>
                <button type="submit"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                    </svg>
                    Search
                </button>
                <a href="{{ route('admin.roles.index') }}"
                   class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset
                </a>
            </div>
        </form>

        <!-- ── ROLE CARDS ── -->
        <div class="flex flex-col gap-3">
            @if (!$roles->isEmpty())
                @foreach ($roles as $role)
                    @php
                        $countNested = $role->permissions->groupBy(function ($perm) {
                            $parts  = explode('.', $perm->name);
                            $module = $parts[0] ?? 'Other';
                            $model  = $parts[1] ?? $module;
                            return $module . '.' . $model;
                        });
                    @endphp
                    <div class="order-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <!-- Info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{
                                        ucfirst($role->name) }}</span>
                                    @if ($countNested->isNotEmpty())
                                        <span class="badge-custom badge-blue">{{ $role->permissions->count() }} permissions
                                            </span>
                                    @else
                                        <span class="badge-custom badge-amber">No permissions</span>
                                    @endif
                                </div>
                                @if ($countNested->isNotEmpty())
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($countNested as $key => $perms)
                                            <span class="text-[11px] bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 px-2 py-0.5 rounded-full">
                                                {{ $key }}: {{ $perms->count() }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-[12px] text-slate-400 dark:text-slate-500">No permissions assigned to
                                        this role.</p>
                                @endif
                            </div>
                            <!-- Actions -->
                            <div class="flex gap-1 flex-shrink-0">
                                @can('authentication.roles.show')
                                    <a href="{{ route('admin.roles.show', $role->id) }}?page={{ request('page') }}"
                                       class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                       title="View">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                @endcan
                                @can('authentication.roles.edit')
                                    <a href="{{ route('admin.roles.edit', $role->id) }}?page={{ request('page') }}"
                                       class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                       title="Edit">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                @endcan
                                @can('authentication.roles.delete')
                                    <button onclick="deleteData({{ $role->id }})"
                                            class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors"
                                            title="Delete">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    <form id="delete-form-{{ $role->id }}" method="POST"
                                          action="{{ route('admin.roles.destroy', $role->id) }}?page={{ request('page') }}"
                                          style="display:none;">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500">No roles found.</p>
                </div>
            @endif
        </div>

        <!-- ── PAGINATION ── -->
        @include('master.pagination', ['paginator' => $roles])

    </div>
@endsection

