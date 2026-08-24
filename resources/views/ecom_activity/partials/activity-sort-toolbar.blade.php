@php
    use App\Support\EcomActivitySessionSort;

    $currentSort = EcomActivitySessionSort::effectiveSortBy(request());
    $sortOptions = EcomActivitySessionSort::sortOptions();
@endphp

<div class="etd-activity-table-toolbar">
    <label class="etd-activity-sort-field">
        <span class="etd-activity-sort-label">Sort by</span>
        <select data-etd-activity-sort-select class="etd-activity-sort-select tom-select etd-tom-select">
            @foreach ($sortOptions as $value => $label)
                <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
</div>
