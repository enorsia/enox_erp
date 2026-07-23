@props(['title', 'rows' => []])

@php
    $tableId = 'bd-' . md5($title);
@endphp

<div class="etd-panel" x-data="{
    rows: @js($rows),
    sortKey: 'count',
    sortDir: 'desc',
    sortedRows() {
        return [...this.rows].sort((a, b) => {
            const av = a[this.sortKey] ?? 0;
            const bv = b[this.sortKey] ?? 0;
            if (typeof av === 'string') {
                return this.sortDir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
            }
            return this.sortDir === 'asc' ? av - bv : bv - av;
        });
    },
    toggleSort(key) {
        if (this.sortKey === key) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortKey = key;
            this.sortDir = key === 'label' ? 'asc' : 'desc';
        }
    }
}">
    <div class="etd-panel-head">
        <h2 class="etd-panel-title">{{ $title }}</h2>
    </div>
    <div class="etd-table-scroll">
        <table class="etd-table w-full text-[12px]">
            <thead>
                <tr>
                    <th class="cursor-pointer select-none" @click="toggleSort('label')">Name</th>
                    <th class="etd-num cursor-pointer select-none" @click="toggleSort('count')">Sessions</th>
                    <th class="etd-num cursor-pointer select-none" @click="toggleSort('pct')">% of total</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in sortedRows()" :key="row.label">
                    <tr>
                        <td x-text="row.label"></td>
                        <td class="etd-num">
                            <div class="flex items-center gap-2 justify-end">
                                <div class="ga4-breakdown-bar flex-1 max-w-[80px] h-1.5 bg-slate-100 dark:bg-slate-700 rounded overflow-hidden">
                                    <div class="h-full bg-blue-400 dark:bg-blue-500 rounded" :style="'width:' + row.pct + '%'"></div>
                                </div>
                                <span x-text="row.count.toLocaleString()"></span>
                            </div>
                        </td>
                        <td class="etd-num" x-text="row.pct.toFixed(1) + '%'"></td>
                    </tr>
                </template>
                <tr x-show="rows.length === 0">
                    <td colspan="3" class="text-center text-slate-400 py-6">No data for this period</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
