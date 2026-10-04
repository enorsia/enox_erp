const ACTIVITY_TABLE_LOADER_MIN_MS = 250;
const ACTIVITY_LIST_RESTORE_KEY = 'etd_activity_list_restore';
const ACTIVITY_SHOW_PATH_PATTERN = /\/admin\/ecom-activity\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\/?$/i;

function getActivityPage() {
    return document.querySelector('.etd-page--activity');
}

function getActivityTableShell(page = getActivityPage()) {
    return page?.querySelector('[data-etd-activity-table-shell]') ?? null;
}

function getActivityTableViewport(page = getActivityPage()) {
    return page?.querySelector('[data-etd-activity-table-viewport]')
        ?? page?.querySelector('.etd-table-scroll--activity')
        ?? null;
}

function activityTableLoaderMarkup() {
    return `
        <svg class="etd-activity-table-loading__spinner" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="etd-activity-table-loading__track" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <path class="etd-activity-table-loading__head" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="etd-activity-table-loading__label">Loading sessions…</span>
    `;
}

function ensureActivityTableLoader(shell) {
    let overlay = shell.querySelector('[data-etd-activity-table-loading]');

    if (overlay) {
        return overlay;
    }

    overlay = document.createElement('div');
    overlay.className = 'etd-activity-table-loading';
    overlay.setAttribute('data-etd-activity-table-loading', '');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = activityTableLoaderMarkup();
    shell.prepend(overlay);

    return overlay;
}

function waitForNextPaint() {
    return new Promise((resolve) => {
        requestAnimationFrame(() => {
            requestAnimationFrame(resolve);
        });
    });
}

function wait(ms) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

function setActivityTableLoading(isLoading) {
    const page = getActivityPage();
    const shell = getActivityTableShell(page);
    const viewport = getActivityTableViewport(page);

    if (!shell) {
        return;
    }

    const overlay = ensureActivityTableLoader(shell);
    const block = page?.querySelector('[data-etd-activity-table-block]');

    shell.classList.toggle('is-loading', isLoading);
    block?.classList.toggle('is-loading', isLoading);
    shell.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    overlay.setAttribute('aria-hidden', isLoading ? 'false' : 'true');

    if (viewport) {
        viewport.toggleAttribute('inert', isLoading);
    }

    page?.querySelectorAll('[data-etd-activity-sort], [data-etd-activity-sort-select]').forEach((control) => {
        if (control.tagName === 'A') {
            control.setAttribute('aria-disabled', isLoading ? 'true' : 'false');
            control.tabIndex = isLoading ? -1 : 0;

            return;
        }

        control.disabled = isLoading;

        if (control.tomselect) {
            if (isLoading) {
                control.tomselect.disable();
            } else {
                control.tomselect.enable();
            }
        }
    });
}

function isActivityTableLoading() {
    return getActivityTableShell()?.classList.contains('is-loading') ?? false;
}

function cleanActivityUrl(url) {
    const parsed = new URL(url, window.location.origin);
    parsed.searchParams.delete('fragment');

    return parsed;
}

function canonicalActivityListUrl(url) {
    const parsed = cleanActivityUrl(url);

    if (!isActivityIndexUrl(parsed.toString())) {
        return parsed.toString();
    }

    const params = new URLSearchParams(parsed.search);
    const sorted = new URLSearchParams();

    [...params.keys()].sort().forEach((key) => {
        const values = params.getAll(key);

        values.forEach((value) => {
            sorted.append(key, value);
        });
    });

    const query = sorted.toString();

    return query === '' ? parsed.origin + parsed.pathname : `${parsed.origin}${parsed.pathname}?${query}`;
}

function isActivityIndexUrl(url) {
    const parsed = cleanActivityUrl(url);
    const path = parsed.pathname.replace(/\/$/, '');

    return path === '/admin/ecom-activity';
}

function captureActivityListRestoreState() {
    const page = getActivityPage();

    if (!page) {
        return;
    }

    const payload = {
        url: canonicalActivityListUrl(window.location.href),
        scrollTop: getActivityTableViewport(page)?.scrollTop ?? 0,
        windowScrollY: window.scrollY,
    };

    try {
        sessionStorage.setItem(ACTIVITY_LIST_RESTORE_KEY, JSON.stringify(payload));
    } catch {
        // ignore quota / private mode
    }
}

function restoreActivityListRestoreState() {
    const page = getActivityPage();

    if (!page) {
        return;
    }

    let payload = null;

    try {
        const raw = sessionStorage.getItem(ACTIVITY_LIST_RESTORE_KEY);

        if (!raw) {
            return;
        }

        sessionStorage.removeItem(ACTIVITY_LIST_RESTORE_KEY);
        payload = JSON.parse(raw);
    } catch {
        return;
    }

    if (!payload?.url) {
        return;
    }

    const currentUrl = canonicalActivityListUrl(window.location.href);

    if (currentUrl !== payload.url || !isActivityIndexUrl(payload.url)) {
        return;
    }

    const applyScroll = () => {
        const viewport = getActivityTableViewport(page);

        if (viewport && Number.isFinite(payload.scrollTop)) {
            viewport.scrollTop = payload.scrollTop;
        }

        if (Number.isFinite(payload.windowScrollY)) {
            window.scrollTo(0, payload.windowScrollY);
        }
    };

    requestAnimationFrame(() => {
        requestAnimationFrame(applyScroll);
    });
}

