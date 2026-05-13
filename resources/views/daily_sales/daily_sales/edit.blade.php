@extends('layouts.app')

@section('title', 'Edit Daily Sale')

@section('content')
<div id="daily-sales-page-content"></div>
<div class="px-5 py-6 pb-28">

    <!-- PAGE HEADER -->
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Daily Sale</h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">
                {{ $dailySale->salePlatform->name ?? 'N/A' }} — {{ $dailySale->date ? $dailySale->date->format('d M Y') : '' }}
            </p>
        </div>
    </div>

    @if($errors->any())
    <div class="mb-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <p class="text-xs font-semibold text-red-600 dark:text-red-400 mb-1">Please fix the following errors:</p>
        <ul class="text-sm text-red-600 dark:text-red-400 space-y-1">
            @foreach($errors->all() as $error)
                <li>• {{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.daily-sales.update', $dailySale->id) }}" id="EditValidateForm">
        @csrf
        @method('PUT')

        <!-- ── DATE ROW ── -->
        <div class="section-card !mb-4">
            <div class="flex flex-wrap items-end gap-5">
                <div class="w-56">
                    <label class="f-label">Date <span class="f-required">*</span></label>
                    <input type="date" name="date"
                           class="f-input @error('date') border-red-400 @enderror"
                           value="{{ old('date', $dailySale->date ? $dailySale->date->format('Y-m-d') : '') }}" required />
                    @error('date') <p class="f-error">{{ $message }}</p> @enderror
                </div>
                <div class="flex-1 text-xs text-slate-400 dark:text-slate-500 pb-1.5">
                    <span class="font-medium text-slate-500 dark:text-slate-400">Created:</span>
                    {{ $dailySale->created_at ? $dailySale->created_at->diffForHumans() : 'N/A' }}
                    &nbsp;&middot;&nbsp;
                    <span class="font-medium text-slate-500 dark:text-slate-400">Updated:</span>
                    {{ $dailySale->updated_at ? $dailySale->updated_at->diffForHumans() : 'N/A' }}
                </div>
            </div>
        </div>

        <!-- ── COLUMN HEADERS (xl+) ── -->
        <div class="hidden xl:grid gap-2 px-3 mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500"
             style="grid-template-columns: minmax(180px,2.5fr) repeat(10,1fr)">
            <div>Platform</div>
            <div>Spent</div>
            <div>Sales</div>
            <div>Orders</div>
            <div>Qty</div>
            <div>M. Orders</div>
            <div>F. Orders</div>
            <div>K. Orders</div>
            <div>M. Qty</div>
            <div>F. Qty</div>
            <div>K. Qty</div>
        </div>

        <!-- ── ENTRY ROW ── -->
        <div class="section-card !p-2.5 !mb-0">
            <div class="flex flex-col xl:flex-row xl:items-start gap-2">

                <!-- Platform -->
                <div class="xl:shrink-0 xl:w-[220px]">
                    <label class="f-label text-[10px] xl:hidden">Platform *</label>
                    <select name="sale_platform_id" class="tom-select w-full @error('sale_platform_id') border-red-400 @enderror" required>
                        <option value="">Select platform</option>
                        @foreach($salePlatforms as $platform)
                            <option value="{{ $platform['id'] }}" {{ old('sale_platform_id', $dailySale->sale_platform_id) == $platform['id'] ? 'selected' : '' }}>
                                {{ $platform['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('sale_platform_id') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                </div>

                <!-- Numeric fields -->
                <div class="flex-1 grid grid-cols-2 sm:grid-cols-5 xl:grid-cols-10 gap-2">

                    <div>
                        <label class="f-label text-[10px] xl:hidden">Spent *</label>
                        <input type="number" name="spent" step="0.01" min="0"
                               class="tbl-input @error('spent') border-red-400 @enderror"
                               placeholder="0.00" value="{{ old('spent', $dailySale->spent) }}" required />
                        @error('spent') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="f-label text-[10px] xl:hidden">Sales *</label>
                        <input type="number" name="sales" step="0.01" min="0"
                               class="tbl-input @error('sales') border-red-400 @enderror"
                               placeholder="0.00" value="{{ old('sales', $dailySale->sales) }}" required />
                        @error('sales') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="f-label text-[10px] xl:hidden">Orders *</label>
                        <input type="number" name="number_of_orders" min="0"
                               class="tbl-input @error('number_of_orders') border-red-400 @enderror"
                               placeholder="0" value="{{ old('number_of_orders', $dailySale->number_of_orders) }}" required />
                        @error('number_of_orders') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="f-label text-[10px] xl:hidden">Qty *</label>
                        <input type="number" name="number_of_quantities" min="0"
                               class="tbl-input @error('number_of_quantities') border-red-400 @enderror"
                               placeholder="0" value="{{ old('number_of_quantities', $dailySale->number_of_quantities) }}" required />
                        @error('number_of_quantities') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="f-label text-[10px] xl:hidden">M. Orders</label>
                        <input type="number" name="number_of_male_orders" min="0"
                               class="tbl-input @error('number_of_male_orders') border-red-400 @enderror"
                               placeholder="0" value="{{ old('number_of_male_orders', $dailySale->number_of_male_orders) }}" />
                        @error('number_of_male_orders') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="f-label text-[10px] xl:hidden">F. Orders</label>
                        <input type="number" name="number_of_female_orders" min="0"
                               class="tbl-input @error('number_of_female_orders') border-red-400 @enderror"
                               placeholder="0" value="{{ old('number_of_female_orders', $dailySale->number_of_female_orders) }}" />
                        @error('number_of_female_orders') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="f-label text-[10px] xl:hidden">K. Orders</label>
                        <input type="number" name="number_of_kids_orders" min="0"
                               class="tbl-input @error('number_of_kids_orders') border-red-400 @enderror"
                               placeholder="0" value="{{ old('number_of_kids_orders', $dailySale->number_of_kids_orders) }}" />
                        @error('number_of_kids_orders') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="f-label text-[10px] xl:hidden">M. Qty</label>
                        <input type="number" name="number_of_male_quantities" min="0"
                               class="tbl-input @error('number_of_male_quantities') border-red-400 @enderror"
                               placeholder="0" value="{{ old('number_of_male_quantities', $dailySale->number_of_male_quantities) }}" />
                        @error('number_of_male_quantities') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="f-label text-[10px] xl:hidden">F. Qty</label>
                        <input type="number" name="number_of_female_quantities" min="0"
                               class="tbl-input @error('number_of_female_quantities') border-red-400 @enderror"
                               placeholder="0" value="{{ old('number_of_female_quantities', $dailySale->number_of_female_quantities) }}" />
                        @error('number_of_female_quantities') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="f-label text-[10px] xl:hidden">K. Qty</label>
                        <input type="number" name="number_of_kids_quantities" min="0"
                               class="tbl-input @error('number_of_kids_quantities') border-red-400 @enderror"
                               placeholder="0" value="{{ old('number_of_kids_quantities', $dailySale->number_of_kids_quantities) }}" />
                        @error('number_of_kids_quantities') <p class="f-error text-[10px]">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>
        </div>

        <!-- ── STICKY FOOTER ── -->
        <div class="sticky-footer mt-5 -mx-5 rounded-none">
            <div class="px-5 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                    <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Fields marked <span class="text-red-400 mx-1">*</span> are required
                </div>
                <div class="flex gap-2.5">
                    <a href="{{ route('admin.daily-sales.index') }}"
                       class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                        Cancel
                    </a>
                    <button type="submit"
                            class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Update Daily Sale
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

