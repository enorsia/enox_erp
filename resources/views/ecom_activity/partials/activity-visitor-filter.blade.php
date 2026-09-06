@php
    $filterOptionCounts = $filterOptionCounts ?? [];
    $countLabel = static function (string $value, string $label, array $counts): string {
        return isset($counts[$value]) ? "{$label} ({$counts[$value]})" : $label;
    };
    $tomSelectClass = 'tom-select etd-tom-select w-full';
@endphp

<section class="etd-activity-filter-section">
    <p class="etd-activity-filter-section-title">Visitor</p>
    <div class="etd-activity-filter-grid">
        <label class="etd-filter-compact-field">
            <span class="etd-filter-compact-label">Visitor trust</span>
            <select name="visitor_type" class="{{ $tomSelectClass }}" data-placeholder="All">
                <option value="" @selected(request('visitor_type', '') === '')>All</option>
                <option value="human" @selected(request('visitor_type') === 'human')>{{ $countLabel('human', 'Real visitors', $filterOptionCounts['visitor_type'] ?? []) }}</option>
                <option value="bot" @selected(request('visitor_type') === 'bot')>{{ $countLabel('bot', 'Automated traffic', $filterOptionCounts['visitor_type'] ?? []) }}</option>
                <option value="unclassified" @selected(request('visitor_type') === 'unclassified')>{{ $countLabel('unclassified', 'Not classified', $filterOptionCounts['visitor_type'] ?? []) }}</option>
            </select>
        </label>
    </div>
</section>
