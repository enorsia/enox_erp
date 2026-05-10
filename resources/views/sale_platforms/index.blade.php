@extends('layouts.app')

@section('title', 'Sale Platforms')

@section('content')
    <div id="sale-platform-page-content"></div>
    <div class="p-5 lg:p-6">

        {{-- ── PAGE HEADER ── --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Sale Platforms</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage all sale platforms and their hierarchy</p>
            </div>
            @can('general.sale_platform.create')
                <a href="{{ route('admin.sale-platforms.create') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    Create Sale Platform
                </a>
            @endcan
        </div>

        {{-- ── STATS ROW ── --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Total</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Active</p>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['active'] }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Inactive</p>
                <p class="text-2xl font-bold text-red-500 dark:text-red-400">{{ $stats['inactive'] }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Types</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['types']->count() }}</p>
            </div>
        </div>

        {{-- ── FILTER TOOLBAR ── --}}
        <form method="get" action="{{ route('admin.sale-platforms.index') }}">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-5">
                <div class="flex-1 min-w-0 flex items-center gap-2">
                    <div class="relative flex-1">
                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                             fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" name="search" placeholder="Search by name or slug…"
                               value="{{ request('search') }}"
                               class="w-full pl-8 pr-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-accent-400 dark:focus:border-accent-400 transition-colors"/>
                    </div>
                    <div class="flex-1 sm:flex-none sm:w-40">
                        <select name="type" class="tom-select w-full h-9" data-placeholder="Select Type">
                            <option value="">All Types</option>
                            <option value="channel"     {{ request('type') == 'channel'     ? 'selected' : '' }}>Channel</option>
                            <option value="sub_channel" {{ request('type') == 'sub_channel' ? 'selected' : '' }}>Sub Channel</option>
                            <option value="marketplace" {{ request('type') == 'marketplace' ? 'selected' : '' }}>Marketplace</option>
                            <option value="region"      {{ request('type') == 'region'      ? 'selected' : '' }}>Region</option>
                        </select>
                    </div>
                    <div class="flex-1 sm:flex-none sm:w-36">
                        <select name="is_active" class="tom-select w-full h-9" data-placeholder="Select Status">
                            <option value="">All Status</option>
                            <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:ml-3">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors whitespace-nowrap shrink-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        <span>Search</span>
                    </button>
                    <a href="{{ route('admin.sale-platforms.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap shrink-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset</span>
                    </a>
                </div>
            </div>
        </form>

        {{-- ── FILTER NOTICE ── --}}
        @if ($is_filtered)
            <div class="flex items-center gap-2 mb-4 px-3 py-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 rounded-lg text-[12px] text-amber-700 dark:text-amber-400">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                Filtered results — hierarchy view disabled. <a href="{{ route('admin.sale-platforms.index') }}" class="underline underline-offset-2 ml-1">Clear filters</a> to see full tree.
            </div>
        @endif

        {{-- ── PLATFORM TREE / LIST ── --}}
        <div class="flex flex-col gap-0" id="platform-tree-container">
            @forelse ($flat_list as $platform)
                @php
                    $depth       = $platform->depth ?? 0;
                    $hasChildren = $platform->has_children ?? false;
                    $isActive    = $platform->is_active;
                    $typeColors  = [
                        'channel'     => 'badge-blue',
                        'sub_channel' => 'badge-purple',
                        'marketplace' => 'badge-orange',
                        'region'      => 'badge-teal',
                    ];
                    $typeColor = $typeColors[$platform->type] ?? 'badge-blue';
                    $platformId = 'platform_' . $platform->id;
                @endphp

                <div class="relative flex items-stretch platform-item
                        @if($depth === 0) mt-3 @else mt-0 @endif
                        @if($depth > 0) platform-child hidden @else platform-parent @endif"
                     data-platform-id="{{ $platformId }}"
                     data-depth="{{ $depth }}"
                     data-parent-id="@if($depth > 0){{ $platform->parent_id }}@endif"
                     style="padding-left: {{ $depth * 24 }}px;">

                    {{-- Tree connector lines for children --}}
                    @if ($depth > 0)
                        {{-- Vertical pipe on left --}}
                        <div class="absolute left-0 top-0 bottom-0 flex"
                             style="left: {{ ($depth - 1) * 24 + 10 }}px; width: 14px;">
                            {{-- Vertical line --}}
                            <div class="w-px bg-slate-200 dark:bg-slate-700
                                    @if ($platform->is_last_child) h-5 @else h-full @endif
                                    self-start"></div>
                            {{-- Horizontal connector --}}
                            <div class="w-3.5 h-px bg-slate-200 dark:bg-slate-700 mt-5 shrink-0"></div>
                        </div>
                    @endif

                    {{-- Card --}}
                    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800
                             border border-slate-200 dark:border-slate-700
                             @if($depth === 0) rounded-xl @else rounded-lg @endif
                             @if($depth === 0) shadow-sm @endif
                             p-3.5 grid grid-cols-[auto_1fr_auto] gap-3 items-center
                             mb-px platform-card
                             @if($hasChildren) cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors @endif"
                     @if($hasChildren) onclick="togglePlatformCollapse(event, '{{ $platformId }}')" @endif>

                        {{-- Depth indicator dot / expand icon --}}
                        <div class="flex flex-col items-center gap-0.5">
                            @if ($hasChildren)
                                <div class="collapse-toggle-icon w-7 h-7 rounded-lg bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center transition-transform duration-200"
                                     style="transform: rotate(-90deg);">
                                    <svg class="w-3.5 h-3.5 text-accent-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            @elseif($depth === 0)
                                <div class="w-7 h-7 rounded-lg bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5 text-accent-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
                                    </svg>
                                </div>
                            @elseif ($depth === 1)
                                <div class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                    </svg>
                                </div>
                            @else
                                <div class="w-5 h-5 rounded flex items-center justify-center">
                                    <div class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                                </div>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-100
                                         @if($depth === 0) text-[14px] @elseif($depth === 1) text-[13px] @else text-[12px] @endif">
                                {{ $platform->name }}
                            </span>
                                <span class="badge-custom {{ $typeColor }} text-[10px]">
                                {{ ucfirst(str_replace('_', ' ', $platform->type)) }}
                            </span>
                                @if ($isActive)
                                    <span class="badge-custom badge-green text-[10px]">Active</span>
                                @else
                                    <span class="badge-custom badge-red text-[10px]">Inactive</span>
                                @endif
                                @if ($hasChildren)
                                    <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-700/60 px-1.5 py-0.5 rounded">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                                    </svg>
                                    {{ $platform->children_count }} {{ Str::plural('child', $platform->children_count) }}
                                </span>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                <span class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">{{ $platform->slug }}</span>
                                <span class="text-[11px] text-slate-300 dark:text-slate-600">·</span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">Sort: {{ $platform->sort_order }}</span>
                                @if (!empty($platform->ancestor_names))
                                    <span class="text-[11px] text-slate-300 dark:text-slate-600">·</span>
                                    <span class="text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-[200px]">
                                    {{ implode(' › ', $platform->ancestor_names) }}
                                </span>
                                @endif
                                <span class="text-[11px] text-slate-300 dark:text-slate-600">·</span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ $platform->created_at?->diffForHumans() }}</span>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-1 flex-shrink-0">
                            @can('general.sale_platform.show')
                                <a href="{{ route('admin.sale-platforms.show', $platform->id) }}"
                                   class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                   title="View">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                            @endcan

                            @can('general.sale_platform.edit')
                                <a href="{{ route('admin.sale-platforms.edit', $platform->id) }}"
                                   class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                   title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                            @endcan

                            @can('general.sale_platform.delete')
                                <button onclick="deleteData({{ $platform->id }})"
                                        class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors"
                                        title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                <form id="delete-form-{{ $platform->id }}" method="POST"
                                      action="{{ route('admin.sale-platforms.destroy', $platform->id) }}" style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-10 text-center">
                    <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                    </svg>
                    <p class="text-sm text-slate-400 dark:text-slate-500">No sale platforms found.</p>
                    @can('general.sale_platform.create')
                        <a href="{{ route('admin.sale-platforms.create') }}"
                           class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 text-sm rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                            Create your first platform
                        </a>
                    @endcan
                </div>
            @endforelse
        </div>

        {{-- Only show pagination in filtered mode --}}
        @if ($is_filtered)
            @include('layouts.pagination', ['paginator' => $platforms])
        @endif

    </div>
@endsection

