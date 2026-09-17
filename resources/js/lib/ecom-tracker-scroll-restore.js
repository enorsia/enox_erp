const SCROLL_HASH = /^etd-y=(\d+)$/;

function pageScrollY() {
    const main = document.querySelector('main');

    return Math.max(0, Math.round(main ? main.scrollTop : window.scrollY));
}

function applyPageScrollY(y) {
    const main = document.querySelector('main');

    if (main) {
        main.scrollTop = y;
        return;
    }

    window.scrollTo(0, y);
}

function stampActivityBackScroll(anchor) {
    const href = anchor.getAttribute('href');

    if (!href) {
        return;
    }

    let url;

    try {
        url = new URL(href, window.location.origin);
    } catch {
        return;
    }

    if (!url.pathname.includes('/admin/ecom-activity')) {
        return;
    }

    const back = url.searchParams.get('back');

    if (!back) {
        return;
    }

    let backUrl;

    try {
        backUrl = new URL(back, window.location.origin);
    } catch {
        return;
    }

    backUrl.hash = `etd-y=${pageScrollY()}`;
    url.searchParams.set('back', backUrl.toString());
    anchor.setAttribute('href', url.toString());
}

function restorePageScroll({ stabilize = false } = {}) {
    const hash = decodeURIComponent((window.location.hash || '').replace(/^#/, ''));
    const hashMatch = hash.match(SCROLL_HASH);
    const storageKey = `admin_scroll_${window.location.pathname}`;
    let y = hashMatch ? parseInt(hashMatch[1], 10) : Number.NaN;

    if (Number.isNaN(y)) {
        const saved = sessionStorage.getItem(storageKey);
        y = saved !== null && saved !== '' ? parseInt(saved, 10) : Number.NaN;
    }

    sessionStorage.removeItem(storageKey);

    if (hashMatch) {
        history.replaceState(null, '', `${window.location.pathname}${window.location.search}`);
    }

    if (Number.isNaN(y) || y < 1) {
        return;
    }

    const apply = () => applyPageScrollY(y);

    apply();

    if (!stabilize) {
        return;
    }

    requestAnimationFrame(() => {
        requestAnimationFrame(apply);
    });
}

export function bindActivityScrollRestore(root, { stabilizeRestore = false } = {}) {
    if (!root) {
        return;
    }

    const rememberScrollForLink = (event) => {
        const link = event.target.closest('a[href]');

        if (!link || !root.contains(link)) {
            return;
        }

        stampActivityBackScroll(link);

        if (typeof window.saveScrollPosition === 'function') {
            window.saveScrollPosition();
        }
    };

    root.addEventListener('pointerdown', rememberScrollForLink);
    root.addEventListener('click', rememberScrollForLink);
    restorePageScroll({ stabilize: stabilizeRestore });
}
