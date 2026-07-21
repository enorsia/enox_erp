@props([
    'filterOptions' => ['categories' => [], 'colors' => [], 'sizes' => []],
    'eventScenarioOptions' => [],
])

@php
    $selectedScenario = request('event_scenario', '');
@endphp

<div>
    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Product search</p>
    <input type="search"
           name="search"
           value="{{ request('search') }}"
           placeholder="Product name or SKU"
           class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
</div>

<div>
    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Category</p>
    <select name="category" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
        <option value="">All categories</option>
        @foreach ($filterOptions['categories'] ?? [] as $category)
            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
        @endforeach
    </select>
</div>

<div class="grid grid-cols-2 gap-2">
    <div>
        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Color</p>
        <select name="color" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
            <option value="">All colors</option>
            @foreach ($filterOptions['colors'] ?? [] as $color)
                <option value="{{ $color }}" @selected(request('color') === $color)>{{ $color }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Size</p>
        <select name="size" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700">
            <option value="">All sizes</option>
            @foreach ($filterOptions['sizes'] ?? [] as $size)
                <option value="{{ $size }}" @selected(request('size') === $size)>{{ $size }}</option>
            @endforeach
        </select>
    </div>
</div>

<div>
    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Action events</p>
    <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-2">Show products that have at least one of these events. Combine multiple for stricter matching.</p>
    <div class="etd-filter-event-grid">
        <label class="etd-filter-event-card">
            <input type="checkbox" name="has_views" value="1" @checked(request('has_views') === '1')>
            <span class="etd-filter-event-card__body">
                <span class="etd-filter-event-card__title">Product views</span>
                <span class="etd-filter-event-card__desc">Has view activity</span>
            </span>
        </label>
        <label class="etd-filter-event-card">
            <input type="checkbox" name="has_adds" value="1" @checked(request('has_adds') === '1')>
            <span class="etd-filter-event-card__body">
                <span class="etd-filter-event-card__title">Add to cart</span>
                <span class="etd-filter-event-card__desc">Has cart adds</span>
            </span>
        </label>
        <label class="etd-filter-event-card">
            <input type="checkbox" name="has_purchases" value="1" @checked(request('has_purchases') === '1')>
            <span class="etd-filter-event-card__body">
                <span class="etd-filter-event-card__title">Purchases</span>
                <span class="etd-filter-event-card__desc">Has completed sales</span>
            </span>
        </label>
    </div>
</div>

<div x-data="{ scenario: @js($selectedScenario) }">
    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Event combinations</p>
    <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-2">Ready-made scenarios across view, add, and purchase events.</p>
    <div class="space-y-2">
        @foreach ($eventScenarioOptions as $value => $label)
            <label class="etd-filter-scenario-card"
                   :class="{ 'is-active': scenario === @js($value) }">
                <input type="radio"
                       name="event_scenario"
                       value="{{ $value }}"
                       x-model="scenario"
                       @checked($selectedScenario === $value)>
                <span class="etd-filter-scenario-card__label">{{ $label }}</span>
            </label>
        @endforeach
    </div>
</div>
