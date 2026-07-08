<div class="p-5 space-y-6">

    {{-- Overview charts --}}
    <div>
        <p class="sec-heading mb-3">Monthly Performance Overview</p>
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="an-card p-5 ap-fs-panel" data-ap-fs-panel>
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="chart-title">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="12" width="4" height="9" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="4" width="4" height="17" rx="1"/></svg>
                            Revenue vs Total Cost vs Net Revenue
                        </div>
                        <p class="chart-subtitle">Monthly revenue, ad cost, and net revenue comparison</p>
                    </div>
                    @include('sale-spend.sale_tracking.partials.ap_fs_toggle')
                </div>
                <div class="ap-fs-body mt-4">
                    <div class="ap-chart-hscroll" data-chart-hscroll>
                        <div class="ap-chart-wrap ap-overview-chart-wrap">
                            <canvas id="apRevenueCostChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="an-card p-5 ap-fs-panel" data-ap-fs-panel>
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="chart-title">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="12" width="4" height="9" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="4" width="4" height="17" rx="1"/></svg>
                            Orders by Month
                        </div>
                        <p class="chart-subtitle">Total orders per month</p>
                    </div>
                    @include('sale-spend.sale_tracking.partials.ap_fs_toggle')
                </div>
                <div class="ap-fs-body mt-4">
                    <div class="ap-chart-hscroll" data-chart-hscroll>
                        <div class="ap-chart-wrap ap-overview-chart-wrap">
                            <canvas id="apOrdersChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="an-card p-5 ap-fs-panel" data-ap-fs-panel>
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="chart-title">
                            <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            Avg ROI by Month
                        </div>
                        <p class="chart-subtitle">Return on investment trend (%)</p>
                    </div>
                    @include('sale-spend.sale_tracking.partials.ap_fs_toggle')
                </div>
                <div class="ap-fs-body mt-4">
                    <div class="ap-chart-hscroll" data-chart-hscroll>
                        <div class="ap-chart-wrap ap-overview-chart-wrap">
                            <canvas id="apRoiChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="an-card p-5 ap-fs-panel" data-ap-fs-panel>
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="chart-title">
                            <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            Avg ROAS by Month
                        </div>
                        <p class="chart-subtitle">Return on ad spend trend (%)</p>
                    </div>
                    @include('sale-spend.sale_tracking.partials.ap_fs_toggle')
                </div>
                <div class="ap-fs-body mt-4">
                    <div class="ap-chart-hscroll" data-chart-hscroll>
                        <div class="ap-chart-wrap ap-overview-chart-wrap">
                            <canvas id="apRoasChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Engagement metrics — single chart with platform filter --}}
    @if(($chart_data['engagement_all'] ?? null) || count($chart_data['platforms'] ?? []) > 0)
        <div class="an-card p-5 ap-fs-panel" data-ap-fs-panel>
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-1">
                <div class="min-w-0">
                    <div class="chart-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                        <span id="apEngagementChartTitle">Engagement Metrics</span>
                    </div>
                    <p class="chart-subtitle">Reach · Impressions · Clicks · Sessions — monthly breakdown</p>
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
                    <div class="flex-1 sm:w-56 min-w-0">
                        @include('sale-spend.sale_tracking.partials.engagement_platform_select', [
                            'selectId' => 'apEngagementPlatformSelect',
                            'selected' => $selected_engagement_slug ?? 'all',
                            'sections' => $chart_data['platforms'] ?? [],
                        ])
                    </div>
                    @include('sale-spend.sale_tracking.partials.ap_fs_toggle')
                </div>
            </div>

            <div class="ap-fs-body mt-4">
                <div class="ap-chart-vscroll" id="apPlatformChartScroll">
                    <div class="ap-chart-wrap ap-chart-wrap-tall" id="apPlatformChartWrap">
                        <canvas id="apPlatformChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
