@props(['event' => []])

@php
    $layout = $event['layout'] ?? 'detail';
    $infoGroups = $event['info_groups'] ?? [];
    $products = $event['products'] ?? [];
    $cartQty = (int) ($event['cart_qty'] ?? 0);
    $cartTotal = $event['cart_total'] ?? null;
    $showCartTotals = $products !== [] && ($cartQty > 0 || filled($cartTotal));
@endphp

@if ($layout === 'compact')
    <div class="etd-commerce-event-detail etd-commerce-event-detail--compact">
        @if ($products !== [])
            <div class="etd-commerce-event-detail__product-table-wrap">
                <table class="etd-commerce-event-detail__product-table">
                    <thead>
                        <tr>
                            <th class="etd-commerce-event-detail__product-col-image"></th>
                            <th>Title</th>
                            <th>Size</th>
                            <th>Color</th>
                            <th class="etd-num">Qty</th>
                            <th class="etd-num">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td class="etd-commerce-event-detail__product-col-image">
                                    @if (filled($product['image_url'] ?? null))
                                        <img src="{{ $product['image_url'] }}" alt="" class="etd-commerce-event-detail__product-image">
                                    @else
                                        <span class="etd-commerce-event-detail__product-image etd-commerce-event-detail__product-image--placeholder" aria-hidden="true"></span>
                                    @endif
                                </td>
                                <td>{{ $product['title'] ?? '—' }}</td>
                                <td>{{ $product['size'] ?? '—' }}</td>
                                <td>{{ $product['color_po'] ?? ($product['color_ecommerce'] ?? '—') }}</td>
                                <td class="etd-num">{{ $product['qty'] ?? '—' }}</td>
                                <td class="etd-num">{{ $product['price'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($showCartTotals)
                        <tfoot>
                            <tr class="etd-commerce-event-detail__product-total-row">
                                <td colspan="4"></td>
                                <td class="etd-num etd-commerce-event-detail__product-total-qty">{{ $cartQty > 0 ? $cartQty : '—' }}</td>
                                <td class="etd-num etd-commerce-event-detail__product-total-price">{{ $cartTotal ?? '—' }}</td>
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
                                <th class="etd-commerce-event-detail__product-col-image"></th>
                                <th>Title</th>
                                <th>Size</th>
                                <th>Color (PO)</th>
                                <th>Color</th>
                                <th class="etd-num">Qty</th>
                                <th class="etd-num">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <td class="etd-commerce-event-detail__product-col-image">
                                        @if (filled($product['image_url'] ?? null))
                                            <img src="{{ $product['image_url'] }}" alt="" class="etd-commerce-event-detail__product-image">
                                        @else
                                            <span class="etd-commerce-event-detail__product-image etd-commerce-event-detail__product-image--placeholder" aria-hidden="true"></span>
                                        @endif
                                    </td>
                                    <td>{{ $product['title'] ?? '—' }}</td>
                                    <td>{{ $product['size'] ?? '—' }}</td>
                                    <td>{{ $product['color_po'] ?? '—' }}</td>
                                    <td>{{ $product['color_ecommerce'] ?? '—' }}</td>
                                    <td class="etd-num">{{ $product['qty'] ?? '—' }}</td>
                                    <td class="etd-num etd-commerce-event-detail__value--emphasis">{{ $product['price'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endif
