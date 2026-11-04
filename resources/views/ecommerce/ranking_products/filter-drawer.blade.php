@php
    $filterDrawerHasFilters = trim((string) ($search ?? '')) !== ''
        || ($contextKey ?? '') !== '';
    $drawerResetUrl = $resetUrl ?? route('admin.ecommerce.ranking-products.index');
@endphp

<div id="ecom-dashboard-filter-backdrop" class="etd-filter-panel__backdrop etd-filter-panel--closed js-ecom-dashboard-filter-close" aria-hidden="true"></div>

<div id="ecom-dashboard-filter-drawer"
     role="dialog" aria-modal="true" aria-label="Filters" aria-hidden="true"
     class="etd-filter-panel etd-filter-drawer etd-filter-panel--wide etd-filter-drawer--wide etd-filter-panel--closed">
    <div class="etd-filter-panel__handle" aria-hidden="true"></div>
    <div class="etd-filter-panel__header">
        <div class="etd-filter-panel__title-wrap">
            <span class="etd-filter-panel__icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
            </span>
            <div>
                <p class="etd-filter-panel__title">Filters</p>
                <p class="etd-filter-panel__subtitle">Listing context and product code</p>
            </div>
        </div>
        <div class="etd-filter-panel__header-actions">
            <button type="button" class="etd-filter-panel__close js-ecom-dashboard-filter-close" aria-label="Close filters">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
        <form method="GET" action="{{ $filterFormAction ?? route('admin.ecommerce.ranking-products.index') }}" class="etd-filter-panel__form" id="ranking-products-filter-drawer-form">
        <div class="etd-filter-panel__body etd-filter-drawer__body">
            <section class="etd-activity-filter-section etd-activity-filter-section--full">
                <p class="etd-activity-filter-section-title">Listing context</p>
                <label class="etd-filter-compact-field block" for="ranking-filter-context">
                    <span class="etd-filter-compact-label">Where should this ranking apply?</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block mb-1.5">Global, department, category, or micro-category listing.</span>
                    <select id="ranking-filter-context" name="context" class="w-full text-[13px] ranking-context-ts" data-placeholder="All listing contexts">
                        <option value="" @selected(($contextKey ?? '') === '')>All listing contexts</option>
                        @foreach ($listingContexts ?? [] as $ctx)
                            <option value="{{ $ctx['key'] }}" @selected(($contextKey ?? '') === $ctx['key'])>{{ $ctx['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            </section>
            <section class="etd-activity-filter-section etd-activity-filter-section--full">
                <p class="etd-activity-filter-section-title">Search</p>
                <label class="etd-filter-compact-field block" for="ranking-filter-search">
                    <span class="etd-filter-compact-label">Product code</span>
                    <input type="search"
                           id="ranking-filter-search"
                           name="search"
                           value="{{ $search ?? '' }}"
                           maxlength="200"
                           placeholder="Contains…"
                           class="f-input w-full"
                           autocomplete="off">
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 block">Partial match on product code (server-side).</span>
                </label>
            </section>
        </div>
        <div class="etd-filter-panel__footer">
            <a href="{{ $drawerResetUrl }}" class="etd-filter-panel__reset">Reset</a>
            <button type="submit" class="etd-filter-panel__apply">Apply filters</button>
        </div>
    </form>
</div>
