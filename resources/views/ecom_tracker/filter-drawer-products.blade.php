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
                <p class="etd-filter-panel__subtitle">Filter by parent product code</p>
            </div>
        </div>
        <button type="button" class="etd-filter-panel__close js-ecom-dashboard-filter-close" aria-label="Close filters">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <form method="GET" action="{{ $data['filterFormAction'] ?? route('admin.ecom-tracker.dashboard.products') }}" class="etd-filter-panel__form">
        <input type="hidden" name="period" value="{{ $data['period'] }}">
        @if ($data['period'] === 'custom')
            <input type="hidden" name="date_from" value="{{ $data['dateFrom'] }}">
            <input type="hidden" name="date_to" value="{{ $data['dateTo'] }}">
        @endif
        <div class="etd-filter-panel__body etd-filter-drawer__body">
            <section class="etd-activity-filter-section">
                <p class="etd-activity-filter-section-title">Product</p>
                <label class="etd-filter-compact-field block">
                    <span class="etd-filter-compact-label">Product code</span>
                    <input type="search"
                           name="product_code"
                           value="{{ request('product_code') }}"
                           maxlength="100"
                           placeholder="Parent style code"
                           class="etd-filter-input etd-filter-input--sm w-full"
                           autocomplete="off"
                           spellcheck="false">
                </label>
            </section>
        </div>
        <div class="etd-filter-panel__footer">
            <a href="{{ $data['resetUrl'] ?? route('admin.ecom-tracker.dashboard.products') }}" class="etd-filter-panel__reset">Reset</a>
            <button type="submit" class="etd-filter-panel__apply">Apply filters</button>
        </div>
    </form>
</div>
