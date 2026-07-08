<div class="ap-fs-panel p-5" data-ap-fs-panel>
    <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">Monthly Performance Summary</p>
        @include('sale-spend.sale_tracking.partials.ap_fs_toggle')
    </div>
    <div class="ap-fs-body">
        <div class="ap-scroll-y overflow-x-auto">
            <table class="sr-report-table ap-report-table ap-summary-table ap-sticky-table w-full min-w-[900px]">
                <thead class="ap-thead">
                    <tr>
                        <th class="ap-th ap-th-left ap-sticky-col ap-sticky-col-1">Month</th>
                        <th class="ap-th ap-th-right">Revenue (£)</th>
                        <th class="ap-th ap-th-right">Total Cost (£)</th>
                        <th class="ap-th ap-th-right">Net Revenue (£)</th>
                        <th class="ap-th ap-th-right">Orders</th>
                        <th class="ap-th ap-th-right">Clicks</th>
                        <th class="ap-th ap-th-right">Impressions</th>
                        <th class="ap-th ap-th-right">Avg ROI</th>
                        <th class="ap-th ap-th-right">Avg ROAS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary_rows as $row)
                        <tr class="{{ $row['row_class'] }}">
                            <td class="sr-td font-medium ap-sticky-col ap-sticky-col-1">{{ $row['label'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['revenue'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['total_cost'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['net_revenue'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['orders'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['clicks'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['impressions'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['avg_roi'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['avg_roas'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-[13px] sr-empty-cell">No summary rows match your filters.</td>
                        </tr>
                    @endforelse
                    @if(count($summary_rows) > 0)
                        <tr class="{{ $summary_totals['row_class'] }} ap-row-total-sticky">
                            <td class="sr-td font-bold ap-sticky-col ap-sticky-col-1">{{ $summary_totals['label'] }}</td>
                            <td class="sr-td sr-td-num font-bold">{{ $summary_totals['revenue'] }}</td>
                            <td class="sr-td sr-td-num font-bold">{{ $summary_totals['total_cost'] }}</td>
                            <td class="sr-td sr-td-num font-bold">{{ $summary_totals['net_revenue'] }}</td>
                            <td class="sr-td sr-td-num font-bold">{{ $summary_totals['orders'] }}</td>
                            <td class="sr-td sr-td-num font-bold">{{ $summary_totals['clicks'] }}</td>
                            <td class="sr-td sr-td-num font-bold">{{ $summary_totals['impressions'] }}</td>
                            <td class="sr-td sr-td-num font-bold">{{ $summary_totals['avg_roi'] }}</td>
                            <td class="sr-td sr-td-num font-bold">{{ $summary_totals['avg_roas'] }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
