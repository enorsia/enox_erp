@if ($appliedDiscounts)
    @php
        $entries = [];

        if (!empty($appliedDiscounts['has_range'])) {
            foreach ($appliedDiscounts['platform_ranges'] ?? [] as $code => $ranges) {
                $entries[] = ['type' => 'range', 'code' => $code, 'ranges' => $ranges];
            }
        } else {
            foreach ($appliedDiscounts['platform_discounts'] ?? [] as $discount) {
                $entries[] = [
                    'type' => 'flat',
                    'code' => $discount['code'],
                    'price' => $discount['price'],
                ];
            }
        }

        $visibleEntries = array_slice($entries, 0, 3);
        $extraEntries = array_slice($entries, 3);
        $extraCount = count($extraEntries);
    @endphp

    <div class="ssr-applied-discounts">
        <p class="ssr-applied-discounts__title">Applied discounts</p>

        @if ($entries === [])
            <p class="ssr-applied-discounts__empty">No discounts applied</p>
        @else
            <div class="ssr-applied-discounts__track">
                @foreach ($visibleEntries as $entry)
                    @include('style_stocks.partials.applied-discounts-platform', ['entry' => $entry])
                @endforeach
                @foreach ($extraEntries as $entry)
                    <div class="ssr-applied-discounts__extra hidden">
                        @include('style_stocks.partials.applied-discounts-platform', ['entry' => $entry])
                    </div>
                @endforeach
            </div>

            @if ($extraCount > 0)
                <button type="button"
                    class="ssr-applied-discounts__toggle"
                    data-extra-count="{{ $extraCount }}"
                    aria-expanded="false">
                    See more ({{ $extraCount }})
                </button>
            @endif
        @endif
    </div>
@endif