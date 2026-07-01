<div class="overflow-x-auto">
    <table class="sticky-table w-full min-w-[900px]">
        <thead>
            <tr>
                <th class="tbl-th sticky left-0 z-20 bg-slate-50 dark:bg-slate-800 min-w-[180px]">Summary</th>
                <th class="tbl-th text-right">Daily Sales</th>
                <th class="tbl-th text-right">Daily Spend</th>
                @foreach($platform_columns as $col)
                    <th class="tbl-th text-right">{{ $col['name'] }} ({{ $col['type_label'] }})</th>
                @endforeach
                <th class="tbl-th text-right">Orders</th>
                <th class="tbl-th text-right">Qty</th>
                <th class="tbl-th text-right">Kids</th>
                <th class="tbl-th text-right">Female</th>
                <th class="tbl-th text-right">Male</th>
            </tr>
        </thead>
        <tbody>
            @forelse($summary_rows as $row)
                <tr class="{{ $row['row_class'] }}">
                    <td class="sr-td sticky left-0 z-10 font-semibold text-slate-700 dark:text-slate-200">{{ $row['label'] }}</td>
                    <td class="sr-td text-right">{{ $row['sales_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['spend_display'] }}</td>
                    @foreach($row['platform_cells'] as $cell)
                        <td class="sr-td text-right">{{ $cell['display'] }}</td>
                    @endforeach
                    <td class="sr-td text-right">{{ $row['orders_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['qty_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['kids_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['female_display'] }}</td>
                    <td class="sr-td text-right">{{ $row['male_display'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 5 + count($platform_columns) }}" class="px-4 py-12 text-center text-[13px] text-slate-400">No summary rows match your filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
