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

        <!-- ── FABRICATION TABLE ── -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-[60px]">#SL</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                            <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-[120px]">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @if (!$lookup_names->isEmpty())
                            @foreach ($lookup_names as $lookup)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $start + $loop->index }}</td>
                                    <td class="px-4 py-3 text-slate-800 dark:text-slate-200 font-medium">{{ $lookup->name }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($lookup->status == 1)
                                            <span class="badge-custom badge-green">Active</span>
                                        @else
                                            <span class="badge-custom badge-red">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center">
                                    <p class="text-sm text-slate-400 dark:text-slate-500">No fabrication records found.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── PAGINATION ── -->
        @if(count($lookup_names) > 0 && $lookup_names && $lookup_names->hasPages())
            <div class="mt-5 flex justify-center">
                <div class="flex items-center gap-1">
                    @if($lookup_names->onFirstPage())
                        <span class="px-3 py-2 text-[13px] text-slate-300 dark:text-slate-600 cursor-not-allowed">← Prev</span>
                    @else
                        <a href="{{ $lookup_names->previousPageUrl() }}" class="px-3 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:text-accent-400 transition-colors">← Prev</a>
                    @endif

                    @foreach ($lookup_names->getUrlRange(1, $lookup_names->lastPage()) as $page => $url)
                        @if ($page == $lookup_names->currentPage())
                            <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-accent-400 text-white text-[13px] font-semibold">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="w-8 h-8 flex items-center justify-center rounded-lg text-[13px] text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if($lookup_names->hasMorePages())
                        <a href="{{ $lookup_names->nextPageUrl() }}" class="px-3 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:text-accent-400 transition-colors">Next →</a>
                    @else
                        <span class="px-3 py-2 text-[13px] text-slate-300 dark:text-slate-600 cursor-not-allowed">Next →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection

