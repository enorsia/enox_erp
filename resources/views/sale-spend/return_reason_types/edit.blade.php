@extends('layouts.app')

@section('title', 'Edit Return Reason Type')

@section('content')
    <div id="return-reason-type-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Return Reason Type</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Update details for {{ $return_reason->name }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.return-reason.update', $return_reason->id) }}" id="EditValidateForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_url" value="{{ request('return_url') }}" />

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

                <!-- LEFT COLUMN -->
                <div class="space-y-5">

                    <!-- ── Basic Information ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Basic Information
                        </div>
                        <p class="section-desc">Update the return reason type details.</p>

                        <div class="grid grid-cols-1 gap-4">
                            <!-- Name -->
                            <div>
                                <label class="f-label">Name <span class="f-required">*</span></label>
                                <input type="text" name="name"
                                       class="f-input @error('name') border-red-400 @enderror"
                                       value="{{ $return_reason->name ?? old('name') }}" required />
                                @error('name')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Slug -->
                            <div>
                                <label class="f-label">Slug</label>
                                <input type="text" name="slug" id="slug"
                                       class="f-input @error('slug') border-red-400 @enderror"
                                       value="{{ $return_reason->slug ?? old('slug') }}" />
                                <p class="f-hint">Leave blank to auto-generate from name.</p>
                                @error('slug')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="f-label">Description</label>
                                <textarea name="description"
                                          class="f-input @error('description') border-red-400 @enderror"
                                          rows="5">{{ $return_reason->description ?? old('description') }}</textarea>
                                @error('description')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Sort Order -->
                            <div>
                                <label class="f-label">Sort Order</label>
                                <input type="number" name="sort_order" min="0" max="255"
                                       class="f-input @error('sort_order') border-red-400 @enderror"
                                       value="{{ $return_reason->sort_order ?? old('sort_order', 0) }}" />
                                <p class="f-hint">Used to order the return reason types in the list.</p>
                                @error('sort_order')
                                    <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="space-y-5">

                    <!-- ── Status ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                            Status
                        </div>

                        <div class="space-y-4">
                            <!-- Active Status -->
                            <div>
                                <label class="flex items-center gap-3 cursor-pointer" onclick="toggleSwitch('statusToggle', event)">
                                    <div class="toggle-track {{ $return_reason->is_active ? 'on' : '' }}" id="statusToggle">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium">Active status</span>
                                </label>
                                {{-- checkbox is outside the label so the label has no native form control to activate --}}
                                <input type="checkbox" name="is_active" id="statusCheckbox" class="hidden"
                                       {{ $return_reason->is_active ? 'checked' : '' }}>
                                <p class="f-hint mt-1">Enable to make this return reason type active.</p>
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
                        <a href="{{ route('admin.return-reason.index') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                                class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Update Return Reason Type
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection