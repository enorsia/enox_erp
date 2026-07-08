@if(count($platform_sections) > 0)
    <div class="ap-fs-panel p-5" data-ap-fs-panel>
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
            <div class="min-w-0">
                <h3 id="apPlatformEngagementTitle" class="text-[14px] font-semibold text-slate-800 dark:text-slate-100">
                    {{ ($selected_engagement_slug ?? 'all') === 'all' ? 'All Platforms' : (collect($platform_sections)->firstWhere('slug', $selected_engagement_slug)['name'] ?? 'Platform Engagement') }}
                </h3>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Reach · Impressions · Clicks — monthly engagement</p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
                <div class="flex-1 sm:w-56 min-w-0">
                    @include('sale-spend.sale_tracking.partials.engagement_platform_select', [
                        'selectId' => 'apPlatformEngagementSelect',
                        'selected' => $selected_engagement_slug ?? 'all',
                        'sections' => $platform_sections,
                    ])
                </div>
                @include('sale-spend.sale_tracking.partials.ap_fs_toggle')
            </div>
        </div>

        <div class="ap-fs-body">
            <div class="ap-scroll-y overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700" id="apPlatformEngagementTable"></div>
        </div>
    </div>
@else
    <div class="px-4 py-16 text-center text-[13px] text-slate-400 dark:text-slate-500">
        No platform engagement data for the selected filters.
    </div>
@endif
