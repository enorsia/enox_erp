@php
    $detail = $detail ?? [];
    $galleryId = 'gallery-style-alert-' . ($detail['design_id'] ?? \Illuminate\Support\Str::slug($detail['style'] ?? 'style'));
@endphp

<div class="space-y-4 text-[13px] text-slate-700 dark:text-slate-200">
  <div class="flex flex-wrap items-start gap-4">
    @if (!empty($detail['image_thumb']))
      <a href="{{ $detail['image_full'] }}"
        data-fancybox="{{ $galleryId }}"
        data-caption="{{ $detail['style'] ?? '' }}"
        class="block rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 shrink-0 hover:scale-105 hover:shadow-md transition-all duration-150">
        <img src="{{ $detail['image_thumb'] }}" alt="{{ $detail['style'] ?? '' }}" class="w-20 h-20 object-cover" loading="lazy">
      </a>
    @endif
    <div class="min-w-0 flex-1 space-y-1">
      <p class="text-[15px] font-semibold text-slate-800 dark:text-slate-100 m-0">{{ $detail['style'] ?? '—' }}</p>
      @if (!empty($detail['item_no']))
        <p class="text-[12px] text-slate-500 m-0">Item: {{ $detail['item_no'] }}</p>
      @endif
      @if (!empty($detail['department']) || !empty($detail['categories']))
        <p class="text-[12px] text-slate-500 m-0">
          {{ $detail['department'] ?? '' }}
          @if (!empty($detail['department']) && !empty($detail['categories']))
            →
          @endif
          {{ $detail['categories'] ?? '' }}
        </p>
      @endif
      @if (!empty($detail['product_name']))
        <p class="text-[12px] m-0">{{ $detail['product_name'] }}</p>
      @endif
    </div>
    <div class="text-right shrink-0">
      <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 m-0">UK Stock</p>
      <p class="text-[16px] font-bold text-accent-500 m-0 tabular-nums">{{ $detail['uk_stock'] ?? 0 }}</p>
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-1">London stock</p>
      @include('style_stocks.partials.progress', [
        'percent' => $detail['london_stock_percent'] ?? 0,
        'value' => $detail['uk_stock'] ?? 0,
        'level' => 'product',
        'type' => 'stock',
      ])
    </div>
    <div>
      <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-1">BD stock</p>
      @include('style_stocks.partials.progress', [
        'percent' => $detail['bd_stock_percent'] ?? 0,
        'value' => $detail['bd_stock'] ?? 0,
        'level' => 'product',
        'type' => 'sold',
      ])
    </div>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 text-[12px] border border-slate-200 dark:border-slate-700 rounded-xl p-3 bg-slate-50/50 dark:bg-slate-900/20">
    <div><span class="text-slate-400 block text-[10px] uppercase">Total sold</span><span class="font-semibold tabular-nums">{{ $detail['total_sold'] ?? 0 }}</span></div>
    <div><span class="text-slate-400 block text-[10px] uppercase">Sold %</span><span class="font-semibold tabular-nums {{ !empty($detail['sold_alert']) ? 'text-red-500' : '' }}">{{ $detail['sold_percent'] ?? 0 }}%</span></div>
    <div><span class="text-slate-400 block text-[10px] uppercase">BD stock qty</span><span class="font-semibold tabular-nums">{{ $detail['bd_stock'] ?? 0 }}</span></div>
    <div><span class="text-slate-400 block text-[10px] uppercase">Cutting qty</span><span class="font-semibold tabular-nums">{{ $detail['cutting_qty'] ?? 0 }}</span></div>
    <div><span class="text-slate-400 block text-[10px] uppercase">Damage/Missing</span><span class="font-semibold tabular-nums">{{ $detail['damage_missing'] ?? 0 }}</span></div>
    @if (!empty($detail['launch_date']))
      <div><span class="text-slate-400 block text-[10px] uppercase">Launch</span>{{ $detail['launch_date'] }}</div>
    @endif
    @if (!empty($detail['stock_out_date']))
      <div><span class="text-slate-400 block text-[10px] uppercase">Stock out</span>{{ $detail['stock_out_date'] }}</div>
    @endif
    @if (!empty($detail['vendor']))
      <div><span class="text-slate-400 block text-[10px] uppercase">Vendor</span>{{ $detail['vendor'] }}</div>
    @endif
    @if (!empty($detail['fabric']))
      <div><span class="text-slate-400 block text-[10px] uppercase">Fabric</span>{{ $detail['fabric'] }}</div>
    @endif
  </div>

  @if (!empty($detail['purchase_orders']))
    <div class="space-y-2">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 m-0">Purchase orders</p>
      @foreach ($detail['purchase_orders'] as $po)
        <p class="text-[12px] m-0 text-slate-600 dark:text-slate-300">
          PO {{ $po['po_no'] ?? '—' }} · Season: {{ $po['season'] ?? '—' }} ·
          PO qty: <span class="font-semibold tabular-nums">{{ $po['po_qty'] ?? 0 }}</span> ·
          Prod: <span class="font-semibold tabular-nums">{{ $po['prod_qty'] ?? 0 }}</span>
        </p>
      @endforeach
    </div>
  @endif

  @foreach ($detail['colors'] ?? [] as $color)
    <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
      <div class="px-3 py-2 bg-slate-50 dark:bg-slate-900/30 flex items-center gap-2 border-b border-slate-200 dark:border-slate-700">
        @if (!empty($color['image_full']))
          <a href="{{ $color['image_full'] }}"
            data-fancybox="{{ $galleryId }}"
            data-caption="Swatch — {{ $color['name'] ?? '' }}@if (!empty($color['general_color'])) ({{ $color['general_color'] }}) @endif"
            class="block rounded-md overflow-hidden border border-slate-200 dark:border-slate-600 shrink-0 hover:scale-105 hover:shadow-md transition-all duration-150">
            <img src="{{ $color['image_thumb'] }}" alt="{{ $color['name'] ?? '' }}" class="w-8 h-8 object-cover" loading="lazy">
          </a>
        @endif
        <div>
          <p class="font-semibold m-0">{{ $color['name'] ?? '—' }}
            @if (!empty($color['general_color']))
              <span class="text-slate-400 font-normal">({{ $color['general_color'] }})</span>
            @endif
          </p>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-[11px] min-w-[480px]">
          <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-400 uppercase">
            <tr>
              <th class="text-left px-2 py-1.5">Warehouse</th>
              @foreach ($detail['size_headers'] ?? [] as $sizeLabel)
                <th class="text-center px-1 py-1.5">{{ $sizeLabel }}</th>
              @endforeach
              <th class="text-center px-1 py-1.5">Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($color['rows'] ?? [] as $row)
              <tr class="border-t border-slate-100 dark:border-slate-800">
                <td class="px-2 py-1 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $row['label'] ?? '' }}</td>
                @foreach ($row['cells'] ?? [] as $cell)
                  <td class="text-center px-1 py-1 tabular-nums">{{ $cell }}</td>
                @endforeach
                <td class="text-center px-1 py-1 font-semibold tabular-nums">{{ $row['total'] ?? '' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="px-3 py-1.5 border-t border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/15">
        <button type="button"
          class="ssr-style-alert-color-toggle"
          aria-expanded="false">
          Show shipping &amp; remark
        </button>
      </div>
      <div class="ssr-style-alert-color-meta hidden grid grid-cols-1 sm:grid-cols-2 gap-2 px-3 py-2 text-[11px] bg-slate-50/60 dark:bg-slate-900/20 border-t border-slate-200 dark:border-slate-700">
        <div><span class="text-slate-400 uppercase text-[10px]">Remark</span> {{ $color['remark'] ?? '—' }}</div>
        <div><span class="text-slate-400 uppercase text-[10px]">X factory date</span> {{ $color['x_factory_date'] ?? '—' }}</div>
        <div>
          <span class="text-slate-400 uppercase text-[10px] block">Air</span>
          @forelse ($color['air_packages']['sent'] ?? [] as $pkg)
            <p class="m-0">Sent {{ $pkg['send_at'] }}: <strong>{{ $pkg['quantity'] }}</strong></p>
          @empty
          @endforelse
          @foreach ($color['air_packages']['send'] ?? [] as $pkg)
            <p class="m-0">Send {{ $pkg['send_at'] }}: <strong>{{ $pkg['quantity'] }}</strong></p>
          @endforeach
        </div>
        <div>
          <span class="text-slate-400 uppercase text-[10px] block">Sea</span>
          @foreach ($color['sea_packages']['sent'] ?? [] as $pkg)
            <p class="m-0">Sent {{ $pkg['send_at'] }}: <strong>{{ $pkg['quantity'] }}</strong></p>
          @endforeach
          @foreach ($color['sea_packages']['send'] ?? [] as $pkg)
            <p class="m-0">Send {{ $pkg['send_at'] }}: <strong>{{ $pkg['quantity'] }}</strong></p>
          @endforeach
        </div>
      </div>
    </div>
  @endforeach
</div>