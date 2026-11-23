@php
    $type = $entry['type'] ?? 'flat';
    $code = $entry['code'] ?? '';
    $platformLabel = strtoupper(str_replace('_', ' ', $code));
@endphp

@if ($type === 'range')
    <div class="ssr-applied-discounts__chip ssr-applied-discounts__chip--range">
        <span class="ssr-applied-discounts__platform">{{ $platformLabel }}</span>
        <div class="ssr-applied-discounts__ranges">
            @foreach ($entry['ranges'] ?? [] as $item)
                <span class="ssr-applied-discounts__range-line">
                    <span class="ssr-applied-discounts__range">{{ $item['range'] ?: 'N/A' }}</span>
                    <span class="ssr-applied-discounts__price">@price($item['price'])</span>
                </span>
            @endforeach
        </div>
    </div>
@else
    <span class="ssr-applied-discounts__chip">
        <span class="ssr-applied-discounts__platform">{{ $platformLabel }}</span>
        <span class="ssr-applied-discounts__price">@price($entry['price'] ?? 0)</span>
    </span>
@endif