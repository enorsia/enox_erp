<div class="overflow-x-auto">
    <table class="sticky-table w-full min-w-[900px]">
        <thead>
            <tr>
                <th class="tbl-th sticky left-0 z-20 bg-slate-50 dark:bg-slate-800 min-w-[160px]">Reason</th>
                @foreach($root_platforms as $p)
                    <th class="tbl-th text-right">{{ $p['name'] }}</th>
                    <th class="tbl-th text-right">% {{ $p['name'] }}</th>
                @endforeach
                <th class="tbl-th text-right">Kids</th>
                <th class="tbl-th text-right">Female</th>
                <th class="tbl-th text-right">Male</th>
                <th class="tbl-th text-right">Total</th>
                <th class="tbl-th text-right">% Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($return_rows as $idx => $row)
                <tr class="{{ $idx % 2 === 1 ? 'sr-row-alt' : '' }}">
                    <td class="sr-td sticky left-0 z-10 font-medium text-slate-700 dark:text-slate-200">{{ $row['name'] }}</td>
                    @foreach($row['root_cells'] as $cell)
                        <td class="sr-td text-right">{{ $cell['count_display'] }}</td>
                        <td class="sr-td text-right text-slate-500">{{ $cell['pct_display'] }}</td>
                    @endforeach
                    <td class="sr-td text-right">{{ $row['kids_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['female_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['male_display'] }}</td>
                    <td class="sr-td text-right font-medium">{{ $row['total_display'] }}</td>
                    <td class="sr-td text-right text-slate-500">{{ $row['pct_display'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 6 + (count($root_platforms) * 2) }}" class="px-4 py-12 text-center text-[13px] text-slate-400">No return rows match your filters.</td>
                </tr>
            @endforelse
            @if(count($return_rows) > 0)
                <tr class="sr-row-total font-semibold">
                    <td class="sr-td sticky left-0 z-10">{{ $return_total['label'] }}</td>
                    @foreach($return_total['root_cells'] as $cell)
                        <td class="sr-td text-right">{{ $cell['count_display'] }}</td>
                        <td class="sr-td text-right">{{ $cell['pct_display'] }}</td>
                    @endforeach
                    <td class="sr-td text-right">{{ $return_total['kids_display'] }}</td>
                    <td class="sr-td text-right">{{ $return_total['female_display'] }}</td>
                    <td class="sr-td text-right">{{ $return_total['male_display'] }}</td>
                    <td class="sr-td text-right">{{ $return_total['total_display'] }}</td>
                    <td class="sr-td text-right">{{ $return_total['pct_display'] }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
