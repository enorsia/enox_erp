<div class="ap-fs-panel p-5" data-ap-fs-panel>
    <div class="flex items-center justify-between gap-3 mb-3">
        <p class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">Ad Performance</p>
        @include('sale-spend.sale_tracking.partials.ap_fs_toggle')
    </div>
    <div class="ap-fs-body">
        <div class="ap-scroll-y overflow-x-auto">
            <table class="sr-report-table ap-report-table ap-sticky-table ap-performance-table w-full min-w-[1400px]">
                <thead class="ap-thead">
                    <tr>
                        @foreach($columns as $col)
                            @php
                                $stickyCol = match($col['key']) {
                                    'sl' => 'ap-sticky-col ap-sticky-col-1',
                                    'month_label' => 'ap-sticky-col ap-sticky-col-2',
                                    'platform' => 'ap-sticky-col ap-sticky-col-3',
                                    default => '',
                                };
                            @endphp
                            <th class="ap-th ap-th-{{ $col['align'] }} whitespace-nowrap {{ $stickyCol }}">{{ $col['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($performance_rows as $row)
                        <tr class="{{ $row['row_class'] }}">
                            <td class="sr-td sr-td-num ap-sticky-col ap-sticky-col-1">{{ $row['sl'] }}</td>
                            @if($row['is_first_in_month'])
                                <td class="sr-td sr-td-center ap-month-cell ap-sticky-col ap-sticky-col-2" rowspan="{{ $row['month_rowspan'] }}">{{ $row['month_label'] }}</td>
                            @endif
                            <td class="sr-td ap-sticky-col ap-sticky-col-3">{{ $row['platform'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['reach'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['impressions'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['clicks'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['sessions'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['engaged_sessions'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['users'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['net_cost'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['ads_tax'] }}</td>
                            @if($row['is_first_in_month'])
                                <td class="sr-td sr-td-num ap-month-cell" rowspan="{{ $row['month_rowspan'] }}">{{ $row['total_cost'] }}</td>
                            @endif
                            <td class="sr-td sr-td-num">{{ $row['orders'] }}</td>
                            <td class="sr-td sr-td-num">{{ $row['products'] }}</td>
                            @if($row['is_first_in_month'])
                                <td class="sr-td sr-td-num ap-month-cell" rowspan="{{ $row['month_rowspan'] }}">{{ $row['sales_growth'] }}</td>
                            @endif
                            <td class="sr-td sr-td-num">{{ $row['revenue'] }}</td>
                            @if($row['is_first_in_month'])
                                <td class="sr-td sr-td-num ap-month-cell" rowspan="{{ $row['month_rowspan'] }}">{{ $row['total_revenue'] }}</td>
                                <td class="sr-td sr-td-num ap-month-cell" rowspan="{{ $row['month_rowspan'] }}">{{ $row['total_return'] }}</td>
                                <td class="sr-td sr-td-num ap-month-cell" rowspan="{{ $row['month_rowspan'] }}">{{ $row['net_revenue'] }}</td>
                                <td class="sr-td sr-td-num ap-month-cell" rowspan="{{ $row['month_rowspan'] }}">{{ $row['roi'] }}</td>
                                <td class="sr-td sr-td-num ap-month-cell" rowspan="{{ $row['month_rowspan'] }}">{{ $row['roas'] }}</td>
                            @endif
                        </tr>
                    @endforeach
                    <tr class="{{ $performance_totals['row_class'] }} ap-row-total-sticky">
                        <td class="sr-td ap-sticky-col ap-sticky-col-1"></td>
                        <td class="sr-td ap-sticky-col ap-sticky-col-2"></td>
                        <td class="sr-td font-bold ap-sticky-col ap-sticky-col-3">{{ $performance_totals['label'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['reach'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['impressions'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['clicks'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['sessions'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['engaged_sessions'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['users'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['net_cost'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['ads_tax'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['total_cost'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['orders'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['products'] }}</td>
                        <td class="sr-td sr-td-num font-bold ap-td-empty">—</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['revenue'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['total_revenue'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['total_return'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['net_revenue'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['roi'] }}</td>
                        <td class="sr-td sr-td-num font-bold">{{ $performance_totals['roas'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
