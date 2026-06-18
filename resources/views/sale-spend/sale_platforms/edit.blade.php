@extends('layouts.app')

@section('title', 'Edit Sale Platform')

@section('content')
    <div id="sale-platform-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Platform</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Update details for {{ $salePlatform->name }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.sale-platforms.update', $salePlatform->id) }}" id="EditValidateForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_url" value="{{ request('return_url') }}" />

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
                        <p class="section-desc">Update the platform details.</p>

                        <div class="grid grid-cols-1 gap-4">

                            <!-- Name -->
                            <div>
                                <label class="f-label">Name <span class="f-required">*</span></label>
                                <input type="text" name="name"
                                       class="f-input @error('name') border-red-400 @enderror"
                                       value="{{ old('name', $salePlatform->name) }}" required/>
                                @error('name')
                                <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Slug -->
                            <div>
                                <label class="f-label">Slug</label>
                                <input type="text" name="slug" id="slug"
                                       class="f-input @error('slug') border-red-400 @enderror"
                                       value="{{ old('slug', $salePlatform->slug) }}"/>
                                <p class="f-hint">Leave blank to auto-generate from name.</p>
                                @error('slug')
                                <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="f-label">Channel <span class="f-required">*</span></label>
                                <select name="type" class="tom-select f-input @error('type') border-red-400 @enderror" required>
                                    <option value="">Select Channel</option>
                                    @foreach ($types as $key => $type)
                                        <option value="{{ $key }}"
                                                {{ old('type', $salePlatform->type) == $key ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="f-hint">Choose the type of platform.</p>
                                @error('type')
                                <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Parent Platform (full hierarchical tree, current platform + descendants excluded) -->
                            <div>
                                <label class="f-label">Parent Platform</label>
                                <select name="parent_id" class="tom-select f-input @error('parent_id') border-red-400 @enderror"
                                        data-placeholder="None (top-level)">
                                    <option value="">None (top-level)</option>
                                    @foreach ($parentOptions as $option)
                                        <option value="{{ $option['id'] }}"
                                                data-depth="{{ $option['depth'] }}"
                                                data-parent-id="{{ $option['parent_id'] }}"
                                                {{ old('parent_id', $salePlatform->parent_id) == $option['id'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="f-hint">
                                    Full hierarchy shown. This platform and its descendants are excluded to prevent circular nesting.
                                </p>
                                @error('parent_id')
                                <p class="f-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Sort Order -->
                            <div>
                                <label class="f-label">Sort Order</label>
                                <input type="number" name="sort_order" min="0" max="255"
                                       class="f-input @error('sort_order') border-red-400 @enderror"
                                       value="{{ old('sort_order', $salePlatform->sort_order) }}"/>
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
                                    <div class="toggle-track {{ old('is_active', $salePlatform->is_active) ? 'on' : '' }}"
                                         id="statusToggle" onclick="toggleSwitch('statusToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('statusToggle', event)">Active status</span>
                                    <input type="checkbox" name="is_active" id="statusCheckbox" class="hidden"
                                            {{ old('is_active', $salePlatform->is_active) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">Enable to make this platform active.</p>
                            </div>

                            <!-- Is Spent -->
                            <div>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('is_spent', $salePlatform->is_spent) ? 'on' : '' }}" id="isSpentToggle"
                                         onclick="toggleSwitch('isSpentToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('isSpentToggle', event)">Can track spent</span>
                                    <input type="checkbox" name="is_spent" id="isSpentCheckbox" class="hidden"
                                            {{ old('is_spent', $salePlatform->is_spent) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">Allow recording spending/cost data on this platform.</p>
                            </div>

                            <!-- Is Sales -->
                            <div>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('is_sales', $salePlatform->is_sales) ? 'on' : '' }}" id="isSalesToggle"
                                         onclick="toggleSwitch('isSalesToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('isSalesToggle', event)">Can track sales</span>
                                    <input type="checkbox" name="is_sales" id="isSalesCheckbox" class="hidden"
                                            {{ old('is_sales', $salePlatform->is_sales) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">Allow recording sales data on this platform.</p>
                            </div>

                            <!-- Allows Direct Entry -->
                            <div>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('allows_direct_entry', $salePlatform->allows_direct_entry) ? 'on' : '' }}" id="allowsDirectEntryToggle"
                                         onclick="toggleSwitch('allowsDirectEntryToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('allowsDirectEntryToggle', event)">Allow direct entry</span>
                                    <input type="checkbox" name="allows_direct_entry" id="allowsDirectEntryCheckbox" class="hidden"
                                            {{ old('allows_direct_entry', $salePlatform->allows_direct_entry) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">If enabled, sales/spent can be added directly to this platform. If disabled, entries can only be added to sub-platforms.</p>
                            </div>

                            <!-- Show in daily sale & spend report -->
                            <div class="pt-2 border-t border-slate-100 dark:border-slate-700">
                                <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Module Visibility</p>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('show_in_analytics', $salePlatform->show_in_analytics) ? 'on' : '' }}" id="showInAnalyticsToggle"
                                         onclick="toggleSwitch('showInAnalyticsToggle', event)">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('showInAnalyticsToggle', event)">Show in daily sale & spend report</span>
                                    <input type="checkbox" name="show_in_analytics" id="showInAnalyticsCheckbox" class="hidden"
                                            {{ old('show_in_analytics', $salePlatform->show_in_analytics) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">When enabled, this platform appears in the Daily Sale & Spend report, Daily Sales, and Daily Returns modules.</p>
                            </div>

                            <!-- Show in Ads performance -->
                            <div>
                                <div class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ old('show_in_sale_tracking', $salePlatform->show_in_sale_tracking) ? 'on' : '' }}" id="showInSaleTrackingToggle"
                                         onclick="toggleSwitch('showInSaleTrackingToggle', event); toggleTrackingColumns()">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium cursor-pointer"
                                          onclick="toggleSwitch('showInSaleTrackingToggle', event); toggleTrackingColumns()">Show in Ads performance</span>
                                    <input type="checkbox" name="show_in_sale_tracking" id="showInSaleTrackingCheckbox" class="hidden"
                                            {{ old('show_in_sale_tracking', $salePlatform->show_in_sale_tracking) ? 'checked' : '' }}>
                                </div>
                                <p class="f-hint mt-1 ml-11">When enabled, this platform appears in the Ads Performance module.</p>
                            </div>

                            <!-- Tracking Columns (shown only when Show in Ads performance is ON) -->
                            <div id="trackingColumnsSection"
                                 class="{{ old('show_in_sale_tracking', $salePlatform->show_in_sale_tracking) ? '' : 'hidden' }} ml-1 mt-1 p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                                <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2.5">
                                    Engagement Columns in Ads Performance
                                </p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-3 leading-relaxed">
                                    Choose which metrics are visible for this platform in the Ads Performance module and its export.
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
                                               {{ old($field, $salePlatform->$field) ? 'checked' : '' }}>
                                        <label for="{{ $field }}Checkbox" class="text-[12px] text-slate-600 dark:text-slate-300 cursor-pointer select-none">
                                            {{ $label }}
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Current position card ── -->
                    <div class="section-card bg-slate-50 dark:bg-slate-700/30 border-slate-200 dark:border-slate-700">
                        <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-2">Current position</p>
                        <div class="space-y-1.5">
                            @if ($salePlatform->parent)
                                <div class="flex items-center gap-1.5 text-[12px] text-slate-500 dark:text-slate-400">
                                    <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M15 11.25l-3-3m0 0l-3 3m3-3v7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Parent:</span>
                                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $salePlatform->parent->name }}</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1.5 text-[12px] text-slate-500 dark:text-slate-400">
                                    <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                                    </svg>
                                    <span class="font-medium text-slate-700 dark:text-slate-200">Top-level platform</span>
                                </div>
                            @endif
                            <div class="flex items-center gap-1.5 text-[12px] text-slate-500 dark:text-slate-400">
                                <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
                                </svg>
                                <span>Type:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-200">{{ ucfirst(str_replace('_', ' ', $salePlatform->type)) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-[12px] text-slate-500 dark:text-slate-400">
                                <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6z"/>
                                </svg>
                                <span>ID:</span>
                                <code class="font-mono text-slate-700 dark:text-slate-200">{{ $salePlatform->id }}</code>
                            </div>
                        </div>
                    </div>

                    <!-- ── Hierarchy warning ── -->
                    <div class="section-card bg-amber-50 dark:bg-amber-900/10 border-amber-100 dark:border-amber-800/30">
                        <div class="flex gap-2.5">
                            <svg class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                            </svg>
                            <div>
                                <p class="text-[12px] font-medium text-amber-700 dark:text-amber-300 mb-1">Circular nesting prevented</p>
                                <p class="text-[11px] text-amber-600 dark:text-amber-400 leading-relaxed">
                                    This platform and all its child platforms are hidden from the parent dropdown to prevent invalid circular relationships.
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
                            Update Platform
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
    setTimeout(function () {
        section.classList.toggle('hidden', !checkbox.checked);
    }, 0);
}
</script>
@endpush

