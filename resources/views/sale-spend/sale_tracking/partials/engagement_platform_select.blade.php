@php
    $selectId    = $selectId ?? 'apEngagementPlatformSelect';
    $selected    = $selected ?? 'all';
    $sections    = $sections ?? [];
    $sectionBySlug = collect($sections)->keyBy('slug');
    $sectionByName = collect($sections)->keyBy('name');
    $showAll     = ($showAll ?? true) && count($sections) > 1;
@endphp

<label class="f-label sr-only" for="{{ $selectId }}">Platform</label>
<select id="{{ $selectId }}"
        class="tom-select f-input w-full text-[13px]"
        data-placeholder="Select platform">
    @if($showAll)
        <option value="all" @selected($selected === 'all')>All Platforms</option>
    @endif
    @foreach($salePlatforms ?? [] as $p)
        @php
            $slug = \Illuminate\Support\Str::slug($p['name']);
            $hasEngagement = $sectionBySlug->has($slug) || $sectionByName->has($p['name']);
        @endphp
        @if($hasEngagement)
            <option value="{{ $slug }}" @selected($selected === $slug)>{!! $p['label'] !!}</option>
        @endif
    @endforeach
</select>
