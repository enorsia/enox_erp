@extends('layouts.app')

@section('title', 'Edit Daily Return')

@section('content')
    <div id="daily-returns-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Daily Return</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">
                    {{ $dailyReturn->salePlatform->name ?? 'N/A' }} — {{ $dailyReturn->returnReasonType->name ?? 'N/A' }}
                    — {{ $dailyReturn->date ? $dailyReturn->date->format('d M Y') : '' }}
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.daily-returns.update', $dailyReturn->id) }}" id="EditValidateForm">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

                <!-- LEFT COLUMN -->
                <div class="space-y-5">

                    <!-- ── Core Details ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Core Details
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Platform -->
                            <div class="sm:col-span-2">
                                <label class="f-label">Sale Platform <span class="f-required">*</span></label>
                                <select name="sale_platform_id" class="tom-select f-input @error('sale_platform_id') border-red-400 @enderror" required>
                                    <option value="">Select a platform</option>
                                    @foreach($salePlatforms as $platform)
                                        <option value="{{ $platform['id'] }}" {{ old('sale_platform_id', $dailyReturn->sale_platform_id) == $platform['id'] ? 'selected' : '' }}>
                                            {!! $platform['label'] !!}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sale_platform_id') <p class="f-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Return Reason -->
                            <div class="sm:col-span-2">
                                <label class="f-label">Return Reason <span class="f-required">*</span></label>
                                <select name="return_reason_type_id" class="tom-select f-input @error('return_reason_type_id') border-red-400 @enderror" required>
                                    <option value="">Select a reason</option>
                                    @foreach($reasonTypes as $reason)
                                        <option value="{{ $reason->id }}" {{ old('return_reason_type_id', $dailyReturn->return_reason_type_id) == $reason->id ? 'selected' : '' }}>
                                            {{ $reason->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('return_reason_type_id') <p class="f-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Date -->
                            <div>
                                <label class="f-label">Date <span class="f-required">*</span></label>
                                <input type="date" name="date"
                                       class="f-input @error('date') border-red-400 @enderror"
                                       value="{{ old('date', $dailyReturn->date ? $dailyReturn->date->format('Y-m-d') : '') }}" required />
                                @error('date') <p class="f-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Returns -->
                            <div>
                                <label class="f-label">Number of Returns <span class="f-required">*</span></label>
                                <input type="number" name="number_of_returns" min="0"
                                       class="f-input @error('number_of_returns') border-red-400 @enderror"
                                       value="{{ old('number_of_returns', $dailyReturn->number_of_returns) }}" required />
                                @error('number_of_returns') <p class="f-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Return Quantities -->
                            <div>
                                <label class="f-label">Return Quantities <span class="f-required">*</span></label>
                                <input type="number" name="number_of_return_quantities" min="0"
                                       class="f-input @error('number_of_return_quantities') border-red-400 @enderror"
                                       value="{{ old('number_of_return_quantities', $dailyReturn->number_of_return_quantities) }}" required />
                                @error('number_of_return_quantities') <p class="f-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- ── Gender Breakdown — Returns ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Gender Breakdown — Returns
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="f-label">Male Returns</label>
                                <input type="number" name="number_of_male_returns" min="0"
                                       class="f-input" value="{{ old('number_of_male_returns', $dailyReturn->number_of_male_returns) }}" />
                            </div>
                            <div>
                                <label class="f-label">Female Returns</label>
                                <input type="number" name="number_of_female_returns" min="0"
                                       class="f-input" value="{{ old('number_of_female_returns', $dailyReturn->number_of_female_returns) }}" />
                            </div>
                            <div>
                                <label class="f-label">Kids Returns</label>
                                <input type="number" name="number_of_kids_returns" min="0"
                                       class="f-input" value="{{ old('number_of_kids_returns', $dailyReturn->number_of_kids_returns) }}" />
                            </div>
                        </div>
                    </div>

                    <!-- ── Gender Breakdown — Return Quantities ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            Gender Breakdown — Return Quantities
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="f-label">Male Qty</label>
                                <input type="number" name="number_of_male_return_quantities" min="0"
                                       class="f-input" value="{{ old('number_of_male_return_quantities', $dailyReturn->number_of_male_return_quantities) }}" />
                            </div>
                            <div>
                                <label class="f-label">Female Qty</label>
                                <input type="number" name="number_of_female_return_quantities" min="0"
                                       class="f-input" value="{{ old('number_of_female_return_quantities', $dailyReturn->number_of_female_return_quantities) }}" />
                            </div>
                            <div>
                                <label class="f-label">Kids Qty</label>
                                <input type="number" name="number_of_kids_return_quantities" min="0"
                                       class="f-input" value="{{ old('number_of_kids_return_quantities', $dailyReturn->number_of_kids_return_quantities) }}" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="space-y-5">
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                            </svg>
                            Metadata
                        </div>
                        <div class="space-y-2 text-[12px] text-slate-500 dark:text-slate-400 mt-2">
                            <p>Created: {{ $dailyReturn->created_at ? $dailyReturn->created_at->diffForHumans() : 'N/A' }}</p>
                            <p>Updated: {{ $dailyReturn->updated_at ? $dailyReturn->updated_at->diffForHumans() : 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STICKY FOOTER ── -->
            <div class="sticky-footer mt-5 -mx-5 rounded-none">
                <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Fields marked <span class="text-red-400 mx-1">*</span> are required
                    </div>
                    <div class="flex gap-2.5">
                        <a href="{{ route('admin.daily-returns.index') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                                class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Daily Return
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

