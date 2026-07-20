<table class="etd-table etd-table--colors w-full">
    <thead><tr><th>Product</th><th class="etd-num">SKU</th><th>Color</th><th class="etd-num">Viewed</th><th class="etd-num">Purchased</th></tr></thead>
    <tbody>
        @forelse ($data['products'] ?? [] as $product)
            <tr class="etd-color-product-row">
                <td>{{ $product['product'] }}</td>
                <td class="etd-num">{{ $product['sku'] }}</td>
                <td></td>
                <td class="etd-num">{{ number_format($product['viewed']) }}</td>
                <td class="etd-num">{{ number_format($product['purchased']) }}</td>
            </tr>
            @foreach ($product['variants'] as $variant)
                <tr class="etd-color-variant-row">
                    <td></td><td></td>
                    <td>{{ $variant['color'] }}</td>
                    <td class="etd-num">{{ number_format($variant['viewed']) }}</td>
                    <td class="etd-num">{{ number_format($variant['purchased']) }}</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="5" class="text-slate-400">No data.</td></tr>
        @endforelse
    </tbody>
</table>
