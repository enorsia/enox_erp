<div class="etd-activity-table-toolbar">
    <label class="etd-activity-sort-field">
        <span class="etd-activity-sort-label">Sort by</span>
        <select data-etd-activity-sort-select class="etd-activity-sort-select tom-select etd-tom-select">
            @foreach ($sortSelectOptions as $option)
                <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </label>
</div>
