const ACTIVITY_TABLE_LOADER_MIN_MS = 250;

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

async function fetchActivityTable(url) {
    const page = getActivityPage();
    const cleanUrl = cleanActivityUrl(url);

    if (!page) {
        window.location.href = cleanUrl.toString();

        return;
    }

    const fetchUrl = new URL(cleanUrl.toString(), window.location.origin);
    fetchUrl.searchParams.set('fragment', 'table');
    const startedAt = performance.now();

    setActivityTableLoading(true);
    await waitForNextPaint();

    try {
        const response = await fetch(fetchUrl.toString(), {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Activity table fragment failed (${response.status})`);
        }

        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const panel = doc.querySelector('.etd-panel');
        const pagination = doc.querySelector('.etd-activity-pagination');

        if (!panel || !pagination) {
            throw new Error('Activity table fragment missing expected markup');
        }

        const scrollTop = getActivityTableViewport(page)?.scrollTop ?? 0;

        page.querySelector('.etd-panel')?.replaceWith(panel);
        page.querySelector('.etd-activity-pagination')?.replaceWith(pagination);

        const nextViewport = getActivityTableViewport(page);
        if (nextViewport) {
            nextViewport.scrollTop = scrollTop;
        }

        const nextUrl = cleanUrl.toString();
        if (window.location.href !== nextUrl) {
            history.pushState({}, '', nextUrl);
        }

        if (typeof window.refreshTomSelectIn === 'function') {
            window.refreshTomSelectIn(page.querySelector('.etd-panel'));
        }

        bindActivityTableNavigation(page);
    } catch {
        window.location.href = cleanUrl.toString();
    } finally {
        const elapsed = performance.now() - startedAt;
        const remaining = ACTIVITY_TABLE_LOADER_MIN_MS - elapsed;

        if (remaining > 0) {
            await wait(remaining);
        }

        setActivityTableLoading(false);
    }
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

    window.addEventListener('popstate', () => {
        fetchActivityTable(window.location.href);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootActivityTableNavigation);
} else {
    bootActivityTableNavigation();
}
