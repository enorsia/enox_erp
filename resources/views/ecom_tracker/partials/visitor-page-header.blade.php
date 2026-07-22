@props([
    'title',
    'rangeLabel' => 'Last 24 hours',
    'activeWindow',
    'datetimeFromValue' => '',
    'datetimeToValue' => '',
    'activeFilterCount' => 0,
    'resetUrl',
    'exportUrl',
    'resetActive' => false,
    'breadcrumbs' => [],
    'analyticsCache' => null,
    'sortOptions' => [],
    'currentSort' => null,
    'sortAction' => null,
    'backUrl' => null,
])

<header class="etd-page-header">
    @if (count($breadcrumbs) > 0)
        <div class="mb-2">
            @include('ecom_tracker.partials.breadcrumbs', ['items' => $breadcrumbs])
        </div>
    @endif

    <div class="etd-page-header-bar"
         x-data="{
            windowKey: '{{ $activeWindow }}',
            datetimeFrom: '{{ $datetimeFromValue }}',
            datetimeTo: '{{ $datetimeToValue }}',
            apply(preset) {
                if (preset === 'custom') {
                    this.windowKey = 'custom';
                    return;
                }
                const url = new URL(window.location.href);
                url.searchParams.set('window', preset);
                url.searchParams.delete('datetime_from');
                url.searchParams.delete('datetime_to');
                window.location.href = url.toString();
            },
            applyCustom() {
                const url = new URL(window.location.href);
                url.searchParams.delete('window');
                url.searchParams.set('datetime_from', this.datetimeFrom);
                url.searchParams.set('datetime_to', this.datetimeTo);
                window.location.href = url.toString();
            }
         }">
        <div class="etd-page-header-left">
            <h1 class="etd-page-title">{{ $title }}</h1>
            <span class="etd-header-sep" aria-hidden="true">·</span>
            <span class="etd-page-range">{{ $rangeLabel }}</span>
            <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
            <div class="etd-page-meta">
                @include('ecom_tracker.partials.timezone-notice')
                @include('ecom_tracker.partials.analytics-cache-notice', ['analytics_cache' => $analyticsCache])
            </div>
        </div>

        <div class="etd-page-header-right">
            <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Time window">
                @foreach (['24h' => '24 hours', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $windowKey => $windowLabel)
                    <button type="button" class="etd-segmented-btn {{ $activeWindow === $windowKey ? 'active' : '' }}" aria-label="{{ $windowLabel }}" @click="apply('{{ $windowKey }}')">{{ $windowKey }}</button>
                @endforeach
                <button type="button" class="etd-segmented-btn {{ $activeWindow === 'custom' ? 'active' : '' }}" aria-label="Custom date range" @click="apply('custom')">Custom</button>
            </div>

            <div class="etd-header-actions">
                @if ($backUrl)
                    @include('ecom_tracker.partials.header-back-button', ['url' => $backUrl])
                @endif

                @if (! empty($sortOptions) && $sortAction)
                    <form method="GET" action="{{ $sortAction }}" class="etd-header-sort">
                        @foreach (request()->except(['sort_by', 'page']) as $name => $value)
                            @if (is_array($value))
                                @foreach ($value as $item)
                                    <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <label for="visitor-sort-by" class="etd-header-sort-label">Sort by</label>
                        <select id="visitor-sort-by"
                                name="sort_by"
                                onchange="this.form.submit()"
                                class="tom-select etd-tom-select etd-header-sort-select"
                                data-placeholder="Sort by">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($currentSort ?? 'revenue_desc') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                @include('ecom_tracker.partials.header-reset-button', [
                    'url' => $resetUrl,
                    'active' => $resetActive,
                ])
                <button type="button" @click="drawerOpen = true" class="etd-header-btn etd-header-btn--icon {{ $activeFilterCount > 0 ? 'etd-header-btn--filtered' : '' }}" aria-label="Filters">
                    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                    <span class="etd-header-btn-text">Filters</span>
                    @if ($activeFilterCount > 0)
                        <span class="etd-header-btn-badge">{{ $activeFilterCount }}</span>
                    @endif
                </button>
                <a href="{{ $exportUrl }}" class="etd-header-btn etd-header-btn--primary no-underline" title="Export">
                    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l4-4m-4 4L8 11M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/></svg>
                    <span class="etd-header-btn-text">Export</span>
                </a>
            </div>
        </div>

        <div x-show="windowKey === 'custom'"
             x-collapse
             x-effect="if (windowKey === 'custom') { $nextTick(() => window.refreshEtdFilterControls?.($el)) }"
             class="etd-custom-dates etd-custom-dates--inline etd-date-range"
             data-etd-date-range>
            <input type="text"
                   x-model="datetimeFrom"
                   data-range="from"
                   data-default="{{ $datetimeFromValue }}"
                   value="{{ $datetimeFromValue }}"
                   placeholder="From date & time"
                   readonly
                   class="etd-flatpickr-datetime f-input etd-date-input etd-date-input--datetime"
                   aria-label="From date and time">
            <span class="etd-custom-dates-sep">–</span>
            <input type="text"
                   x-model="datetimeTo"
                   data-range="to"
                   data-default="{{ $datetimeToValue }}"
                   value="{{ $datetimeToValue }}"
                   placeholder="To date & time"
                   readonly
                   class="etd-flatpickr-datetime f-input etd-date-input etd-date-input--datetime"
                   aria-label="To date and time">
            <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply" @click="applyCustom()">Apply</button>
        </div>
    </div>
</header>
