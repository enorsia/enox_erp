@php
    $products = $data['products'] ?? [];
    $expandedProduct = request('product');
@endphp

<div x-data="{ expanded: @js($expandedProduct) }" class="etd-product-catalog">
    <table class="etd-table etd-table--catalog etd-table--compact-head w-full">
        <thead>
            <tr>
                <th class="etd-catalog-expand-col"></th>
                <th class="etd-col-product">Product</th>
                <th class="etd-num">Views</th>
                <th class="etd-num">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Adds',
                        'tip' => 'Add to cart',
                        'align' => 'right',
                    ])
                </th>
                <th class="etd-num">Purchases</th>
                <th class="etd-num">Qty</th>
                <th class="etd-num">Sale</th>
                <th class="etd-num">Variants</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                @php
                    $productKey = $product['key'] ?? $product['code'];
                    $variantCount = $product['variant_count'] ?? count($product['variants'] ?? []);
                @endphp
                <tr class="etd-catalog-product-row" :class="{ 'is-expanded': expanded === @js($productKey) }">
                    <td class="etd-catalog-expand-col">
                        @if ($variantCount > 0)
                            <button type="button"
                                    class="etd-catalog-expand-btn"
                                    @click="expanded = expanded === @js($productKey) ? null : @js($productKey)"
                                    :aria-expanded="expanded === @js($productKey)">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': expanded === @js($productKey) }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        @endif
                    </td>
                    <td class="etd-col-product">
                        <button type="button"
                                class="etd-catalog-product-trigger"
                                @if ($variantCount > 0) @click="expanded = expanded === @js($productKey) ? null : @js($productKey)" @endif>
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ $product['name'] }}</span>
                            <div class="etd-subtle">{{ $product['code'] }}</div>
                        </button>
                    </td>
                    <td class="etd-num">{{ number_format($product['views']) }}</td>
                    <td class="etd-num">{{ number_format($product['adds']) }}</td>
                    <td class="etd-num">{{ number_format($product['purchases']) }}</td>
                    <td class="etd-num">{{ number_format($product['qty'] ?? 0) }}</td>
                    <td class="etd-num">£{{ number_format($product['revenue'], 2) }}</td>
                    <td class="etd-num">{{ number_format($variantCount) }}</td>
                </tr>
                @if ($variantCount > 0)
                    <tr class="etd-catalog-variant-wrap" x-show="expanded === @js($productKey)" x-cloak>
                        <td colspan="8" class="!p-0">
                            <div class="etd-catalog-variant-panel">
                                <table class="etd-table etd-table--variant-nested etd-table--compact-head w-full">
                                    <thead>
                                        <tr>
                                            <th>Color</th>
                                            <th>Size</th>
                                            <th class="etd-num">SKU</th>
                                            <th class="etd-num">Views</th>
                                            <th class="etd-num">
                                                @include('ecom_tracker.partials.column-header-with-tip', [
                                                    'label' => 'Adds',
                                                    'tip' => 'Add to cart',
                                                    'align' => 'right',
                                                ])
                                            </th>
                                            <th class="etd-num">Purchases</th>
                                            <th class="etd-num">Qty</th>
                                            <th class="etd-num">Sale</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($product['variants'] as $variant)
                                            <tr>
                                                <td>{{ $variant['color'] ?: '—' }}</td>
                                                <td>{{ $variant['size'] ?: '—' }}</td>
                                                <td class="etd-num"><span class="etd-chip">{{ $variant['sku'] ?: '—' }}</span></td>
                                                <td class="etd-num">{{ number_format($variant['views']) }}</td>
                                                <td class="etd-num">{{ number_format($variant['adds']) }}</td>
                                                <td class="etd-num">{{ number_format($variant['purchases']) }}</td>
                                                <td class="etd-num">{{ number_format($variant['qty']) }}</td>
                                                <td class="etd-num">£{{ number_format($variant['revenue'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="8" class="text-slate-400">No products match the current filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
