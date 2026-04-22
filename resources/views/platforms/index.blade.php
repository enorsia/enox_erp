@extends('layouts.app')

@section('title', 'Platforms')

@section('content')
    <div class="p-5 lg:p-6">

        <!-- ── PAGE HEADER ── -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Platforms</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage sales platforms and their settings
                </p>
            </div>
            {{-- Uncomment when create route is available
            @can('settings.platforms.create')
                <a href="{{ route('admin.platforms.create') }}"
                   class="flex items-center gap-2 px-4 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    Create Platform
                </a>
            @endcan
            --}}
        </div>

        <!-- ── FILTER TOOLBAR ── -->
        <form method="GET" action="{{ route('admin.platforms.index') }}">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-5">

                <!-- Search input -->
                <div class="relative flex-1 min-w-0">
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                         fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="text" name="q" placeholder="Search platforms…"
                           value="{{ request('q') }}"
                           class="w-full pl-8 pr-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-accent-400 dark:focus:border-accent-400 transition-colors"/>
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
                    <a href="{{ route('admin.platforms.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset</span>
                    </a>
                </div>

            </div>
        </form>

        <!-- ── PLATFORM CARDS ── -->
        <div class="flex flex-col gap-3">
            @if (!$platforms->isEmpty())
                @foreach ($platforms as $data)
                    <div class="order-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">

                            <!-- Info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                    <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $data->name }}</span>
                                    <span class="badge-custom badge-blue font-mono">{{ $data->code }}</span>
                                    @if ($data->status)
                                        <span class="badge-custom badge-green">Active</span>
                                    @else
                                        <span class="badge-custom badge-red">Inactive</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-4 text-[12px] text-slate-500 dark:text-slate-400">
                                    <span>Min Profit: <strong class="text-slate-700 dark:text-slate-200">@price($data->min_profit)</strong></span>
                                    <span>Shipping: <strong class="text-slate-700 dark:text-slate-200">@price($data->shipping_charge)</strong></span>
                                    <span>Commission: <strong class="text-slate-700 dark:text-slate-200">@pricews($data->commission * 100)%</strong></span>
                                </div>
                                @if ($data->note)
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">{{ $data->note }}</p>
                                @endif
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-1 flex-shrink-0">
                                @can('settings.platforms.edit')
                                    <a href="{{ route('admin.platforms.edit', $data->id) }}"
                                       class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                       title="Edit">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                @endcan
                                @can('settings.platforms.delete')
                                    <button onclick="deleteData({{ $data->id }})"
                                            class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors"
                                            title="Delete">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    <form id="delete-form-{{ $data->id }}" method="POST"
                                          action="{{ route('admin.platforms.destroy', $data->id) }}" style="display:none;">
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
                    <p class="text-sm text-slate-400 dark:text-slate-500">No platforms found.</p>
                </div>
            @endif
        </div>

        <!-- ── PAGINATION ── -->
        @include('layouts.pagination', ['paginator' => $platforms])

    </div>
@endsection

