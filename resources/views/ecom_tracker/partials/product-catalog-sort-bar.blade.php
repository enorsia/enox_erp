@props([
    'sortGroups' => [],
    'presets' => [],
    'currentSort' => 'top_revenue',
    'currentHint' => '',
    'sortAction',
])

@php
    $queryExceptSort = request()->except(['sort_by', 'page']);
@endphp

<div class="etd-catalog-sort-bar">
    <div class="etd-catalog-sort-bar__head">
        <div class="etd-catalog-sort-bar__title-wrap">
            <span class="etd-catalog-sort-bar__label">Sort products</span>
            @if ($currentHint)
                <span class="etd-catalog-sort-bar__hint">{{ $currentHint }}</span>
            @endif
        </div>

        <form method="GET" action="{{ $sortAction }}" class="etd-catalog-sort-bar__select-form">
            @foreach ($queryExceptSort as $name => $value)
                @if (is_array($value))
                    @foreach ($value as $item)
                        <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach
            <label class="sr-only" for="product-catalog-sort">All sort options</label>
            <select id="product-catalog-sort"
                    name="sort_by"
                    onchange="this.form.submit()"
                    class="etd-catalog-sort-bar__select">
                @foreach ($sortGroups as $group)
                    <optgroup label="{{ $group['label'] }}">
                        @foreach ($group['options'] as $value => $option)
                            <option value="{{ $value }}" @selected($currentSort === $value)>{{ $option['label'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </form>
    </div>

    <div class="etd-catalog-sort-bar__presets" role="list" aria-label="Quick sort presets">
        @foreach ($presets as $preset)
            @php
                $isActive = $currentSort === $preset['key'];
                $presetUrl = route('admin.ecom-tracker.dashboard.details', array_merge(
                    ['section' => 'products'],
                    $queryExceptSort,
                    ['sort_by' => $preset['key']],
                ));
            @endphp
            <a href="{{ $presetUrl }}"
               role="listitem"
               title="{{ $preset['hint'] }}"
               @class([
                   'etd-catalog-sort-pill',
                   'is-active' => $isActive,
               ])>
                {{ $preset['label'] }}
            </a>
        @endforeach
    </div>

    <div class="etd-catalog-sort-bar__insights" role="list" aria-label="Insight sort presets">
        @foreach ($sortGroups as $group)
            @if ($group['label'] !== 'Insights')
                @continue
            @endif
            @foreach ($group['options'] as $value => $option)
                @php
                    $isActive = $currentSort === $value;
                    $insightUrl = route('admin.ecom-tracker.dashboard.details', array_merge(
                        ['section' => 'products'],
                        $queryExceptSort,
                        ['sort_by' => $value],
                    ));
                @endphp
                <a href="{{ $insightUrl }}"
                   role="listitem"
                   title="{{ $option['hint'] }}"
                   @class([
                       'etd-catalog-sort-chip',
                       'is-active' => $isActive,
                   ])>
                    {{ $option['label'] }}
                </a>
            @endforeach
        @endforeach
    </div>
</div>
