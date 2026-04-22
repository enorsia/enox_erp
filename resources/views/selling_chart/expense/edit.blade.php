@extends('layouts.app')

@section('title', 'Edit Expense')

@section('content')
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">
        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Expense</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Update expense details for year
                    {{ $expense->year }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.selling_chart.expense.update', $expense->id) }}"
            id="expenseEditForm">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                <!-- ── Expense Details ── -->
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Expense Information
                    </div>
                    <p class="section-desc">Update the expense details for the selling chart.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Year -->
                        <div>
                            <label class="f-label">Year <span class="f-required">*</span></label>
                            <select name="year"
                                class="f-input custom-select @error('year') border-red-400 @enderror" required>
                                <option value="">Select Year</option>
                                @for ($i = 2020; $i <= 2030; $i++)
                                    <option value="{{ $i }}" {{ $expense->year == $i ? 'selected' : '' }}>{{ $i }}
                                    </option>
                                @endfor
                            </select>
                            @error('year')
                                <p class="f-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Conversion Rate -->
                        <div>
                            <label class="f-label">Conversion Rate <span class="f-required">*</span></label>
                            <input type="text" name="conversion_rate"
                                class="f-input @error('conversion_rate') border-red-400 @enderror"
                                value="{{ $expense->conversion_rate }}" required />
                            @error('conversion_rate')
                                <p class="f-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Commercial Expense -->
                        <div>
                            <label class="f-label">Commercial Expense <span class="f-required">*</span></label>
                            <input type="text" name="commercial_expense"
                                class="f-input @error('commercial_expense') border-red-400 @enderror"
                                value="{{ $expense->commercial_expense }}" required />
                            @error('commercial_expense')
                                <p class="f-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Enorsia Expense BD -->
                        <div>
                            <label class="f-label">Enorsia Expense BD <span class="f-required">*</span></label>
                            <input type="text" name="enorsia_expense_bd"
                                class="f-input @error('enorsia_expense_bd') border-red-400 @enderror"
                                value="{{ $expense->enorsia_expense_bd }}" required />
                            @error('enorsia_expense_bd')
                                <p class="f-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Enorsia Expense UK -->
                        <div>
                            <label class="f-label">Enorsia Expense UK <span class="f-required">*</span></label>
                            <input type="text" name="enorsia_expense_uk"
                                class="f-input @error('enorsia_expense_uk') border-red-400 @enderror"
                                value="{{ $expense->enorsia_expense_uk }}" required />
                            @error('enorsia_expense_uk')
                                <p class="f-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Shipping Cost -->
                        <div>
                            <label class="f-label">Shipping Cost</label>
                            <input type="text" name="shipping_cost"
                                class="f-input @error('shipping_cost') border-red-400 @enderror"
                                value="{{ $expense->shipping_cost }}" />
                            @error('shipping_cost')
                                <p class="f-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <div class="toggle-track {{ $expense->status == 1 ? 'on' : '' }}" id="statusToggle"
                                    onclick="toggleSwitch('statusToggle')">
                                    <div class="toggle-thumb"></div>
                                </div>
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 font-medium">Active status</span>
                                <input type="checkbox" name="status" class="hidden" id="statusCheckbox"
                                    {{ $expense->status == 1 ? 'checked' : '' }}>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STICKY FOOTER ── -->
            <div class="sticky-footer mt-5 -mx-5 rounded-none">
                <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Fields marked <span class="text-red-400 mx-1">*</span> are required
                    </div>
                    <div class="flex gap-2.5">
                        <a href="{{ route('admin.selling_chart.expense.index') }}"
                            class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                            class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Update Expense
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('js')
    <script>
        document.getElementById('statusToggle').addEventListener('click', function () {
            var cb = document.getElementById('statusCheckbox');
            cb.checked = this.classList.contains('on');
        });
    </script>
@endpush
