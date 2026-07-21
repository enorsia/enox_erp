@props([
    'title',
    'subtitle' => null,
    'defaultBackRoute',
    'exportUrl' => null,
    'activeFilterCount' => 0,
    'breadcrumbs' => [],
    'sortOptions' => [],
    'currentSort' => null,
    'sortAction' => null,
    'compact' => false,
])

<div @class(['etd-topbar', 'etd-topbar--compact' => $compact])>
    <div @class(['etd-topbar-intro', 'etd-topbar-intro--compact' => $compact])>
        @if (count($breadcrumbs) > 0)
            <div @class(['mb-2', 'mb-1' => $compact])>
                @include('ecom_tracker.partials.breadcrumbs', ['items' => $breadcrumbs])
            </div>
        @endif
        @if ($compact)
            <div class="etd-topbar-title-row">
                <h1 class="etd-page-title etd-page-title--compact">{{ $title }}</h1>
                @if ($subtitle)
                    <span class="etd-topbar-sep" aria-hidden="true">·</span>
                    <span class="etd-page-desc-inline">{{ $subtitle }}</span>
                @endif
            </div>
        @else
            <h1 class="etd-page-title">{{ $title }}</h1>
            @if ($subtitle)
                <p class="etd-page-desc">{{ $subtitle }}</p>
            @endif
            @include('ecom_tracker.partials.timezone-notice')
        @endif
    </div>

    <div class="etd-topbar-actions">
        <a href="{{ request('back') ? urldecode(request('back')) : route($defaultBackRoute) }}"
           @class([
               'etd-topbar-btn',
               'etd-topbar-btn--ghost',
               'etd-topbar-btn--sm' => $compact,
           ])>
            ← Back
        </a>
        @if ($exportUrl)
            <a href="{{ $exportUrl }}" class="etd-topbar-btn etd-topbar-btn--export {{ $compact ? 'etd-topbar-btn--sm' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </a>
        @endif
        @if (! empty($sortOptions) && $sortAction)
            <form method="GET" action="{{ $sortAction }}" class="inline-flex items-center gap-2">
                @foreach (request()->except(['sort_by', 'page']) as $name => $value)
                    @if (is_array($value))
                        @foreach ($value as $item)
                            <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endif
                @endforeach
                <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 whitespace-nowrap">Sort by</span>
                <select id="visitor-sort-by"
                        name="sort_by"
                        onchange="this.form.submit()"
                        class="tom-select etd-tom-select min-w-[200px] max-w-[240px]"
                        data-placeholder="Sort by">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($currentSort ?? 'revenue_desc') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <button type="button"
                @click="drawerOpen = true"
                @class([
                    'etd-topbar-btn',
                    'etd-topbar-btn--sm' => $compact,
                    $activeFilterCount > 0 ? 'etd-topbar-btn--active' : 'etd-topbar-btn--ghost',
                ])>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
            Filters
            @if ($activeFilterCount > 0)
                <span class="etd-topbar-btn__badge">{{ $activeFilterCount }}</span>
            @endif
        </button>
    </div>
</div>
