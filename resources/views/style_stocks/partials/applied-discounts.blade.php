@if ($appliedDiscounts)
    <div class="rounded-lg p-2 bg-slate-200 dark:bg-slate-700/50 border border-transparent dark:border-slate-600">
        <p class="text-slate-800 dark:text-slate-200 text-[12px] font-medium mb-1">Applied Discounts:</p>

        @if ($appliedDiscounts['has_range'])
            <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                @forelse ($appliedDiscounts['platform_ranges'] as $code => $ranges)
                    <div class="ssr-product-meta text-slate-600 dark:text-slate-400">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ strtoupper(str_replace('_', ' ', $code)) }}:</span>
                        @foreach ($ranges as $item)
                            <span class="block text-slate-600 dark:text-slate-400">{{ $item['range'] ?: 'N/A' }} — @price($item['price'])</span>
                        @endforeach
                    </div>
                @empty
                    <p class="ssr-product-meta col-span-2 text-slate-600 dark:text-slate-400">No discounts applied</p>
                @endforelse
            </div>
        @else
            <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                @forelse ($appliedDiscounts['platform_discounts'] as $discount)
                    <p class="ssr-product-meta text-slate-600 dark:text-slate-400">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ strtoupper(str_replace('_', ' ', $discount['code'])) }}:</span>
                        @price($discount['price'])
                    </p>
                @empty
                    <p class="ssr-product-meta col-span-2 text-slate-600 dark:text-slate-400">No discounts applied</p>
                @endforelse
            </div>
        @endif
    </div>
@endif
