<div class="overflow-x-auto">
    <table class="sticky-table w-full min-w-[1100px]">
        <thead>
            <tr>
                <th class="tbl-th sticky left-0 z-20 bg-slate-50 dark:bg-slate-800">Week</th>
                <th class="tbl-th text-right">Sales (£)</th>
                <th class="tbl-th text-right">Spend (£)</th>
                <th class="tbl-th text-right">Order</th>
                <th class="tbl-th text-right">Order Qty</th>
                <th class="tbl-th text-right">Return Qty</th>
                <th class="tbl-th text-right">Return Qty %</th>
                <th class="tbl-th text-right">Return Amt (£)</th>
                <th class="tbl-th text-right">Return Amt %</th>
                @foreach($root_platforms as $p)
                    <th class="tbl-th text-right sr-plat-hdr {{ $p['color_class'] }}" colspan="6">{{ $p['name'] }}</th>
                @endforeach
            </tr>
            <tr>
                <th class="tbl-th sticky left-0 z-20 bg-slate-50 dark:bg-slate-800"></th>
                <th class="tbl-th" colspan="8"></th>
                @foreach($root_platforms as $p)
                    <th class="tbl-th text-right sr-plat-subhdr {{ $p['color_class'] }}">Sales</th>
                    <th class="tbl-th text-right sr-plat-subhdr {{ $p['color_class'] }}">Orders</th>
                    <th class="tbl-th text-right sr-plat-subhdr {{ $p['color_class'] }}">Qty</th>
                    <th class="tbl-th text-right sr-plat-subhdr {{ $p['color_class'] }}">Return (£)</th>
                    <th class="tbl-th text-right sr-plat-subhdr {{ $p['color_class'] }}">Ret Orders</th>
                    <th class="tbl-th text-right sr-plat-subhdr {{ $p['color_class'] }}">Ret Qty</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($weekly_rows as $idx => $row)
                <tr class="{{ $idx % 2 === 1 ? 'sr-row-alt' : '' }}">
                    <td class="sr-td sticky left-0 z-10 font-medium capitalize bg-white dark:bg-slate-900">{{ $row['label'] }}</td>
                    <td class="sr-td text-right">{{ $row['sales_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['spend_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['orders_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['qty_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['returns_pcs_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['return_pct_qty_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['returns_gbp_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['return_pct_amt_display'] }}</td>
                    @foreach($row['platforms'] as $p)
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['sales_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['orders_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['qty_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['return_amount_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['return_orders_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['return_qty_display'] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 9 + (count($root_platforms) * 6) }}" class="px-4 py-12 text-center text-[13px] text-slate-400">No weekly rows match your filters.</td>
                </tr>
            @endforelse
            @if(count($weekly_rows) > 0)
                <tr class="sr-row-total font-semibold">
                    <td class="sr-td sticky left-0 z-10">{{ $weekly_total['label'] }}</td>
                    <td class="sr-td text-right">{{ $weekly_total['sales_display'] }}</td>
                    <td class="sr-td text-right">{{ $weekly_total['spend_display'] }}</td>
                    <td class="sr-td text-right">{{ $weekly_total['orders_display'] }}</td>
                    <td class="sr-td text-right">{{ $weekly_total['qty_display'] }}</td>
                    <td class="sr-td text-right">{{ $weekly_total['returns_pcs_display'] }}</td>
                    <td class="sr-td text-right">{{ $weekly_total['return_pct_qty_display'] }}</td>
                    <td class="sr-td text-right">{{ $weekly_total['returns_gbp_display'] }}</td>
                    <td class="sr-td text-right">{{ $weekly_total['return_pct_amt_display'] }}</td>
                    @foreach($weekly_total['platforms'] as $p)
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['sales_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['orders_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['qty_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['return_amount_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['return_orders_display'] }}</td>
                        <td class="sr-td text-right sr-plat-cell {{ $p['color_class'] }}">{{ $p['return_qty_display'] }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</div>
