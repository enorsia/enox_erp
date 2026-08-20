{{--
    Discount View-Item Modal
    — Injected via AJAX by viewChart() in discounts.js
    — NO Bootstrap needed; uses Tailwind + Alpine.js tabs
--}}
@php
    $conversionRate = (float) ($expenseConfig['conversion_rate'] ?? 0);
    $defaultShippingCost = (float) ($expenseConfig['shipping_cost'] ?? 0);
@endphp
<div id="viewSellingChartItemModal"
     x-data="{ imagePopup: null }"
     onclick="if(event.target===this) window.closeDiscountModal()"
     class="fixed inset-0 z-[9999] flex items-start justify-center bg-black/60 overflow-y-auto p-3 sm:p-5">

    {{-- ── Image Lightbox ── --}}
    <div x-show="imagePopup" x-cloak
         @click="imagePopup = null"
         class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/85 cursor-zoom-out p-6"
         style="display:none;">
        <button @click="imagePopup = null"
                class="absolute top-4 right-4 z-10 p-2 rounded-full bg-white/20 hover:bg-white/30 text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <img :src="imagePopup"
             class="max-h-[90vh] max-w-[90vw] rounded-xl shadow-2xl object-contain cursor-default"
             @click.stop>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-[1400px] my-auto shadow-2xl"
         onclick="event.stopPropagation()">

        <!-- ── HEADER ── -->
        <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-4 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 rounded-t-2xl">
            <h5 class="text-base font-semibold text-slate-800 dark:text-slate-100">Product Details</h5>
            <button onclick="window.closeDiscountModal()"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- ── BODY ── -->
        <div class="p-5 space-y-5">

            <!-- Product Info Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-3">
                @php
                    $badge = match(true) {
                        $chartInfo->status == 1 => ['label' => 'Approved',     'cls' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'],
                        $chartInfo->status == 2 => ['label' => 'Rejected',     'cls' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'],
                        default                 => ['label' => 'Not Approved', 'cls' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
                    };
                @endphp

                @foreach ([
                    'Department'       => $chartInfo->department_name,
                    'Season'           => $chartInfo->season_name,
                    'Season Phase'     => $chartInfo->phase_name,
                    'Initial / Repeat' => $chartInfo->initial_repeated_status,
                    'Launch Month'     => $chartInfo->product_launch_month,
                    'Description'      => $chartInfo->product_description,
                    'Product Category' => $chartInfo->category_name,
                    'Mini Category'    => $chartInfo->mini_category_name,
                    'Product Code'     => $chartInfo->product_code,
                    'Ecom SKU'         => ($skus['sku'] ?? ''),
                    'Design No'        => $chartInfo->design_no,
                    'Fabrication'      => $chartInfo->fabrication,
                ] as $label => $value)
                    <div>
                        <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">{{ $label }}</p>
                        <p class="text-[13px] text-slate-800 dark:text-slate-100 font-medium leading-snug">{{ $value ?: '—' }}</p>
                    </div>
                @endforeach

                <!-- Status badge -->
                <div>
                    <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">Status</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge['cls'] }}">
                        {{ $badge['label'] }}
                    </span>
                </div>
            </div>

            <!-- Design / Inspiration Images -->
            @if ($chartInfo->design_image || $chartInfo->inspiration_image)
                <div class="flex flex-wrap gap-4">
                    @if ($chartInfo->design_image)
                        <div class="text-center">
                            <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">Design Image</p>
                            <img class="w-28 h-28 rounded-xl object-cover border border-slate-200 dark:border-slate-700 cursor-zoom-in hover:opacity-90 transition-opacity"
                                 src="{{ cloudflareImage($chartInfo->design_image, 130) }}"
                                 @click="imagePopup = '{{ cloudflareImage($chartInfo->design_image, 1200) }}'"
                                 alt="Design Image">
                        </div>
                    @endif
                    @if ($chartInfo->inspiration_image)
                        <div class="text-center">
                            <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">Inspiration Image</p>
                            <img class="w-28 h-28 rounded-xl object-cover border border-slate-200 dark:border-slate-700 cursor-zoom-in hover:opacity-90 transition-opacity"
                                 src="{{ cloudflareImage($chartInfo->inspiration_image, 130) }}"
                                 @click="imagePopup = '{{ cloudflareImage($chartInfo->inspiration_image, 1200) }}'"
                                 alt="Inspiration Image">
                        </div>
                    @endif
                </div>
            @endif

            <!-- Column Toggles -->
            <div class="flex flex-wrap items-center gap-3 bg-slate-50 dark:bg-slate-700/40 rounded-xl px-4 py-3">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Show / Hide:</span>
                <label class="flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox" class="toggle-column w-3.5 h-3.5 rounded accent-accent-400" value="commission">
                    <span class="text-[12px] text-slate-600 dark:text-slate-300">Price &amp; Commission</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox" class="toggle-column w-3.5 h-3.5 rounded accent-accent-400" value="vat">
                    <span class="text-[12px] text-slate-600 dark:text-slate-300">VAT details</span>
                </label>
            </div>

            <!-- ── PLATFORM TABS (Alpine.js) ── -->
            @php $firstPlatformCode = array_key_first($platform_ncs); @endphp
            <div class="discount-edit-panel" x-data="{ tab: '{{ $firstPlatformCode }}' }">

                <!-- Tab Strip -->
                <div class="flex flex-wrap gap-1 border-b border-slate-200 dark:border-slate-700 mb-4 overflow-x-auto pb-px">
                    @foreach ($platform_ncs as $p_code => $p_name)
                        <button type="button"
                                @click="tab = '{{ $p_code }}'"
                                :class="tab === '{{ $p_code }}'
                                    ? 'border-accent-400 text-accent-400 bg-accent-400/5'
                                    : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                                class="px-4 py-2 text-[13px] font-medium border-b-2 -mb-px whitespace-nowrap transition-colors">
                            {{ $p_name }}
                        </button>
                    @endforeach
                </div>

                <!-- Tab Panels -->
                @foreach ($platform_ncs as $p_code => $p_name)
                    @php $platform = $platforms->get($p_code); @endphp

                    <div x-show="tab === '{{ $p_code }}'" x-cloak>
                        <form class="pp-form"
                              action="{{ route('admin.selling_chart.save.platform.discount.price') }}"
                              method="POST"
                              data-conversion-rate="{{ $conversionRate }}"
                              data-default-shipping="{{ $defaultShippingCost }}">
                            @csrf
                            <input type="hidden" name="platform_id"   class="platform_id"   value="{{ $platform->id }}" />
                            <input type="hidden" name="department_id" class="department_id" value="{{ $chartInfo->department_id }}" />

                            @include('selling_chart.discounts.partials.calc-table', [
                                'chartInfo' => $chartInfo,
                                'platform' => $platform,
                                'conversionRate' => $conversionRate,
                                'defaultShippingCost' => $defaultShippingCost,
                            ])

                        </form>
                    </div>
                @endforeach

                @can('general.discounts.update')
                    <div class="flex justify-end pt-4 border-t border-slate-200 dark:border-slate-700">
                        <button type="button"
                                class="save-all-discounts-btn flex items-center gap-2 px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Discounts
                        </button>
                    </div>
                @endcan

            </div>{{-- /Alpine tabs --}}
        </div>{{-- /body --}}
    </div>{{-- /panel --}}
</div>{{-- /overlay --}}