function bindActivityShowLinkCapture(page) {
    if (page._etdActivityShowCaptureBound) {
        return;
    }

    page._etdActivityShowCaptureBound = true;

    page.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link) {
            return;
        }

        let parsed;

        try {
            parsed = new URL(link.href, window.location.origin);
        } catch {
            return;
        }

        if (!ACTIVITY_SHOW_PATH_PATTERN.test(parsed.pathname)) {
            return;
        }

        captureActivityListRestoreState();
    }, true);
}

function applySortSelection(value) {
    if (isActivityTableLoading()) {
        return;
    }

    const url = cleanActivityUrl(window.location.href);

    url.searchParams.delete('page');

    if (!value || value === 'funnel_stage') {
        url.searchParams.delete('sort_by');
        url.searchParams.delete('sort_dir');
    } else {
        url.searchParams.set('sort_by', value);
        url.searchParams.set('sort_dir', 'desc');
    }

    fetchActivityTable(url.toString());
}

function onSortSelectChange(event) {
    applySortSelection(event.target.value);
}

function bindSortSelect(select) {
    if (!select || select._etdActivitySortBound) {
        return;
    }

    select._etdActivitySortBound = true;

    if (select.tomselect) {
        select.tomselect.on('change', applySortSelection);

        return;
    }

    select.addEventListener('change', onSortSelectChange);

    const waitForTomSelect = window.setInterval(() => {
        if (!select.tomselect) {
            return;
        }

        window.clearInterval(waitForTomSelect);
        select.removeEventListener('change', onSortSelectChange);
        select.tomselect.on('change', applySortSelection);
    }, 50);

    window.setTimeout(() => window.clearInterval(waitForTomSelect), 3000);
}

function bindActivityTableNavigation(page) {
    page.querySelectorAll('[data-etd-activity-sort]').forEach((link) => {
        link.addEventListener('click', onSortLinkClick);
    });

    page.querySelectorAll('.etd-activity-pagination a[href]').forEach((link) => {
        link.addEventListener('click', onPaginationClick);
    });

    bindSortSelect(page.querySelector('[data-etd-activity-sort-select]'));
}

function fetchActivityTable(url) {
    window.location.href = cleanActivityUrl(url).toString();
}

const ETD_FILTER_PANEL_CLOSED_CLASS = 'etd-filter-panel--closed';

function isActivityFilterDrawerOpen(drawer) {
    return drawer && !drawer.classList.contains(ETD_FILTER_PANEL_CLOSED_CLASS);
}

function initActivityFilterDrawer(page) {
    const backdrop = document.getElementById('ecom-activity-filter-backdrop');
    const drawer = document.getElementById('ecom-activity-filter-drawer');
    const openButton = page.querySelector('#ecom-activity-filter-open');

    if (!backdrop || !drawer) {
        return;
    }

    const setOpen = (isOpen) => {
        backdrop.classList.toggle(ETD_FILTER_PANEL_CLOSED_CLASS, !isOpen);
        drawer.classList.toggle(ETD_FILTER_PANEL_CLOSED_CLASS, !isOpen);
        backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        document.body.classList.toggle('etd-filter-panel-open', isOpen);
        openButton?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (isOpen && typeof window.refreshEtdFilterControls === 'function') {
            window.refreshEtdFilterControls(drawer);
        }
    };

    const open = () => setOpen(true);
    const close = () => setOpen(false);
    const toggle = () => setOpen(!isActivityFilterDrawerOpen(drawer));

    page.querySelectorAll('.js-ecom-activity-filter-open').forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            toggle();
        });
    });

    page.querySelectorAll('.js-ecom-activity-filter-close').forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            close();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isActivityFilterDrawerOpen(drawer)) {
            close();
        }
    });

    window.closeFilterDrawer = close;
}

const ETD_CUSTOM_DATES_CLOSED_CLASS = 'etd-custom-dates--closed';
const ETD_DRAWER_CUSTOM_CLOSED_CLASS = 'etd-filter-period__custom--closed';

function syncActivityCustomFlatpickr(customPanel, drawerCustom, from, to) {
    if (!from || !to) {
        return;
    }

    const rangeDisplay = customPanel?.querySelector('.etd-flatpickr-date-range');
    const fpRange = rangeDisplay?._etdFlatpickr;

    if (fpRange) {
        fpRange.setDate([from, to], false);
    }

    const drawerFrom = drawerCustom?.querySelector('[name="date_from"]');
    const drawerTo = drawerCustom?.querySelector('[name="date_to"]');

    if (drawerFrom?._etdFlatpickr) {
        drawerFrom._etdFlatpickr.setDate(from, false);
    }

    if (drawerTo?._etdFlatpickr) {
        drawerTo._etdFlatpickr.setDate(to, false);
    }
}

