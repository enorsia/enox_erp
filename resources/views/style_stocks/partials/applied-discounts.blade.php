@php
    $firstPrice = $scInfo?->sellingChartPrices?->first();
@endphp

@if ($firstPrice)
    <p class="text-black dark:text-slate-200 text-[12px] font-medium mb-1">Applied Discounts:</p>

    @if ($firstPrice->range)
        @php
            $platformRanges = [];
            foreach ($scInfo->sellingChartPrices as $price) {
                foreach ($price->discounts as $discount) {
                    if (!$discount->price || !$discount->platform) {
                        continue;
                    }
                    $code = $discount?->platform?->code;
                    $platformRanges[$code][] = [
                        'range' => $price->range,
                        'price' => $discount->price,
                    ];
                }
            }
        @endphp

        <div class="grid grid-cols-2 gap-x-3 gap-y-1">
            @forelse ($platformRanges as $code => $ranges)
                <div class="ssr-product-meta">
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ strtoupper(str_replace('_', ' ', $code)) }}:</span>
                    @foreach ($ranges as $item)
                        <span class="block">{{ $item['range'] ?: 'N/A' }} — @price($item['price'])</span>
                    @endforeach
                </div>
            @empty
                <p class="ssr-product-meta col-span-2">No discounts applied</p>
            @endforelse
        </div>


    @else
        @php
            $platformDiscounts = $firstPrice->discounts->filter(fn($d) => $d->price && $d->platform);
        @endphp
        <div class="grid grid-cols-2 gap-x-3 gap-y-1">
            @forelse ($platformDiscounts as $discount)
                <p class="ssr-product-meta">
                    {{ strtoupper(str_replace('_', ' ', $discount->platform->code)) }}: @price($discount->price)
                </p>
            @empty
                <p class="ssr-product-meta col-span-2">No discounts applied</p>
            @endforelse
        </div>
    @endif
@endif
