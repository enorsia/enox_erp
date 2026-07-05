<div class="overflow-x-auto">
    <table class="sticky-table sr-report-table w-full min-w-[1100px]">
        @include('sales.partials.report_table_head', ['table_headers' => $table_headers['weekly']])
        <tbody>
            @forelse($weekly_rows as $idx => $row)
                @php $platEdges = ['sr-col-start', '', '', '', '', 'sr-col-end']; @endphp
                <tr class="{{ $idx % 2 === 1 ? 'sr-row-alt' : '' }}">
                    <td class="sr-td sr-td-left sticky left-0 z-10 font-medium capitalize sr-sticky-row-label">{{ $row['label'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['sales_class'] ?? '' }}">{{ $row['sales_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['spend_class'] ?? '' }}">{{ $row['spend_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['orders_class'] ?? '' }}">{{ $row['orders_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['qty_class'] ?? '' }}">{{ $row['qty_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['returns_pcs_class'] ?? '' }}">{{ $row['returns_pcs_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['return_pct_qty_class'] ?? '' }}">{{ $row['return_pct_qty_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['returns_gbp_class'] ?? '' }}">{{ $row['returns_gbp_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['return_pct_amt_class'] ?? '' }}">{{ $row['return_pct_amt_display'] }}</td>
                    @foreach($row['platforms'] as $p)
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['sales_class'] ?? '' }} {{ $platEdges[0] }}">{{ $p['sales_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['orders_class'] ?? '' }}">{{ $p['orders_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['qty_class'] ?? '' }}">{{ $p['qty_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['return_amount_class'] ?? '' }}">{{ $p['return_amount_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['return_orders_class'] ?? '' }}">{{ $p['return_orders_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['return_qty_class'] ?? '' }} {{ $platEdges[5] }}">{{ $p['return_qty_display'] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $table_headers['weekly']['col_count'] }}" class="px-4 py-12 text-center text-[13px] sr-empty-cell">No weekly rows match your filters.</td>
                </tr>
            @endforelse
            @if(count($weekly_rows) > 0)
                @php $platEdges = ['sr-col-start', '', '', '', '', 'sr-col-end']; @endphp
                <tr class="sr-row-total font-semibold">
                    <td class="sr-td sr-td-left sticky left-0 z-10 sr-sticky-row-label">{{ $weekly_total['label'] }}</td>
                    <td class="sr-td sr-td-num {{ $weekly_total['sales_class'] ?? '' }}">{{ $weekly_total['sales_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $weekly_total['spend_class'] ?? '' }}">{{ $weekly_total['spend_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $weekly_total['orders_class'] ?? '' }}">{{ $weekly_total['orders_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $weekly_total['qty_class'] ?? '' }}">{{ $weekly_total['qty_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $weekly_total['returns_pcs_class'] ?? '' }}">{{ $weekly_total['returns_pcs_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $weekly_total['return_pct_qty_class'] ?? '' }}">{{ $weekly_total['return_pct_qty_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $weekly_total['returns_gbp_class'] ?? '' }}">{{ $weekly_total['returns_gbp_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $weekly_total['return_pct_amt_class'] ?? '' }}">{{ $weekly_total['return_pct_amt_display'] }}</td>
                    @foreach($weekly_total['platforms'] as $p)
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['sales_class'] ?? '' }} {{ $platEdges[0] }}">{{ $p['sales_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['orders_class'] ?? '' }}">{{ $p['orders_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['qty_class'] ?? '' }}">{{ $p['qty_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['return_amount_class'] ?? '' }}">{{ $p['return_amount_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['return_orders_class'] ?? '' }}">{{ $p['return_orders_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell {{ $p['color_class'] }} {{ $p['return_qty_class'] ?? '' }} {{ $platEdges[5] }}">{{ $p['return_qty_display'] }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</div>
