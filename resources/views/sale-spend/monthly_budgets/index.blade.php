@extends('layouts.app')

@section('title', 'Monthly Budgets')

@section('content')
<div id="monthly-budget-page-content"></div>

<div x-data="{
    drawerOpen: false,
    exportOpen: false,
    exportCols: @js(\App\Exports\MonthlyBudgetExport::allColumns()),
    selectedCols: @js(\App\Exports\MonthlyBudgetExport::allColumns()),
    toggleAll(checked) { this.selectedCols = checked ? [...this.exportCols] : []; }
}" @keydown.escape.window="drawerOpen = false; exportOpen = false">

    {{-- Backdrop --}}
    <div x-show="drawerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="drawerOpen = false" class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]" style="display:none;"></div>

    {{-- Drawer Panel --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 bottom-0 w-full sm:w-[340px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
         style="display:none;">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
            <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100">
                <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                Filters
            </div>
            <button @click="drawerOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="get" action="{{ route('admin.monthly-budgets.index') }}" class="flex-1 flex flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Search</p>
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                        <input type="text" name="search" placeholder="Year, month, budget or platform…" value="{{ request('search') }}"
                               class="w-full pl-9 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-accent-400 transition-colors"/>
                    </div>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Platform</p>
                    <select name="sale_platform_id" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" data-placeholder="All Platforms">
                        <option value="">All Platforms</option>
                        @foreach($salePlatforms as $platform)
                            <option value="{{ $platform['id'] }}" {{ request('sale_platform_id') == $platform['id'] ? 'selected' : '' }}>{!! $platform['label'] !!}</option>
                        @endforeach
                    </select>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Year</p>
                    <select name="year" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" data-placeholder="All Years">
                        <option value="">All Years</option>
                        @foreach($years as $yearOption)
                            <option value="{{ $yearOption }}" {{ request('year') == $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                        @endforeach
                    </select>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Month</p>
                    <select name="month" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" data-placeholder="All Months">
                        <option value="">All Months</option>
                        @foreach($months as $monthNum => $monthName)
                            <option value="{{ $monthNum }}" {{ request('month') == $monthNum ? 'selected' : '' }}>{{ $monthName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex-shrink-0">
                <a href="{{ route('admin.monthly-budgets.index') }}" class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">Reset</a>
                <button type="submit" class="flex-[2] py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">Apply Filters</button>
            </div>
        </form>
    </div>

    {{-- Export Modal --}}
    <div x-show="exportOpen" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="exportOpen = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 dark:border-slate-700"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Export Monthly Budgets</h3>
                </div>
                <button @click="exportOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[12px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Select Columns</p>
                    <div class="flex gap-2">
                        <button type="button" @click="toggleAll(true)" class="text-[11px] text-accent-500 hover:text-accent-700 font-medium">All</button>
                    </div>
                </div>
                @php $exportLabels = \App\Exports\MonthlyBudgetExport::columnLabels(); $exportCols = \App\Exports\MonthlyBudgetExport::allColumns(); @endphp
                <div class="grid grid-cols-2 gap-2">
                    @foreach($exportCols as $col)
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors">
                            <input type="checkbox" :checked="selectedCols.includes('{{ $col }}')"
                                   @change="selectedCols.includes('{{ $col }}') ? selectedCols.splice(selectedCols.indexOf('{{ $col }}'), 1) : selectedCols.push('{{ $col }}')"
                                   class="w-3.5 h-3.5 rounded text-accent-400 border-slate-300 focus:ring-accent-400">
                            <span class="text-[12px] text-slate-600 dark:text-slate-300">{{ $exportLabels[$col] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex gap-2.5 px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                <button @click="exportOpen = false" class="flex-1 py-2.5 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 transition-colors font-medium text-center">Cancel</button>
                <a :href="'{{ route('admin.monthly-budgets.export') }}?' + new URLSearchParams(Object.assign({}, Object.fromEntries(new URLSearchParams('{{ http_build_query(request()->except('page')) }}')), {columns: selectedCols.join(',')})).toString()"
                   class="flex-[2] py-2.5 text-[13px] rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition-colors text-center flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Excel
                </a>
            </div>
        </div>
    </div>

    <div class="p-5 lg:p-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Monthly Budgets</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage all monthly budgets for sale platforms</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="exportOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
                @php $activeFilters = collect([request('search'), request('sale_platform_id'), request('year'), request('month')])->filter()->count(); @endphp
                <button type="button" @click="drawerOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilters > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                    Filters
                    @if($activeFilters > 0)
                        <span class="bg-accent-400 text-white text-[9px] font-semibold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilters }}</span>
                    @endif
                </button>
                @can('general.monthly_budget.create')
                    <a href="{{ route('admin.monthly-budgets.create') }}"
                       class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Create Monthly Budget
                    </a>
                @endcan
            </div>
        </div>

        {{-- Active filter tags --}}
        @if(request('search') || request('sale_platform_id') || request('year') || request('month'))
            <div class="flex flex-wrap gap-2 mb-4">
                @if(request('search'))
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Search:</span> {{ request('search') }}
                        <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('sale_platform_id'))
                    @php $platformLabel = collect($salePlatforms)->firstWhere('id', request('sale_platform_id'))['label'] ?? request('sale_platform_id'); @endphp
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Platform:</span> {!! strip_tags($platformLabel) !!}
                        <a href="{{ request()->fullUrlWithQuery(['sale_platform_id' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('year'))
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Year:</span> {{ request('year') }}
                        <a href="{{ request()->fullUrlWithQuery(['year' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('month'))
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Month:</span> {{ $months[request('month')] ?? request('month') }}
                        <a href="{{ request()->fullUrlWithQuery(['month' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                <a href="{{ route('admin.monthly-budgets.index') }}" class="flex items-center gap-1 text-[11px] text-slate-400 hover:text-red-500 px-2 py-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg> Clear all
                </a>
            </div>
        @endif

        {{-- ── NESTED YEAR / MONTH / PLATFORM VIEW ── --}}
        @if ($monthlyBudgets->isEmpty())
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75"/></svg>
                <p class="text-sm text-slate-400 dark:text-slate-500">No monthly budgets found for the selected filters.</p>
            </div>
        @else
            @foreach ($viewGroups as $yearGroup)
                {{-- ── Year Section ── --}}
                <div class="mb-6">
                    {{-- Year Header --}}
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center gap-2 bg-gradient-to-r from-accent-500 to-accent-400 text-white px-4 py-1.5 rounded-full shadow-sm">
                            <svg class="w-3.5 h-3.5 opacity-80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/></svg>
                            <span class="text-sm font-bold tracking-wide">{{ $yearGroup['year'] }}</span>
                        </div>
                        <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
                        <span class="text-[12px] text-slate-500 dark:text-slate-400 font-medium shrink-0">
                            Year Total: <strong class="text-slate-700 dark:text-slate-200">{{ number_format($yearGroup['yearTotal'], 2) }}</strong>
                        </span>
                    </div>

                    {{-- Month Groups --}}
                    @foreach ($yearGroup['monthGroups'] as $monthGroup)
                        <div class="ml-4 mb-4">
                            {{-- Month Sub-Header --}}
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-accent-400 shrink-0"></div>
                                <span class="text-[13px] font-semibold text-slate-700 dark:text-slate-200">{{ $monthGroup['monthName'] }}</span>
                                <div class="flex-1 h-px bg-slate-100 dark:bg-slate-700/70"></div>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                    Month Total: <strong class="text-slate-600 dark:text-slate-300">{{ number_format($monthGroup['monthTotal'], 2) }}</strong>
                                </span>
                            </div>

                            {{-- Platform Entries (recursive nested tree) --}}
                            <div class="flex flex-col gap-2 ml-4">
                                @foreach ($monthGroup['rootEntries'] as $entry)
                                    @include('sale-spend.monthly_budgets._budget_entry', [
                                        'entry' => $entry,
                                        'depth' => 0,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif

        @include('layouts.pagination', ['paginator' => $monthlyBudgets])
    </div>
</div>
@endsection

