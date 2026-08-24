function cleanActivityUrl(url) {
    const parsed = new URL(url, window.location.origin);
    parsed.searchParams.delete('fragment');

    return parsed;
}

function applySortSelection(value) {
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
    const page = document.querySelector('.etd-page--activity');
    const cleanUrl = cleanActivityUrl(url);

    if (!page) {
        window.location.href = cleanUrl.toString();

        return;
    }

    const fetchUrl = new URL(cleanUrl.toString(), window.location.origin);
    fetchUrl.searchParams.set('fragment', 'table');

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

        page.querySelector('.etd-panel')?.replaceWith(panel);
        page.querySelector('.etd-activity-pagination')?.replaceWith(pagination);

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
    }
}

function onSortLinkClick(event) {
    event.preventDefault();
    fetchActivityTable(event.currentTarget.href);
}

function onPaginationClick(event) {
    event.preventDefault();
    fetchActivityTable(event.currentTarget.href);
}

function bootActivityTableNavigation() {
    const page = document.querySelector('.etd-page--activity');

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
