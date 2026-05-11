@extends('layouts.app')

@section('title', 'Edit Monthly Budget')

@section('content')
    <div id="monthly-budget-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Monthly Budget</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Update details for {{ $monthlyBudget->salePlatform->name ?? 'N/A' }} - {{ $months[$monthlyBudget->month] ?? 'N/A' }} {{ $monthlyBudget->year }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.monthly-budgets.update', $monthlyBudget->id) }}" id="EditValidateForm">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

                <!-- LEFT COLUMN -->
                <div class="space-y-5">

                    <!-- ── Budget Information ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Budget Details
                        </div>
                        <p class="section-desc">Update the monthly budget details.</p>

                        <div class="grid grid-cols-1 gap-4">
                            <!-- Sale Platform -->
                            <div>
                                <label class="f-label">Sale Platform <span class="f-required">*</span></label>
                                <select name="sale_platform_id" class="tom-select f-input @error('sale_platform_id') border-red-400 @enderror" required>
                                    <option value="">Select a platform</option>
                                    @foreach($salePlatforms as $platform)
                                        <option value="{{ $platform['id'] }}" {{ old('sale_platform_id', $monthlyBudget->sale_platform_id) == $platform['id'] ? 'selected' : '' }}>
                                            {!! $platform['label'] !!}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sale_platform_id')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Year -->
                                <div>
                                    <label class="f-label">Year <span class="f-required">*</span></label>
                                    <select name="year" class="tom-select f-input @error('year') border-red-400 @enderror" required>
                                        <option value="">Select year</option>
                                        @foreach($years as $yearOption)
                                            <option value="{{ $yearOption }}" {{ old('year', $monthlyBudget->year) == $yearOption ? 'selected' : '' }}>
                                                {{ $yearOption }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('year')
                                        <p class="f-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Month -->
                                <div>
                                    <label class="f-label">Month <span class="f-required">*</span></label>
                                    <select name="month" class="tom-select f-input @error('month') border-red-400 @enderror" required>
                                        <option value="">Select month</option>
                                        @foreach($months as $monthNum => $monthName)
                                            <option value="{{ $monthNum }}" {{ old('month', $monthlyBudget->month) == $monthNum ? 'selected' : '' }}>
                                                {{ $monthName }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('month')
                                        <p class="f-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Budget -->
                            <div>
                                <label class="f-label">Budget <span class="f-required">*</span></label>
                                <input type="number" name="budget" step="0.01" min="1"
                                       class="f-input @error('budget') border-red-400 @enderror"
                                       value="{{ old('budget', $monthlyBudget->budget) }}" required />
                                @error('budget')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="space-y-5">
                    <!-- ── Additional Information ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Additional Information
                        </div>
                        <p class="section-desc">Optional details for the monthly budget.</p>

                        <div class="grid grid-cols-1 gap-4">
                            <!-- Currency -->
                            <div>
                                <label class="f-label">Currency <span class="f-required">*</span></label>
                                <input type="text" name="currency" maxlength="3"
                                       class="f-input @error('currency') border-red-400 @enderror"
                                       value="{{ old('currency', $monthlyBudget->currency) }}" required />
                                @error('currency')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="f-label">Notes</label>
                                <textarea name="notes"
                                          class="f-input @error('notes') border-red-400 @enderror"
                                          rows="5">{{ old('notes', $monthlyBudget->notes) }}</textarea>
                                @error('notes')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>
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
                            <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Fields marked <span class="text-red-400 mx-1">*</span> are required
                    </div>
                    <div class="flex gap-2.5">
                        <a href="{{ route('admin.monthly-budgets.index') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                                class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Update Monthly Budget
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
