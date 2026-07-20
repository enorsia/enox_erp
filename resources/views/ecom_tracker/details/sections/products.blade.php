<table class="etd-table w-full">
    <thead><tr><th>Product</th><th class="etd-num">Views</th><th class="etd-num">Add to cart</th><th class="etd-num">Purchases</th><th class="etd-num">Sale</th></tr></thead>
    <tbody>
        @forelse ($data as $row)
            <tr>
                <td>{{ $row['name'] }}<div class="etd-subtle">{{ $row['code'] }}</div></td>
                <td class="etd-num">{{ number_format($row['views']) }}</td>
                <td class="etd-num">{{ number_format($row['adds']) }}</td>
                <td class="etd-num">{{ number_format($row['purchases']) }}</td>
                <td class="etd-num">£{{ number_format($row['revenue'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-slate-400">No data.</td></tr>
        @endforelse
    </tbody>
</table>
