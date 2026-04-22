@extends('layouts.app')

@section('title', 'Edit Platform')

@section('content')
    <div class="max-w-3xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Platform: {{ $platform->name }}</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Update platform settings and charges</p>
            </div>
        </div>

        <form action="{{ route('admin.platforms.update', $platform->id) }}" method="POST" id="platformEditForm">
            @csrf
            @method('PATCH')

            <div class="section-card">
                <div class="section-title">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M20 7H4a1 1 0 00-1 1v10a1 1 0 001 1h16a1 1 0 001-1V8a1 1 0 00-1-1zM9 11h6M9 15h4"/>
                    </svg>
                    Platform Details
                </div>
                <p class="section-desc">Update the settings for this platform.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Platform Name (readonly) -->
                    <div class="sm:col-span-2">
                        <label for="platform_name" class="f-label">Platform Name</label>
                        <input type="text" name="platform_name" id="platform_name"
                               class="f-input opacity-60 cursor-not-allowed"
                               value="{{ old('platform_name', $platform->name) }}" disabled />
                        <p class="f-hint">Platform name cannot be changed.</p>
                    </div>

                    <!-- Shipping Charge -->
                    <div>
                        <label for="shipping_charge" class="f-label">Shipping Charge</label>
                        <input type="number" name="shipping_charge" id="shipping_charge"
                               class="f-input @error('shipping_charge') border-red-400 @enderror"
                               value="{{ old('shipping_charge', $platform->shipping_charge) }}" placeholder="0.00" step="0.01" />
                        @error('shipping_charge') <p class="f-error">{{ $message }}</p> @enderror
                    </div>

                    <!-- Min Profit -->
                    <div>
                        <label for="min_profit" class="f-label">Min Profit <span class="f-required">*</span></label>
                        <input type="number" name="min_profit" id="min_profit"
                               class="f-input @error('min_profit') border-red-400 @enderror"
                               value="{{ old('min_profit', $platform->min_profit) }}" placeholder="0.00" step="0.01" required />
                        @error('min_profit') <p class="f-error">{{ $message }}</p> @enderror
                    </div>

                    <!-- Commission -->
                    <div>
                        <label for="commission" class="f-label">Commission <span class="f-required">*</span></label>
                        <input type="number" name="commission" id="commission"
                               class="f-input @error('commission') border-red-400 @enderror"
                               value="{{ old('commission', $platform->commission) }}" placeholder="e.g. 0.15 for 15%" step="0.001" required />
                        <p class="f-hint">Enter as decimal, e.g. 0.15 = 15%</p>
                        @error('commission') <p class="f-error">{{ $message }}</p> @enderror
                    </div>

                    <!-- Status -->
                    <div class="flex items-center gap-3 pt-6">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <div class="toggle-track {{ $platform->status ? 'on' : '' }}" id="statusToggle" onclick="toggleSwitch('statusToggle')">
                                <div class="toggle-thumb"></div>
                            </div>
                            <span class="text-sm text-slate-600 dark:text-slate-300 font-medium">Active status</span>
                            <input type="checkbox" name="status" class="hidden" id="statusCheckbox" {{ $platform->status ? 'checked' : '' }}>
                        </label>
                    </div>

                    <!-- Note -->
                    <div class="sm:col-span-2">
                        <label for="note" class="f-label">Note</label>
                        <textarea name="note" id="note"
                                  class="f-input @error('note') border-red-400 @enderror"
                                  rows="3" placeholder="Optional note...">{{ old('note', $platform->note) }}</textarea>
                        @error('note') <p class="f-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- ── STICKY FOOTER ── -->
            <div class="sticky-footer mt-5 -mx-5 rounded-none">
                <div class="max-w-3xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Fields marked <span class="text-red-400 mx-1">*</span> are required
                    </div>
                    <div class="flex gap-2.5">
                        <a href="{{ route('admin.platforms.index') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('js')
<script>
    document.getElementById('statusToggle').addEventListener('click', function() {
        var cb = document.getElementById('statusCheckbox');
        cb.checked = this.classList.contains('on');
    });
</script>
@endpush
