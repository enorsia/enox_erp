{{-- Expandable edit panel — general info + per-platform calculation forms --}}
@php
    $ecommerceSku = ($ecommerceProduct['sku'] ?? '') ?: '—';
    $conversionRate = (float) ($expenseConfig['conversion_rate'] ?? 0);
    $defaultShippingCost = (float) ($expenseConfig['shipping_cost'] ?? 0);
    $badge = match(true) {
        $chartInfo->status == 1 => ['label' => 'Approved',     'cls' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'],
        $chartInfo->status == 2 => ['label' => 'Rejected',     'cls' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'],
        default                 => ['label' => 'Not Approved', 'cls' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
    };
    $platformKeys = array_keys($platform_ncs);
    $platformDefaults = collect($platformKeys)->mapWithKeys(fn ($k) => [$k => false])->toArray();
@endphp

<div class="discount-edit-panel border-t border-slate-100 dark:border-slate-700"
     x-data="{ showDetails: false, openPlatforms: @js($platformDefaults) }">

    {{-- Toggle: General / Product Details --}}
    <button type="button" @click="showDetails = !showDetails"
            class="w-full flex items-center justify-between px-4 py-2.5 text-left bg-slate-50/80 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
        <span class="flex items-center gap-2 text-[12px] font-semibold text-slate-600 dark:text-slate-300">
            <svg class="w-3.5 h-3.5 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Product Details
        </span>
        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="showDetails && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- General Information --}}
    <div x-show="showDetails" x-collapse class="px-4 py-3 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-2.5">
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
                'Ecom SKU'         => $ecommerceSku,
                'Design No'        => $chartInfo->design_no,
                'Fabrication'      => $chartInfo->fabrication,
            ] as $label => $value)
                <div>
                    <p class="text-[9px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">{{ $label }}</p>
                    <p class="text-[12px] text-slate-700 dark:text-slate-200 font-medium leading-snug truncate" title="{{ $value }}">{{ $value ?: '—' }}</p>
                </div>
            @endforeach
            <div>
                <p class="text-[9px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">Status</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $badge['cls'] }}">{{ $badge['label'] }}</span>
            </div>
        </div>

        @if ($chartInfo->design_image || $chartInfo->inspiration_image)
            <div class="flex flex-wrap gap-3 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                @if ($chartInfo->design_image)
                    <div class="text-center">
                        <p class="text-[9px] font-semibold text-slate-400 uppercase mb-1">Design</p>
                        <img class="w-16 h-16 rounded-lg object-cover border border-slate-200 dark:border-slate-700 cursor-zoom-in hover:opacity-90 transition-opacity"
                             src="{{ cloudflareImage($chartInfo->design_image, 130) }}"
                             @click="$dispatch('discount-image-popup', '{{ cloudflareImage($chartInfo->design_image, 1200) }}')"
                             alt="Design">
                    </div>
                @endif
                @if ($chartInfo->inspiration_image)
                    <div class="text-center">
                        <p class="text-[9px] font-semibold text-slate-400 uppercase mb-1">Inspiration</p>
                        <img class="w-16 h-16 rounded-lg object-cover border border-slate-200 dark:border-slate-700 cursor-zoom-in hover:opacity-90 transition-opacity"
                             src="{{ cloudflareImage($chartInfo->inspiration_image, 130) }}"
                             @click="$dispatch('discount-image-popup', '{{ cloudflareImage($chartInfo->inspiration_image, 1200) }}')"
                             alt="Inspiration">
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Column Toggles --}}
    <div class="flex flex-wrap items-center gap-2.5 px-4 py-2 bg-slate-50/60 dark:bg-slate-700/20 border-b border-slate-100 dark:border-slate-700">
        <span class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Columns:</span>
        <label class="flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" class="toggle-column w-3 h-3 rounded accent-accent-400" value="commission">
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Price &amp; Commission</span>
        </label>
        <label class="flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" class="toggle-column w-3 h-3 rounded accent-accent-400" value="vat">
            <span class="text-[11px] text-slate-500 dark:text-slate-400">VAT details</span>
        </label>
    </div>

    {{-- Platform Checkboxes --}}
    <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700">
        <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">Platforms</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($platform_ncs as $p_code => $p_name)
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border cursor-pointer select-none transition-colors text-[11px] font-medium"
                       :class="openPlatforms['{{ $p_code }}']
                           ? 'border-accent-300 bg-accent-50 text-accent-600 dark:border-accent-600 dark:bg-accent-800/30 dark:text-accent-300'
                           : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:border-slate-300'">
                    <input type="checkbox" class="w-3 h-3 rounded accent-accent-400"
                           x-model="openPlatforms['{{ $p_code }}']">
                    {{ $p_name }}
                </label>
            @endforeach
        </div>
    </div>

    {{-- Platform Calculation Panels --}}
    <div class="p-4 space-y-4">
        @foreach ($platform_ncs as $p_code => $p_name)
            @php $platform = $platforms->get($p_code); @endphp

            <div x-show="openPlatforms['{{ $p_code }}']" x-collapse
                 class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between px-3 py-2 bg-slate-50 dark:bg-slate-700/40 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">{{ $p_name }}</span>
                </div>

                <form class="pp-form p-3"
                      action="{{ route('admin.selling_chart.save.platform.discount.price') }}"
                      method="POST"
                      data-chart-id="{{ $chartInfo->id }}"
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

                    @can('general.discounts.update')
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="submit-btn flex items-center gap-1.5 px-4 py-1.5 text-[12px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save {{ $p_name }}
                            </button>
                        </div>
                    @endcan
                </form>
            </div>
        @endforeach
    </div>
</div>
