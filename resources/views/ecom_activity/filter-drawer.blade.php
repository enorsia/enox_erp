<div id="ecom-activity-filter-backdrop" class="etd-filter-panel__backdrop etd-filter-panel--closed js-ecom-activity-filter-close" aria-hidden="true"></div>

<div id="ecom-activity-filter-drawer"
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
                <p class="etd-filter-panel__subtitle">Refine what you see on this page</p>
            </div>
        </div>
        <button type="button" class="etd-filter-panel__close js-ecom-activity-filter-close" aria-label="Close filters">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <form method="GET" action="{{ route('admin.ecom-activity.index') }}" class="etd-filter-panel__form">
        <div class="etd-filter-panel__body etd-filter-drawer__body">
            <section class="etd-filter-period etd-filter-period--drawer-mobile etd-activity-filter-section etd-activity-filter-section--full">
                <p class="etd-activity-filter-section-title">Date range</p>
                <div class="etd-filter-period__nav">
                    <button type="button" class="etd-filter-period__nav-btn" aria-label="Previous day"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></button>
                    <span class="etd-filter-period__nav-label">{{ match ($data['period']) { 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom', default => 'Today' } }}</span>
                    <span class="etd-filter-period__nav-btn is-disabled" aria-disabled="true"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></span>
                </div>
                <input type="hidden" name="period" id="ecom-activity-filter-period" value="{{ $data['period'] }}">
                <div class="etd-filter-period__presets" role="group" aria-label="Date presets">
                    <a href="{{ route('admin.ecom-activity.index', ['period' => '24h']) }}" class="etd-filter-period__preset no-underline {{ $data['period'] === '24h' ? 'is-active' : '' }}">Today</a>
                    <a href="{{ route('admin.ecom-activity.index', ['period' => 'yesterday']) }}" class="etd-filter-period__preset no-underline {{ $data['period'] === 'yesterday' ? 'is-active' : '' }}">Yesterday</a>
                    <a href="{{ route('admin.ecom-activity.index', ['period' => '7d']) }}" class="etd-filter-period__preset no-underline {{ $data['period'] === '7d' ? 'is-active' : '' }}">7 days</a>
                    <a href="{{ route('admin.ecom-activity.index', ['period' => '30d']) }}" class="etd-filter-period__preset no-underline {{ $data['period'] === '30d' ? 'is-active' : '' }}">30 days</a>
                    <button type="button" class="etd-filter-period__preset js-ecom-activity-drawer-custom-preset {{ $data['period'] === 'custom' ? 'is-active' : '' }}">Custom range</button>
                </div>
                <div id="ecom-activity-drawer-custom-dates"
                     class="etd-date-range etd-filter-period__custom etd-activity-filter-grid @if($data['period'] !== 'custom') etd-filter-period__custom--closed @endif"
                     data-etd-date-range>
                    <label class="etd-filter-compact-field" for="activity-filter-date-from">
                        <span class="etd-filter-compact-label">From</span>
                        <input type="text"
                               id="activity-filter-date-from"
                               name="date_from"
                               value="{{ $data['dateFrom'] }}"
                               data-range="from"
                               data-default="{{ $data['dateFrom'] }}"
                               placeholder="Select date"
                               readonly
                               @disabled($data['period'] !== 'custom')
                               class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
                    </label>
                    <label class="etd-filter-compact-field" for="activity-filter-date-to">
                        <span class="etd-filter-compact-label">To</span>
                        <input type="text"
                               id="activity-filter-date-to"
                               name="date_to"
                               value="{{ $data['dateTo'] }}"
                               data-range="to"
                               data-default="{{ $data['dateTo'] }}"
                               placeholder="Select date"
                               readonly
                               @disabled($data['period'] !== 'custom')
                               class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
                    </label>
                </div>
            </section>
            <hr class="etd-filter-divider"/>
            <div class="etd-activity-filter-sections">
                <section class="etd-activity-filter-section etd-activity-filter-section--full">
                    <p class="etd-activity-filter-section-title">Search</p>
                    <label class="etd-filter-compact-field">
                        <span class="etd-filter-compact-label">Keyword</span>
                        <input type="text" name="search" value="" placeholder="Session, visitor, email, phone, IP, product, SKU, category, department…" class="etd-filter-input etd-filter-input--sm w-full">
                    </label>
                </section>
                <section class="etd-activity-filter-section">
                    <p class="etd-activity-filter-section-title">Funnel</p>
                    <div class="etd-activity-filter-grid">
                        <label class="etd-filter-compact-field">
                            <span class="etd-filter-compact-label">Funnel stage</span>
                            <select name="funnel[]" multiple class="tom-select etd-tom-select w-full" data-placeholder="All">
                                <option value="cart_abandonment">Cart abandoned</option>
                                <option value="begin_checkout_abandonment">Begin checkout abandoned</option>
                                <option value="proceed_checkout_abandonment">Proceed checkout abandoned</option>
                                <option value="payment_success">Payment success</option>
                            </select>
                        </label>
                        <label class="etd-filter-compact-field">
                            <span class="etd-filter-compact-label">Has order</span>
                            <select name="has_order" class="tom-select etd-tom-select w-full" data-placeholder="All">
                                <option value="" selected>All</option>
                                <option value="1">With order</option>
                                <option value="0">No order</option>
                            </select>
                        </label>
                    </div>
                </section>
                <section class="etd-activity-filter-section">
                    <p class="etd-activity-filter-section-title">Session</p>
                    <div class="etd-activity-filter-grid">
                        <label class="etd-filter-compact-field">
                            <span class="etd-filter-compact-label">Device</span>
                            <select name="device_type[]" multiple class="tom-select etd-tom-select w-full" data-placeholder="All"></select>
                        </label>
                        <label class="etd-filter-compact-field">
                            <span class="etd-filter-compact-label">Logged in</span>
                            <select name="logged_in" class="tom-select etd-tom-select w-full" data-placeholder="All">
                                <option value="" selected>All</option>
                                <option value="1">Logged in</option>
                                <option value="0">Guest</option>
                            </select>
                        </label>
                        <label class="etd-filter-compact-field">
                            <span class="etd-filter-compact-label">Duration</span>
                            <select name="duration_bucket[]" multiple class="tom-select etd-tom-select w-full" data-placeholder="All">
                                <option value="under_1m">Under 1 min</option>
                                <option value="1_5m">1–5 min</option>
                                <option value="5_15m">5–15 min</option>
                                <option value="15_60m">15–60 min</option>
                                <option value="over_60m">Over 60 min</option>
                            </select>
                        </label>
                    </div>
                </section>
                <section class="etd-activity-filter-section">
                    <p class="etd-activity-filter-section-title">Traffic source</p>
                    <p class="etd-activity-filter-section-hint text-[11px] text-slate-500 dark:text-slate-400 mb-2 leading-snug">Counts sessions by last marketing touch within 7 days for the same visitor, then this visit.</p>
                    <div class="etd-activity-filter-grid">
                        <label class="etd-filter-compact-field">
                            <span class="etd-filter-compact-label">UTM source</span>
                            <select name="utm_source[]" multiple class="tom-select etd-tom-select w-full" data-placeholder="All"></select>
                        </label>
                        <label class="etd-filter-compact-field">
                            <span class="etd-filter-compact-label">UTM medium</span>
                            <select name="utm_medium[]" multiple class="tom-select etd-tom-select w-full" data-placeholder="All"></select>
                        </label>
                    </div>
                </section>
                <section class="etd-activity-filter-section">
                    <p class="etd-kpi-section-label mb-2">Product / category</p>
                    <div class="etd-product-filters-compact etd-activity-filter-grid" data-etd-department-category data-etd-category-catalog="{}">
                        <label class="etd-filter-compact-field" for="catalog-filter-department">
                            <span class="etd-filter-compact-label">Department</span>
                            <select id="catalog-filter-department" name="department" class="tom-select etd-tom-select w-full" data-placeholder="All"><option value="">All</option></select>
                        </label>
                        <label class="etd-filter-compact-field" for="catalog-filter-category">
                            <span class="etd-filter-compact-label">Category</span>
                            <select id="catalog-filter-category" name="category[]" multiple class="tom-select etd-tom-select w-full" data-placeholder="All"></select>
                        </label>
                    </div>
                </section>
            </div>
        </div>
        <div class="etd-filter-panel__footer">
            <a href="{{ route('admin.ecom-activity.index') }}" class="etd-filter-panel__reset">Reset</a>
            <button type="submit" class="etd-filter-panel__apply">Apply filters</button>
        </div>
    </form>
</div>
