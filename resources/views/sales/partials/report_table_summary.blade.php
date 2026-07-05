<div class="overflow-x-auto">
    <table class="sticky-table sr-report-table w-full min-w-[900px]">
        @include('sales.partials.report_table_head', ['table_headers' => $table_headers['summary']])
        <tbody>
            @forelse($summary_rows as $row)
                <tr class="{{ $row['row_class'] }}">
                    <td class="sr-td sr-sticky-head-label sr-row-label">{{ $row['label'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['sales_class'] ?? '' }}">{{ $row['sales_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['spend_class'] ?? '' }}">{{ $row['spend_display'] }}</td>
                    @foreach($row['platform_cells'] as $cell)
                        <td class="sr-td sr-td-num {{ $cell['color_class'] ?? '' }} {{ $cell['edge_class'] ?? '' }}">{{ $cell['display'] }}</td>
                    @endforeach
                    <td class="sr-td sr-td-num {{ $row['orders_class'] ?? '' }}">{{ $row['orders_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['qty_class'] ?? '' }}">{{ $row['qty_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['kids_class'] ?? '' }}">{{ $row['kids_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['female_class'] ?? '' }}">{{ $row['female_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['male_class'] ?? '' }}">{{ $row['male_display'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $table_headers['summary']['col_count'] }}" class="px-4 py-12 text-center text-[13px] sr-empty-cell">No summary rows match your filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
