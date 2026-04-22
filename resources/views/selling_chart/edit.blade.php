@extends('layouts.app')

@section('title', 'Edit Selling Chart')

@section('content')
    {{-- Page-level config for selling-chart.js --}}
    <div id="selling-chart-form-content"
         data-size-range-url="{{ url('/admin/selling-chart/get-size-range') }}"
         data-dep-cats-url="{{ url('admin/selling-chart/get-dep-wise-cats') }}"
         data-color-search-url="{{ url('/admin/selling-chart/get-color-by-search') }}"></div>

    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        {{-- PAGE HEADER --}}
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Selling Chart</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Editing design: <span class="font-medium text-slate-600 dark:text-slate-300">{{ $chartInfo->design_no }}</span></p>
            </div>
            <a href="{{ route('admin.selling_chart.index') }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to List
            </a>
        </div>

        <form action="{{ route('admin.selling_chart.update', ['id' => $chartInfo->id]) }}" method="POST"
              id="selling_chart" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="space-y-5">

                {{-- ── Classification ── --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M4 6h16M4 12h8m-8 6h16"/>
                        </svg>
                        Classification
                    </div>
                    <p class="section-desc">Department, category and season details.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        {{-- Department (read-only) --}}
                        <div>
                            <label class="f-label">Department</label>
                            <input type="hidden" name="department_id" value="{{ $chartInfo->department_id }}">
                            <select id="department_select" disabled
                                class="f-input custom-select opacity-60 cursor-not-allowed">
                                <option value="">Select Department</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}"
                                        {{ $chartInfo->department_id == $department->id ? 'selected' : '' }}>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Product Category (read-only) --}}
                        <div>
                            <label class="f-label">Product Category</label>
                            <input type="hidden" name="category_id" value="{{ $chartInfo->category_id }}">
                            <select id="product_category" disabled
                                class="f-input custom-select opacity-60 cursor-not-allowed">
                                <option value="">Select Category</option>
                                @foreach ($selling_chart_cats as $selling_chart_cat)
                                    <option value="{{ $selling_chart_cat->id }}"
                                        {{ $chartInfo->category_id == $selling_chart_cat->id ? 'selected' : '' }}>
                                        {{ $selling_chart_cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Mini Category --}}
                        <div>
                            <label class="f-label">Mini Category <span class="f-required">*</span></label>
                            <select id="product_mini_category" name="mini_category" required
                                class="f-input custom-select @error('mini_category') border-red-400 @enderror">
                                <option value="">Select Mini Category</option>
                                @foreach ($selling_chart_types as $selling_chart_type)
                                    <option value="{{ $selling_chart_type->id }}"
                                        {{ $chartInfo->mini_category == $selling_chart_type->id ? 'selected' : '' }}>
                                        {{ $selling_chart_type->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('mini_category') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Season --}}
                        <div>
                            <label class="f-label">Season <span class="f-required">*</span></label>
                            {{-- Hidden expense data inputs used by selling-chart.js for price calculation --}}
                            @foreach ($seasons as $season)
                                @php
                                    $season_name_year       = intval(substr($season->name, -2));
                                    $last_digit_season_year = $season_name_year % 10;
                                    $expense = \App\Models\SellingChartExpense::where('status', 1)
                                        ->whereRaw('YEAR(year) % 10 = ?', [$last_digit_season_year])
                                        ->latest()
                                        ->first();
                                @endphp
                                <input class="season-exp{{ $season->id }}" type="hidden" value="{{ $season->id }}"
                                    data-conversion-rate="{{ $expense->conversion_rate ?? 0 }}"
                                    data-commercial-expense="{{ $expense->commercial_expense ?? 0 }}"
                                    data-enorsia-bd-expense="{{ $expense->enorsia_expense_bd ?? 0 }}"
                                    data-enorsia-uk-expense="{{ $expense->enorsia_expense_uk ?? 0 }}"
                                    data-shipping-cost="{{ $expense->shipping_cost ?? 0 }}">
                            @endforeach
                            <select id="season_select" name="season_id" required
                                class="f-input custom-select @error('season_id') border-red-400 @enderror">
                                <option value="">Select Season</option>
                                @foreach ($seasons as $season)
                                    <option value="{{ $season->id }}"
                                        {{ $chartInfo->season_id == $season->id ? 'selected' : '' }}>
                                        {{ $season->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('season_id') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Season Phase --}}
                        <div>
                            <label class="f-label">Season Phase <span class="f-required">*</span></label>
                            <select id="Season_Phase" name="season_phase_id" required
                                class="f-input custom-select @error('season_phase_id') border-red-400 @enderror">
                                <option value="">Select Season Phase</option>
                                @foreach ($seasons_phases as $seasons_phase)
                                    <option value="{{ $seasons_phase->id }}"
                                        {{ $chartInfo->phase_id == $seasons_phase->id ? 'selected' : '' }}>
                                        {{ $seasons_phase->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('season_phase_id') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Initial / Repeat Order --}}
                        <div>
                            <label class="f-label">Initial / Repeat Order <span class="f-required">*</span></label>
                            <select id="Repeat_Order" name="order_type_id" required
                                class="f-input custom-select @error('order_type_id') border-red-400 @enderror">
                                <option value="">Select Initial / Repeat Order</option>
                                @foreach ($initialRepeats as $initialRepeat)
                                    <option value="{{ $initialRepeat->id }}"
                                        {{ $initialRepeat->id == $chartInfo->initial_repeated_id ? 'selected' : '' }}>
                                        {{ $initialRepeat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('order_type_id') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>

                {{-- ── Product Details ── --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Product Details
                    </div>
                    <p class="section-desc">Core product information and identifiers.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        {{-- Product Launch Month --}}
                        <div>
                            <label class="f-label">Product Launch Month <span class="f-required">*</span></label>
                            <input type="text" name="product_launch_month" id="product_launch_month"
                                placeholder="Enter product launch month" required
                                class="f-input @error('product_launch_month') border-red-400 @enderror"
                                value="{{ $chartInfo->product_launch_month }}">
                            @error('product_launch_month') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Product Code --}}
                        <div>
                            <label class="f-label">Product Code <span class="f-required">*</span></label>
                            <input type="text" name="product_code" id="product_code"
                                placeholder="Enter product code" required
                                class="f-input @error('product_code') border-red-400 @enderror"
                                value="{{ $chartInfo->product_code }}">
                            @error('product_code') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Design No --}}
                        <div>
                            <label class="f-label">Design No <span class="f-required">*</span></label>
                            <input type="text" name="design_no" id="design_no"
                                placeholder="Enter design no" required
                                class="f-input @error('design_no') border-red-400 @enderror"
                                value="{{ $chartInfo->design_no }}">
                            @error('design_no') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Fabrication --}}
                        <div>
                            <label class="f-label">Fabrication <span class="f-required">*</span></label>
                            <select id="fabrication" name="fabrication" required
                                class="f-input custom-select @error('fabrication') border-red-400 @enderror">
                                <option value="">Select a fabrication</option>
                                @foreach ($fabrics as $fabric)
                                    <option value="{{ $fabric->id }}"
                                        {{ $chartInfo->fabrication_id == $fabric->id ? 'selected' : '' }}>
                                        {{ $fabric->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('fabrication') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Product Description --}}
                        <div class="sm:col-span-2">
                            <label class="f-label">Product Description <span class="f-required">*</span></label>
                            <input type="text" name="product_description" id="product_design"
                                placeholder="Enter product description" required
                                class="f-input @error('product_description') border-red-400 @enderror"
                                value="{{ $chartInfo->product_description }}">
                            @error('product_description') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>

                {{-- ── Images ── --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Images
                    </div>
                    <p class="section-desc">Inspiration and design images for this chart entry.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        {{-- Inspiration Image --}}
                        <div>
                            <label class="f-label">Inspiration Image</label>
                            <input type="file" name="image" accept="image/*"
                                class="f-input image-input @error('image') border-red-400 @enderror">
                            <img class="image-preview mt-3 w-[120px] rounded-lg"
                                @if($chartInfo->inspiration_image) style="display:block;" @else style="display:none;" @endif
                                src="{{ $chartInfo->inspiration_image ? cloudflareImage($chartInfo->inspiration_image, 150) : '' }}"
                                alt="Inspiration Image Preview">
                            @error('image') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Design Image --}}
                        <div>
                            <label class="f-label">Design Image</label>
                            <input type="file" name="design_image" accept="image/*"
                                class="f-input image-input @error('design_image') border-red-400 @enderror">
                            <img class="image-preview mt-3 w-[120px] rounded-lg"
                                @if($chartInfo->design_image) style="display:block;" @else style="display:none;" @endif
                                src="{{ $chartInfo->design_image ? cloudflareImage($chartInfo->design_image, 150) : '' }}"
                                alt="Design Image Preview">
                            @error('design_image') <p class="f-error">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>

                {{-- ── Color / Price Table ── --}}
                <div class="section-card overflow-x-auto">
                    <div class="section-title mb-3">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
                        </svg>
                        Color &amp; Pricing
                    </div>

                    <input type="hidden" id="ch_in_id" value="{{ $chartInfo->id }}">
                    <div class="color-table mb-0">
                        @include('selling_chart.edit-color-table')
                    </div>

                    <div class="mt-3">
                        <button type="button"
                            class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] rounded-lg border border-cyan-200 dark:border-cyan-700 bg-cyan-50 dark:bg-cyan-900/20 text-cyan-600 dark:text-cyan-300 hover:bg-cyan-500 hover:text-white hover:border-cyan-500 transition-colors font-semibold add_more_btn">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add More
                        </button>
                    </div>
                </div>

            </div>

            {{-- ── STICKY FOOTER ── --}}
            <div class="sticky-footer mt-5 -mx-5 rounded-none">
                <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Fields marked <span class="text-red-400 mx-1">*</span> are required
                    </div>
                    <div class="flex gap-2.5">
                        <a href="{{ route('admin.selling_chart.index') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                            class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Chart
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>
@endsection
