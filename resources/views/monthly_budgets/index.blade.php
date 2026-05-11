@extends('layouts.app')

@section('title', 'Monthly Budgets')

@section('content')
    <div id="monthly-budget-page-content"></div>
    <div class="p-5 lg:p-6">
        <!-- ── PAGE HEADER ── -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Monthly Budgets</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage all monthly budgets for sale platforms</p>
            </div>
            @can('general.monthly_budget.create')
                <a href="{{ route('admin.monthly-budgets.create') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    Create Monthly Budget
                </a>
            @endcan
        </div>

        <!-- ── FILTER TOOLBAR ── -->
        <form method="get" action="{{ route('admin.monthly-budgets.index') }}">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-5">

                <div class="flex-1 min-w-0 flex items-center gap-2">
                    <!-- Search input -->
                    <div class="relative flex-1">
                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                             fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" name="search" placeholder="Search by year, month, budget or platform name…"
                               value="{{ request('search') }}"
                               class="w-full pl-8 pr-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-accent-400 dark:focus:border-accent-400 transition-colors"/>
                    </div>

                    <!-- Sale Platform filter -->
                    <div class="flex-1 sm:flex-none sm:w-48">
                        <select name="sale_platform_id" class="tom-select w-full h-9" data-placeholder="Select Sale Platform">
                            <option value="">All Platforms</option>
                            @foreach($salePlatforms as $platform)
                                <option value="{{ $platform['id'] }}" {{ request('sale_platform_id') == $platform['id'] ? 'selected' : '' }}>
                                    {!! $platform['label'] !!}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Year filter -->
                    <div class="flex-1 sm:flex-none sm:w-32">
                        <select name="year" class="tom-select w-full h-9" data-placeholder="Select Year">
                            <option value="">All Years</option>
                            @foreach($years as $yearOption)
                                <option value="{{ $yearOption }}" {{ request('year') == $yearOption ? 'selected' : '' }}>
                                    {{ $yearOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Month filter -->
                    <div class="flex-1 sm:flex-none sm:w-36">
                        <select name="month" class="tom-select w-full h-9" data-placeholder="Select Month">
                            <option value="">All Months</option>
                            @foreach($months as $monthNum => $monthName)
                                <option value="{{ $monthNum }}" {{ request('month') == $monthNum ? 'selected' : '' }}>
                                    {{ $monthName }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Buttons group -->
                <div class="flex items-center gap-2 sm:ml-3">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors whitespace-nowrap shrink-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        <span>Search</span>
                    </button>

                    <a href="{{ route('admin.monthly-budgets.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap shrink-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Reset</span>
                    </a>
                </div>

            </div>
        </form>

        <!-- ── MONTHLY BUDGET CARDS LIST ── -->
        <div class="flex flex-col gap-3">

            {{-- Display flat, paginated list when filters are applied --}}
            @if (!$monthlyBudgets->isEmpty())
                @foreach ($monthlyBudgets as $key => $monthlyBudget)
                    <div class="order-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 grid grid-cols-[1fr_auto] gap-3 items-center">
                        <!-- Info -->
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ $monthlyBudget->salePlatform->name ?? 'N/A' }} - {{ $months[$monthlyBudget->month] ?? 'N/A' }} {{ $monthlyBudget->year }}
                                </span>
                            </div>

                            <p class="text-[12px] text-slate-400 dark:text-slate-500">
                                Budget: {{ number_format($monthlyBudget->budget, 2) }} {{ $monthlyBudget->currency }}
                            </p>
                            @if ($monthlyBudget->notes)
                                <p class="text-[12px] text-slate-400 dark:text-slate-500 mt-0.5">
                                    Notes: {{ strlen($monthlyBudget->notes) > 100 ? substr($monthlyBudget->notes, 0, 100) . '...' : $monthlyBudget->notes }}
                                </p>
                            @endif
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                Created {{ $monthlyBudget->created_at ? $monthlyBudget->created_at?->diffForHumans() : '' }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-1 flex-shrink-0">
                            @can('general.monthly_budget.show')
                                <a href="{{ route('admin.monthly-budgets.show', $monthlyBudget->id) }}"
                                    class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                    title="View">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                            @endcan

                            @can('general.monthly_budget.edit')
                                <a href="{{ route('admin.monthly-budgets.edit', $monthlyBudget->id) }}"
                                    class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                    title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                            @endcan

                            @can('general.monthly_budget.delete')
                                <button onclick="deleteData({{ $monthlyBudget->id }})"
                                        class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors"
                                        title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                <form id="delete-form-{{ $monthlyBudget->id }}" method="POST"
                                        action="{{ route('admin.monthly-budgets.destroy', $monthlyBudget->id) }}" style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500">No monthly budgets found for the selected filters.</p>
                </div>
            @endif
        </div>

        <!-- ── PAGINATION ── -->
        @include('layouts.pagination', ['paginator' => $monthlyBudgets])


    </div>
@endsection

@push('scripts')
    <script>
        function deleteData(id) {
            if (confirm('Are you sure you want to delete this monthly budget?')) {
                document.getElementById('delete-form-' + id).submit();
            }
        }
    </script>
@endpush
