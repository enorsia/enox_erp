<div class="overflow-x-auto">
    <table class="sticky-table w-full min-w-[1200px]">
        <thead>
            <tr>
                <th class="tbl-th sticky left-0 z-20 bg-slate-50 dark:bg-slate-800">Week</th>
                <th class="tbl-th sticky left-[52px] z-20 bg-slate-50 dark:bg-slate-800 min-w-[100px]">Date</th>
                <th class="tbl-th text-right">Daily Sales</th>
                <th class="tbl-th text-right">Daily ROAS</th>
                <th class="tbl-th text-right">Daily Spend</th>
                @foreach($platform_columns as $col)
                    <th class="tbl-th text-right">{{ $col['name'] }} ({{ $col['type_label'] }})</th>
                @endforeach
                @foreach($root_platforms as $p)
                    <th class="tbl-th text-right">{{ $p['name'] }} Orders</th>
                @endforeach
                <th class="tbl-th text-right">Total Orders</th>
                @foreach($root_platforms as $p)
                    <th class="tbl-th text-right">{{ $p['name'] }} Qty</th>
                @endforeach
                <th class="tbl-th text-right">Total Qty</th>
                <th class="tbl-th text-right">Kids</th>
                <th class="tbl-th text-right">Female</th>
                <th class="tbl-th text-right">Male</th>
            </tr>
        </thead>
        <tbody>
            @forelse($daily_rows as $idx => $row)
                <tr class="{{ $idx % 2 === 1 ? 'sr-row-alt' : '' }}">
                    <td class="sr-td sr-week-cell sticky left-0 z-10 text-center font-medium">W{{ $row['week'] }}</td>
                    <td class="sr-td sticky left-[52px] z-10">{{ $row['date_label'] }}</td>
                    <td class="sr-td text-right">{{ $row['sales_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['roas_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['spend_display'] }}</td>
                    @foreach($row['platform_cells'] as $cell)
                        <td class="sr-td text-right">{{ $cell['display'] }}</td>
                    @endforeach
                    @foreach($row['root_order_cells'] as $cell)
                        <td class="sr-td text-right">{{ $cell['display'] }}</td>
                    @endforeach
                    <td class="sr-td text-right font-medium">{{ $row['orders_display'] }}</td>
                    @foreach($row['root_qty_cells'] as $cell)
                        <td class="sr-td text-right">{{ $cell['display'] }}</td>
                    @endforeach
                    <td class="sr-td text-right font-medium">{{ $row['qty_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['kids_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['female_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['male_display'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 8 + count($platform_columns) + (count($root_platforms) * 2) }}" class="px-4 py-12 text-center text-[13px] text-slate-400">No daily rows match your filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
