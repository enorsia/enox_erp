@extends('layouts.app')

@section('title', 'Selling Chart Expense')

@section('content')
    <div class="p-5 lg:p-6">
        <!-- ── PAGE HEADER ── -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Selling Chart Expense</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage expense records for selling chart
                </p>
            </div>
            @can('general.expense.create')
                <a href="{{ route('admin.selling_chart.expense.create') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    Create Expense
                </a>
            @endcan
        </div>

        <!-- ── FILTER TOOLBAR ── -->
        <form method="GET" action="{{ route('admin.selling_chart.expense.index') }}">
            <div class="flex flex-wrap items-center gap-2.5 mb-5">
                <div class="flex flex-1 gap-2.5 min-w-0">
                    <!-- Year filter -->
                    <div class="w-1/2 max-w-[200px]">
                        <select name="year" class="tom-select w-full" data-placeholder="Select Year">
                            <option value="">Select Year</option>
                            @for ($i = 2020; $i <= 2030; $i++)
                                <option value="{{ $i }}" {{ request('year') == $i ? 'selected' : '' }}>
                                    {{ $i }}
                                </option>
                            @endfor
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
                <a href="{{ route('admin.selling_chart.expense.index') }}"
                   class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset
                </a>
            </div>
        </form>

        <!-- ── EXPENSE CARDS ── -->
        <div class="flex flex-col gap-3">
            @if (!$expenses->isEmpty())
                @foreach ($expenses as $data)
                    <div class="order-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 grid grid-cols-[1fr_auto] gap-3 items-center">

                        <!-- Info -->
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">Year {{ $data->year }}</span>
                                @if ($data->status == 1)
                                    <span class="badge-custom badge-green">Active</span>
                                @else
                                    <span class="badge-custom badge-red">Inactive</span>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-x-4 gap-y-0.5 mt-1">
                                <p class="text-[12px] text-slate-500 dark:text-slate-400">
                                    <span class="text-slate-400 dark:text-slate-500">Conversion Rate:</span>
                                    <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">£ {{ $data->conversion_rate }}</span>
                                </p>
                                <p class="text-[12px] text-slate-500 dark:text-slate-400">
                                    <span class="text-slate-400 dark:text-slate-500">Commercial Expense:</span>
                                    <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">£ {{ $data->commercial_expense }}</span>
                                </p>
                                <p class="text-[12px] text-slate-500 dark:text-slate-400">
                                    <span class="text-slate-400 dark:text-slate-500">Enorsia Expense BD:</span>
                                    <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">£ {{ $data->enorsia_expense_bd }}</span>
                                </p>
                                <p class="text-[12px] text-slate-500 dark:text-slate-400">
                                    <span class="text-slate-400 dark:text-slate-500">Enorsia Expense UK:</span>
                                    <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">£ {{ $data->enorsia_expense_uk }}</span>
                                </p>
                                <p class="text-[12px] text-slate-500 dark:text-slate-400">
                                    <span class="text-slate-400 dark:text-slate-500">Shipping Cost:</span>
                                    <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">£ {{ $data->shipping_cost }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-1 flex-shrink-0">
                            @can('general.expense.edit')
                                <a href="{{ route('admin.selling_chart.expense.edit', $data->id) }}"
                                   class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors"
                                   title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                            @endcan
                            @can('general.expense.delete')
                                <button onclick="deleteData({{ $data->id }})"
                                        class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors"
                                        title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                <form id="delete-form-{{ $data->id }}" method="POST"
                                      action="{{ route('admin.selling_chart.expense.destroy', $data->id) }}" style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endcan
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500">No expenses found.</p>
                </div>
            @endif
        </div>

        <!-- ── PAGINATION ── -->
        @include('master.pagination', ['paginator' => $expenses])
    </div>

@endsection

@push('js')
    @include('selling_chart.expense.script')
@endpush
