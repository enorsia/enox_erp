<div class="overflow-x-auto">
    <table class="sticky-table sr-report-table w-full min-w-[1200px]">
        @include('sales.partials.report_table_head', ['table_headers' => $table_headers['daily']])
        <tbody>
            @forelse($daily_rows as $idx => $row)
                <tr class="{{ $idx % 2 === 1 ? 'sr-row-alt' : '' }}">
                    <td class="sr-td sr-td-center sr-sticky-week font-medium">W{{ $row['week'] }}</td>
                    <td class="sr-td sr-sticky-date">{{ $row['date_label'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['sales_class'] ?? '' }}">{{ $row['sales_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['spend_class'] ?? '' }}">{{ $row['spend_display'] }}</td>
                    @foreach($row['platform_cells'] as $cell)
                        <td class="sr-td sr-td-num {{ $cell['color_class'] ?? '' }} {{ $cell['edge_class'] ?? '' }}">{{ $cell['display'] }}</td>
                    @endforeach
                    @foreach($row['root_order_cells'] as $cell)
                        <td class="sr-td sr-td-num {{ $cell['value_class'] ?? '' }} {{ $cell['edge_class'] ?? '' }}">{{ $cell['display'] }}</td>
                    @endforeach
                    <td class="sr-td sr-td-num font-medium {{ $row['orders_class'] ?? '' }} {{ $row['orders_total_edge'] ?? '' }}">{{ $row['orders_display'] }}</td>
                    @foreach($row['root_qty_cells'] as $cell)
                        <td class="sr-td sr-td-num {{ $cell['value_class'] ?? '' }} {{ $cell['edge_class'] ?? '' }}">{{ $cell['display'] }}</td>
                    @endforeach
                    <td class="sr-td sr-td-num font-medium {{ $row['qty_class'] ?? '' }} {{ $row['qty_total_edge'] ?? '' }}">{{ $row['qty_display'] }}</td>
                    @foreach($row['gender_cells'] as $cell)
                        <td class="sr-td sr-td-num {{ $cell['value_class'] ?? '' }} {{ $cell['edge_class'] ?? '' }}">{{ $cell['display'] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $table_headers['daily']['col_count'] }}" class="px-4 py-12 text-center text-[13px] sr-empty-cell">No daily rows match your filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
