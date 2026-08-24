@props(['event' => []])

@php
    $layout = $event['layout'] ?? 'detail';
    $infoGroups = $event['info_groups'] ?? [];
    $products = $event['products'] ?? [];
    $cartQty = (int) ($event['cart_qty'] ?? 0);
    $cartTotal = $event['cart_total'] ?? null;
    $showCartTotals = $products !== [] && ($cartQty > 0 || filled($cartTotal));
    $isCompact = $layout === 'compact';
@endphp

@if ($isCompact)
    <div class="etd-commerce-event-detail etd-commerce-event-detail--compact">
        @if ($products !== [])
            <div class="etd-commerce-event-detail__product-table-wrap">
                <table class="etd-commerce-event-detail__product-table">
                    <thead>
                        <tr>
                            <th class="etd-commerce-event-detail__product-col-title">Title</th>
                            <th class="etd-commerce-event-detail__product-col-size">Size</th>
                            <th class="etd-commerce-event-detail__product-col-color">Color</th>
                            <th class="etd-commerce-event-detail__product-col-qty etd-num">Qty</th>
                            <th class="etd-commerce-event-detail__product-col-price etd-num">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td class="etd-commerce-event-detail__product-col-title" data-label="Title">{{ $product['title'] ?? '—' }}</td>
                                <td class="etd-commerce-event-detail__product-col-size" data-label="Size">{{ $product['size'] ?? '—' }}</td>
                                <td class="etd-commerce-event-detail__product-col-color" data-label="Color">{{ $product['color_po'] ?? ($product['color_ecommerce'] ?? '—') }}</td>
                                <td class="etd-commerce-event-detail__product-col-qty etd-num" data-label="Qty">{{ $product['qty'] ?? '—' }}</td>
                                <td class="etd-commerce-event-detail__product-col-price etd-num" data-label="Price">{{ $product['price'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($showCartTotals)
                        <tfoot>
                            <tr class="etd-commerce-event-detail__product-total-row">
                                <td colspan="3" class="etd-commerce-event-detail__product-total-spacer"></td>
                                <td class="etd-num etd-commerce-event-detail__product-total-qty" data-label="Qty">{{ $cartQty > 0 ? $cartQty : '—' }}</td>
                                <td class="etd-num etd-commerce-event-detail__product-total-price" data-label="Total">{{ $cartTotal ?? '—' }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif

        @if (filled($event['footer_note'] ?? null))
            <p class="etd-commerce-event-detail__footer-note">{{ $event['footer_note'] }}</p>
        @endif
    </div>
@else
    <div class="etd-commerce-event-detail">
        <div class="etd-commerce-event-detail__head">
            <span class="etd-commerce-event-detail__badge">{{ $event['stage_label'] ?? 'Event' }}</span>
            <span class="etd-commerce-event-detail__title">{{ $event['title'] ?? 'Details' }}</span>
            @if (filled($event['occurred_at'] ?? null))
                <span class="etd-commerce-event-detail__when">{{ $event['occurred_at'] }}</span>
            @endif
        </div>

        @if ($infoGroups !== [])
            <div class="etd-commerce-event-detail__groups">
                @foreach ($infoGroups as $group)
                    <section class="etd-commerce-event-detail__group">
                        <h4 class="etd-commerce-event-detail__group-title">{{ $group['title'] ?? 'Details' }}</h4>
                        <dl class="etd-commerce-event-detail__fields">
                            @foreach ($group['fields'] ?? [] as $field)
                                <div class="etd-commerce-event-detail__field">
                                    <dt>{{ $field['label'] ?? '' }}</dt>
                                    <dd @class(['etd-commerce-event-detail__value--emphasis' => ! empty($field['emphasis'])])>
                                        {{ $field['value'] ?? '—' }}
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>
        @endif

        @if ($products !== [])
            <section class="etd-commerce-event-detail__products">
                <h4 class="etd-commerce-event-detail__group-title">Products</h4>
                <div class="etd-commerce-event-detail__product-table-wrap">
                    <table class="etd-commerce-event-detail__product-table">
                        <thead>
                            <tr>
                                <th class="etd-commerce-event-detail__product-col-title">Title</th>
                                <th class="etd-commerce-event-detail__product-col-size">Size</th>
                                <th class="etd-commerce-event-detail__product-col-color">Color (PO)</th>
                                <th class="etd-commerce-event-detail__product-col-color-ecom">Color</th>
                                <th class="etd-commerce-event-detail__product-col-qty etd-num">Qty</th>
                                <th class="etd-commerce-event-detail__product-col-price etd-num">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <td class="etd-commerce-event-detail__product-col-title" data-label="Title">{{ $product['title'] ?? '—' }}</td>
                                    <td class="etd-commerce-event-detail__product-col-size" data-label="Size">{{ $product['size'] ?? '—' }}</td>
                                    <td class="etd-commerce-event-detail__product-col-color" data-label="Color (PO)">{{ $product['color_po'] ?? '—' }}</td>
                                    <td class="etd-commerce-event-detail__product-col-color-ecom" data-label="Color">{{ $product['color_ecommerce'] ?? '—' }}</td>
                                    <td class="etd-commerce-event-detail__product-col-qty etd-num" data-label="Qty">{{ $product['qty'] ?? '—' }}</td>
                                    <td class="etd-num etd-commerce-event-detail__product-col-price etd-commerce-event-detail__value--emphasis" data-label="Price">{{ $product['price'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endif
