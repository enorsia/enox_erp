@php
    $products = $data['products'] ?? [];
    $expandedProduct = request('product');
@endphp

<div x-data="{ expanded: @js($expandedProduct) }" class="etd-product-catalog">
    <table class="etd-table etd-table--catalog etd-table--compact-head etd-table--performance-metrics w-full">
        <thead>
            <tr>
                <th class="etd-catalog-expand-col"></th>
                <th class="etd-col-product">Product</th>
                <th class="etd-col-product-code">Product code</th>
                <th class="etd-num etd-col-metric">Views</th>
                <th class="etd-num etd-col-metric">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Adds',
                        'tip' => 'Add to cart',
                        'align' => 'center',
                    ])
                </th>
                <th class="etd-num etd-col-metric">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Proceed',
                        'tip' => 'Proceed to checkout',
                        'align' => 'center',
                    ])
                </th>
                <th class="etd-num etd-col-metric">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Sold',
                        'tip' => 'Sale item',
                        'align' => 'center',
                    ])
                </th>
                <th class="etd-num etd-col-metric">Sale</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                @php
                    $productKey = $product['key'] ?? $product['product_code'] ?? $product['code'];
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
                        </button>
                    </td>
                    <td class="etd-col-product-code">
                        @php $productCode = trim((string) ($product['product_code'] ?? $product['code'] ?? '')); @endphp
                        @if ($productCode !== '')
                            <span class="etd-chip">{{ $productCode }}</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="etd-num etd-col-metric">{{ number_format($product['views']) }}</td>
                    <td class="etd-num etd-col-metric">{{ number_format($product['adds']) }}</td>
                    <td class="etd-num etd-col-metric">{{ number_format($product['proceed_checkouts'] ?? 0) }}</td>
                    <td class="etd-num etd-col-metric">{{ number_format($product['qty'] ?? 0) }}</td>
                    <td class="etd-num etd-col-metric">£{{ number_format($product['revenue'], 2) }}</td>
                </tr>
                @if ($variantCount > 0)
                    @foreach ($product['variants'] as $variant)
                        <tr class="etd-catalog-variant-row" x-show="expanded === @js($productKey)" x-cloak>
                            <td class="etd-catalog-expand-col"></td>
                            <td class="etd-col-product etd-catalog-variant-detail">
                                <span class="etd-catalog-variant-label">Color:</span>
                                <span>{{ $variant['color'] ?: '—' }}</span>
                                <span class="etd-catalog-variant-sep">·</span>
                                <span class="etd-catalog-variant-label">Size:</span>
                                <span>{{ $variant['size'] ?: '—' }}</span>
                            </td>
                            <td class="etd-col-product-code">
                                <span class="etd-catalog-variant-label">SKU:</span>
                                @if (! empty($variant['sku']))
                                    <span class="etd-chip">{{ $variant['sku'] }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="etd-num etd-col-metric">{{ number_format($variant['views']) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($variant['adds']) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($variant['proceed_checkouts'] ?? 0) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($variant['qty']) }}</td>
                            <td class="etd-num etd-col-metric">£{{ number_format($variant['revenue'], 2) }}</td>
                        </tr>
                    @endforeach
                @endif
            @empty
                <tr><td colspan="8" class="text-slate-400">No products match the current filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