function initActivityPeriodControls(page) {
    const headerNav = page.querySelector('.etd-header-period-nav');
    const customToggle = headerNav?.querySelector('.js-ecom-activity-period-custom-toggle');
    const customPanel = document.getElementById('ecom-activity-header-custom-dates');
    const fromInput = document.getElementById('ecom-activity-header-date-from');
    const toInput = document.getElementById('ecom-activity-header-date-to');
    const applyBtn = customPanel?.querySelector('.js-ecom-activity-header-custom-apply');
    const presetLinks = headerNav?.querySelectorAll('.etd-segmented .etd-segmented-btn[href]');
    const drawerCustom = document.getElementById('ecom-activity-drawer-custom-dates');
    const periodInput = document.getElementById('ecom-activity-filter-period');

    const showHeaderCustom = (show) => {
        if (!customPanel) {
            return;
        }

        customPanel.classList.toggle(ETD_CUSTOM_DATES_CLOSED_CLASS, !show);
        customToggle?.classList.toggle('active', show);

        if (show) {
            presetLinks?.forEach((link) => link.classList.remove('active'));

            if (typeof window.refreshEtdFilterControls === 'function') {
                window.refreshEtdFilterControls(customPanel);
            }

            syncActivityCustomFlatpickr(
                customPanel,
                drawerCustom,
                fromInput?.value ?? '',
                toInput?.value ?? '',
            );
        }
    };

    customToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        const isOpen = customPanel && !customPanel.classList.contains(ETD_CUSTOM_DATES_CLOSED_CLASS);
        showHeaderCustom(!isOpen);
    });

    applyBtn?.addEventListener('click', () => {
        const url = new URL(window.location.href);

        url.searchParams.set('period', 'custom');

        const from = fromInput?.value?.trim() ?? '';
        const to = toInput?.value?.trim() ?? '';

        if (from) {
            url.searchParams.set('date_from', from);
        } else {
            url.searchParams.delete('date_from');
        }

        if (to) {
            url.searchParams.set('date_to', to);
        } else {
            url.searchParams.delete('date_to');
        }

        window.location.href = url.toString();
    });

    page.querySelector('.js-ecom-activity-drawer-custom-preset')?.addEventListener('click', () => {
        if (periodInput) {
            periodInput.value = 'custom';
        }

        drawerCustom?.classList.remove(ETD_DRAWER_CUSTOM_CLOSED_CLASS);
        drawerCustom?.querySelectorAll('[name="date_from"], [name="date_to"]').forEach((input) => {
            input.disabled = false;
        });

        if (typeof window.syncEtdFlatpickrEnabled === 'function') {
            window.syncEtdFlatpickrEnabled(drawerCustom, true);
        }

        if (typeof window.refreshEtdFilterControls === 'function') {
            window.refreshEtdFilterControls(drawerCustom);
        }

        syncActivityCustomFlatpickr(
            customPanel,
            drawerCustom,
            drawerCustom?.querySelector('[name="date_from"]')?.value ?? '',
            drawerCustom?.querySelector('[name="date_to"]')?.value ?? '',
        );
    });

    const from = fromInput?.value?.trim() ?? '';
    const to = toInput?.value?.trim() ?? '';

    if (!from || !to) {
        return;
    }

    if (customPanel && !customPanel.classList.contains(ETD_CUSTOM_DATES_CLOSED_CLASS)) {
        if (typeof window.refreshEtdFilterControls === 'function') {
            window.refreshEtdFilterControls(customPanel);
        }
    }

    if (drawerCustom && !drawerCustom.classList.contains(ETD_DRAWER_CUSTOM_CLOSED_CLASS)) {
        if (typeof window.refreshEtdFilterControls === 'function') {
            window.refreshEtdFilterControls(drawerCustom);
        }
    }

    syncActivityCustomFlatpickr(customPanel, drawerCustom, from, to);
}

function onSortLinkClick(event) {
    if (isActivityTableLoading()) {
        event.preventDefault();

        return;
    }

    event.preventDefault();
    fetchActivityTable(event.currentTarget.href);
}

function onPaginationClick(event) {
    if (isActivityTableLoading()) {
        event.preventDefault();

        return;
    }

    event.preventDefault();
    fetchActivityTable(event.currentTarget.href);
}

function bootActivityTableNavigation() {
    const page = getActivityPage();

    if (!page) {
        return;
    }

    const cleanUrl = cleanActivityUrl(window.location.href);
    if (cleanUrl.toString() !== window.location.href) {
        history.replaceState({}, '', cleanUrl.toString());
    }

    bindActivityTableNavigation(page);
    bindActivityShowLinkCapture(page);
    restoreActivityListRestoreState();
    initActivityFilterDrawer(page);
    initActivityPeriodControls(page);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootActivityTableNavigation);
} else {
    bootActivityTableNavigation();
}
