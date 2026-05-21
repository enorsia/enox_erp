@extends('layouts.app')

@section('title', 'Create Sale Platform')

@section('content')
    <div id="sale-platform-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Create New Sale Platform</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Fill in the details below to create a new sale platform</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.sale-platforms.store') }}" id="validateForm">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

                <!-- LEFT COLUMN -->
                <div class="space-y-5">

                    <!-- ── Basic Information ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Basic Information
                        </div>
                        <p class="section-desc">Enter the basic details for this sale platform.</p>

                        <div class="grid grid-cols-1 gap-4">

                            <!-- Name -->
                            <div>
                                <label class="f-label">Name <span class="f-required">*</span></label>
                                <input type="text" name="name"
                                       class="f-input @error('name') border-red-400 @enderror"
                                       placeholder="e.g. Amazon, eBay, Shopee"
                                       value="{{ old('name') }}" required/>
                                @error('name')
                                <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Slug -->
                            <div>
                                <label class="f-label">Slug</label>
                                <input type="text" name="slug" id="slug"
                                       class="f-input @error('slug') border-red-400 @enderror"
                                       placeholder="Auto-generated from name"
                                       value="{{ old('slug') }}"/>
                                <p class="f-hint">Leave blank to auto-generate from name.</p>
                                @error('slug')
                                <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="f-label">Type <span class="f-required">*</span></label>
                                <select name="type" class="tom-select f-input @error('type') border-red-400 @enderror" required>
                                    <option value="">Select Type</option>
                                    @foreach ($types as $type)
                                        <option value="{{ $type }}" {{ old('type') == $type ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="f-hint">Choose the type of platform.</p>
                                @error('type')
                                <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Parent Platform (full hierarchical tree) -->
                            <div>
                                <label class="f-label">Parent Platform</label>
                                <select name="parent_id" class="tom-select f-input @error('parent_id') border-red-400 @enderror"
                                        data-placeholder="None (top-level)">
                                    <option value="">None (top-level)</option>
                                    @foreach ($parentOptions as $option)
                                        <option value="{{ $option['id'] }}"
                                                data-depth="{{ $option['depth'] }}"
                                                {{ old('parent_id') == $option['id'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="f-hint">Select a parent platform if this is a sub-platform. The full hierarchy is shown.</p>
                                @error('parent_id')
                                <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Sort Order -->
                            <div>
                                <label class="f-label">Sort Order</label>
                                <input type="number" name="sort_order" min="0" max="255"
                                       class="f-input @error('sort_order') border-red-400 @enderror"
                                       placeholder="0" value="{{ old('sort_order', 0) }}"/>
                                <p class="f-hint">Used to order platforms within the same level.</p>
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
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                            </svg>
                            Status & Capabilities
                        </div>

                        <div class="space-y-3">
                            <!-- Active Status -->
                            <div>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('is_active') ? 'on' : '' }}" id="statusToggle"
                                         onclick="toggleSwitch('statusToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('statusToggle', event)">Active status</span>
                                    <input type="checkbox" name="is_active" id="statusCheckbox" class="hidden"
                                            {{ old('is_active') ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">Enable to make this sale platform active.</p>
                            </div>

                            <!-- Is Spent -->
                            <div>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('is_spent', true) ? 'on' : '' }}" id="isSpentToggle"
                                         onclick="toggleSwitch('isSpentToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('isSpentToggle', event)">Can track spent</span>
                                    <input type="checkbox" name="is_spent" id="isSpentCheckbox" class="hidden"
                                            {{ old('is_spent', true) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">Allow recording spending/cost data on this platform.</p>
                            </div>

                            <!-- Is Sales -->
                            <div>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('is_sales', true) ? 'on' : '' }}" id="isSalesToggle"
                                         onclick="toggleSwitch('isSalesToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('isSalesToggle', event)">Can track sales</span>
                                    <input type="checkbox" name="is_sales" id="isSalesCheckbox" class="hidden"
                                            {{ old('is_sales', true) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">Allow recording sales data on this platform.</p>
                            </div>

                            <!-- Allows Direct Entry -->
                            <div>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('allows_direct_entry', true) ? 'on' : '' }}" id="allowsDirectEntryToggle"
                                         onclick="toggleSwitch('allowsDirectEntryToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('allowsDirectEntryToggle', event)">Allow direct entry</span>
                                    <input type="checkbox" name="allows_direct_entry" id="allowsDirectEntryCheckbox" class="hidden"
                                            {{ old('allows_direct_entry', true) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">If enabled, sales/spent can be added directly to this platform. If disabled, entries can only be added to sub-platforms.</p>
                            </div>

                            <!-- Show in Analytics -->
                            <div class="pt-2 border-t border-slate-100 dark:border-slate-700">
                                <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Module Visibility</p>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('show_in_analytics', true) ? 'on' : '' }}" id="showInAnalyticsToggle"
                                         onclick="toggleSwitch('showInAnalyticsToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('showInAnalyticsToggle', event)">Show in Analytics</span>
                                    <input type="checkbox" name="show_in_analytics" id="showInAnalyticsCheckbox" class="hidden"
                                            {{ old('show_in_analytics', true) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">When enabled, this platform appears in the Analytics Dashboard, Daily Sales, and Daily Returns modules.</p>
                            </div>

                            <!-- Show in Sale Tracking -->
                            <div id="saleTrackingSection">
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('show_in_sale_tracking', true) ? 'on' : '' }}" id="showInSaleTrackingToggle"
                                         onclick="toggleSwitch('showInSaleTrackingToggle', event); toggleTrackingColumns()">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('showInSaleTrackingToggle', event); toggleTrackingColumns()">Show in Sale Tracking</span>
                                    <input type="checkbox" name="show_in_sale_tracking" id="showInSaleTrackingCheckbox" class="hidden"
                                            {{ old('show_in_sale_tracking', true) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">When enabled, this platform appears in the Sale Tracking module.</p>
                            </div>

                            <!-- Tracking Columns (shown only when Show in Sale Tracking is ON) -->
                            <div id="trackingColumnsSection"
                                 class="{{ old('show_in_sale_tracking', true) ? '' : 'hidden' }} ml-1 mt-1 p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                                <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2.5">
                                    Engagement Columns in Sale Tracking
                                </p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-3 leading-relaxed">
                                    Choose which metrics are visible for this platform in the Sale Tracking module and its export.
                                </p>
                                <div class="grid grid-cols-2 gap-y-2 gap-x-3">
                                    @foreach([
                                        'track_reach'            => 'Reach',
                                        'track_impressions'      => 'Impressions',
                                        'track_clicks'           => 'Clicks',
                                        'track_sessions'         => 'Sessions',
                                        'track_engaged_sessions' => 'Engaged Sessions',
                                        'track_users'            => 'Users',
                                    ] as $field => $label)
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="{{ $field }}" id="{{ $field }}Checkbox"
                                               class="w-3.5 h-3.5 rounded border-slate-300 dark:border-slate-600 text-accent-400 focus:ring-accent-400"
                                               {{ old($field, true) ? 'checked' : '' }}>
                                        <label for="{{ $field }}Checkbox" class="text-[12px] text-slate-600 dark:text-slate-300 cursor-pointer select-none">
                                            {{ $label }}
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Hierarchy hint card ── -->
                    <div class="section-card bg-blue-50 dark:bg-blue-900/10 border-blue-100 dark:border-blue-800/30">
                        <div class="flex gap-2.5">
                            <svg class="w-4 h-4 text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                            </svg>
                            <div>
                                <p class="text-[12px] font-medium text-blue-700 dark:text-blue-300 mb-1">Hierarchy tip</p>
                                <p class="text-[11px] text-blue-600 dark:text-blue-400 leading-relaxed">
                                    The parent dropdown shows the full platform tree. Indented items are children of the item above them.
                                    Leave blank to create a top-level (root) platform.
                                </p>
                            </div>
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
                        <a href="{{ route('admin.sale-platforms.index') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                                class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Create Sale Platform
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>
@endsection

@push('js')
<script>
function toggleTrackingColumns() {
    const checkbox = document.getElementById('showInSaleTrackingCheckbox');
    const section  = document.getElementById('trackingColumnsSection');
    if (!section) return;
    // Use a short defer so toggleSwitch() has time to update the checkbox first
    setTimeout(function () {
        section.classList.toggle('hidden', !checkbox.checked);
    }, 0);
}
</script>
@endpush

