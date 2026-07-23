@php
    $selectId    = $selectId ?? 'apEngagementPlatformSelect';
    $selected    = $selected ?? 'all';
    $sections    = $sections ?? [];
    $showAll     = ($showAll ?? true) && count($sections) > 1;

    $sectionLabel = function (array $section): string {
        $name = $section['name'] ?? '—';

        if (!empty($section['parent_name'])) {
            return "{$name} · {$section['parent_name']}";
        }

        return $name;
    };
@endphp

<label class="f-label sr-only" for="{{ $selectId }}">Platform</label>
<select id="{{ $selectId }}"
        class="tom-select f-input w-full text-[13px]"
        data-placeholder="Select platform"
        data-dropdown-parent="body">
    @if($showAll)
        <option value="all" @selected($selected === 'all')>All Platforms</option>
    @endif
    @foreach($sections as $section)
        <option value="{{ $section['slug'] }}" @selected($selected === $section['slug'])>{{ $sectionLabel($section) }}</option>
    @endforeach
</select>
