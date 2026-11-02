@php
    $totals = $totals ?? [];
    $totalsScopeLabel = trim((string) ($totalsScopeLabel ?? ''));
    $productCount = (int) ($totals['product_count'] ?? 0);
    $metrics = [
        ['key' => 'views', 'label' => 'View', 'tip' => 'Total product views in this view.'],
        ['key' => 'add_to_cart', 'label' => 'Add', 'tip' => 'Total add-to-cart units in this view.'],
        ['key' => 'begin_checkout', 'label' => 'Begin', 'tip' => 'Total begin-checkout units in this view.'],
        ['key' => 'proceed_checkout', 'label' => 'Proceed', 'tip' => 'Total proceed-checkout units in this view.'],
        ['key' => 'paid', 'label' => 'Paid', 'tip' => 'Total paid units in this view.'],
        ['key' => 'revenue', 'label' => 'Sale', 'tip' => 'Total sale amount in this view.', 'sale' => true],
    ];
@endphp

@if ($productCount > 0)
    <div class="etd-panel etd-print-unit etd-product-performance-totals" role="region" aria-label="Product performance totals for this view">
        <div class="etd-product-performance-totals__row">
            <div class="etd-product-performance-totals__lead">
                <span class="etd-product-performance-totals__lead-label">Total<span class="etd-product-performance-totals__lead-sep" aria-hidden="true">:</span></span>
                @if ($totalsScopeLabel !== '')
                    <span class="etd-product-performance-totals__lead-scope">{{ $totalsScopeLabel }}</span>
                @endif
            </div>
            <div class="etd-product-performance-totals__metrics">
                @foreach ($metrics as $metric)
                    @php
                        $isSale = (bool) ($metric['sale'] ?? false);
                        $raw = $isSale ? (float) ($totals['revenue'] ?? 0) : (int) ($totals[$metric['key']] ?? 0);
                        $formatted = $isSale ? '£'.number_format($raw, 2) : number_format($raw);
                    @endphp
                    <div @class(['etd-product-performance-totals__metric', 'etd-product-performance-totals__metric--sale' => $isSale])>
                        <span class="etd-product-performance-totals__metric-label">
                            @include('ecom_tracker.partials.column-header-with-tip', [
                                'label' => $metric['label'],
                                'tip' => $metric['tip'],
                            'align' => 'left',
                            'iconBefore' => true,
                            'labelSuffix' => ':',
                        ])
                        </span>
                        <span class="etd-product-performance-totals__metric-value">{{ $formatted }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
