@extends('layouts.app')

@section('title', 'Fabrication')

@section('content')
    <div class="p-5 lg:p-6">
        <!-- ── PAGE HEADER ── -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Fabrication</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage fabrication lookup names</p>
            </div>
            @can('general.fabrication.create')
                <a href="{{ route('admin.selling_chart.fabrication.create') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    Create Fabrication
                </a>
            @endcan
        </div>

        <!-- ── FILTER TOOLBAR ── -->
        <form method="GET" action="{{ route('admin.selling_chart.fabrication.index') }}">
            <div class="flex flex-wrap items-center gap-2.5 mb-5">
                <div class="flex flex-1 gap-2.5 min-w-0">
                    <!-- Name search -->
                    <div class="relative w-1/2">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                             fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" name="name" placeholder="Search by name..."
                               value="{{ request('name') }}"
                               class="w-full pl-9 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-accent-400 dark:focus:border-accent-400 transition-colors"/>
                    </div>

                    <!-- Status filter -->
                    <div class="w-1/2 max-w-[200px]">
                        <select name="status" class="tom-select w-full" data-placeholder="Status">
                            <option value="">Status</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
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
                <a href="{{ route('admin.selling_chart.fabrication.index') }}"
                   class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset
                </a>
            </div>
        </form>

        <!-- ── FABRICATION LIST (CARD/GRID STYLE) ── -->
        <div class="flex flex-col gap-3">
            @if (!$lookup_names->isEmpty())
                @foreach ($lookup_names as $lookup)
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 grid grid-cols-[1fr_auto] items-center gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate">{{ $lookup->name }}</h3>
                                @if ($lookup->status == 1)
                                    <span class="badge-custom badge-green">Active</span>
                                @else
                                    <span class="badge-custom badge-red">Inactive</span>
                                @endif
                            </div>
                            @if(!empty($lookup->created_at))
                                <p class="text-[12px] text-slate-400 dark:text-slate-500">Created {{ \Illuminate\Support\Carbon::parse($lookup->created_at)->diffForHumans() }}</p>
                            @endif
                        </div>

                        <div class="flex gap-2 items-center">
                            @can('general.fabrication.edit')
                                <a href="{{ route('admin.selling_chart.fabrication.edit', $lookup->id) }}" title="Edit"
                                   class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                            @endcan

                            @can('general.fabrication.delete')
                                <button onclick="deleteData({{ $lookup->id }})" title="Delete"
                                        class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                <form id="delete-form-{{ $lookup->id }}" method="POST" action="{{ route('admin.selling_chart.fabrication.destroy', $lookup->id) }}" style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500">No fabrication records found.</p>
                </div>
            @endif
        </div>

        <!-- ── PAGINATION ── -->
        @if($lookup_names && $lookup_names->hasPages())
            @include('master.pagination', ['paginator' => $lookup_names])
        @endif
    </div>
@endsection

