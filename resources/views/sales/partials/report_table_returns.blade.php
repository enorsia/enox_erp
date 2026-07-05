<div class="overflow-x-auto">
    <table class="sticky-table sr-report-table w-full min-w-[900px]">
        @include('sales.partials.report_table_head', ['table_headers' => $table_headers['returns']])
        <tbody>
            @forelse($return_rows as $idx => $row)
                <tr class="{{ $idx % 2 === 1 ? 'sr-row-alt' : '' }}">
                    <td class="sr-td sr-sticky-head-label sr-row-label font-medium">{{ $row['name'] }}</td>
                    @foreach($row['root_cells'] as $cell)
                        <td class="sr-td sr-td-num sr-plat-cell {{ $cell['color_class'] ?? '' }} {{ $cell['count_edge'] ?? '' }}">{{ $cell['count_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell sr-td-muted {{ $cell['color_class'] ?? '' }} {{ $cell['pct_edge'] ?? '' }}">{{ $cell['pct_display'] }}</td>
                    @endforeach
                    <td class="sr-td sr-td-num sr-col-start {{ $row['kids_class'] ?? '' }}">{{ $row['kids_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $row['female_class'] ?? '' }}">{{ $row['female_display'] }}</td>
                    <td class="sr-td sr-td-num sr-col-end {{ $row['male_class'] ?? '' }}">{{ $row['male_display'] }}</td>
                    <td class="sr-td sr-td-num font-medium sr-col-start {{ $row['total_class'] ?? '' }}">{{ $row['total_display'] }}</td>
                    <td class="sr-td sr-td-num sr-td-muted sr-col-end">{{ $row['pct_display'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $table_headers['returns']['col_count'] }}" class="px-4 py-12 text-center text-[13px] sr-empty-cell">No return rows match your filters.</td>
                </tr>
            @endforelse
            @if(count($return_rows) > 0)
                <tr class="sr-row-total font-semibold">
                    <td class="sr-td sr-sticky-head-label sr-row-label">{{ $return_total['label'] }}</td>
                    @foreach($return_total['root_cells'] as $cell)
                        <td class="sr-td sr-td-num sr-plat-cell {{ $cell['color_class'] ?? '' }} {{ $cell['count_edge'] ?? '' }}">{{ $cell['count_display'] }}</td>
                        <td class="sr-td sr-td-num sr-plat-cell sr-td-muted {{ $cell['color_class'] ?? '' }} {{ $cell['pct_edge'] ?? '' }}">{{ $cell['pct_display'] }}</td>
                    @endforeach
                    <td class="sr-td sr-td-num sr-col-start {{ $return_total['kids_class'] ?? '' }}">{{ $return_total['kids_display'] }}</td>
                    <td class="sr-td sr-td-num {{ $return_total['female_class'] ?? '' }}">{{ $return_total['female_display'] }}</td>
                    <td class="sr-td sr-td-num sr-col-end {{ $return_total['male_class'] ?? '' }}">{{ $return_total['male_display'] }}</td>
                    <td class="sr-td sr-td-num sr-col-start {{ $return_total['total_class'] ?? '' }}">{{ $return_total['total_display'] }}</td>
                    <td class="sr-td sr-td-num sr-td-muted sr-col-end">{{ $return_total['pct_display'] }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
