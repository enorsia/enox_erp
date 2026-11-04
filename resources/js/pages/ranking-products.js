function readRankingContextValue(selectEl) {
    if (!selectEl) {
        return '';
    }

    if (selectEl.tomselect) {
        try {
            selectEl.tomselect.sync();
        } catch {
            // Options may still be valid from getValue().
        }

        return String(selectEl.tomselect.getValue() ?? '').trim();
    }

    return String(selectEl.value ?? '').trim();
}

function initRankingProductsContextSelect() {
    const form = document.getElementById('ranking-products-context-form');
    const el = document.getElementById('ranking-products-context');

    if (!form || !el || el.tomselect || typeof window.TomSelect === 'undefined') {
        return;
    }

    const allContextsUrl = el.dataset.allContextsUrl || form.action;

    const goToAllContexts = () => {
        window.location.assign(allContextsUrl);
    };

    const submitContextFilter = () => {
        let pageInput = form.querySelector('input[name="page"]');
        if (!pageInput) {
            pageInput = document.createElement('input');
            pageInput.type = 'hidden';
            pageInput.name = 'page';
            form.appendChild(pageInput);
        }
        pageInput.value = '1';
        form.submit();
    };

    new window.TomSelect(el, {
        allowEmptyOption: true,
        placeholder: 'Select listing context…',
        maxOptions: null,
        searchField: ['text'],
        sortField: [{ field: '$order' }],
        plugins: ['clear_button'],
        wrapperClass: 'ts-wrapper single',
        dropdownParent: 'body',
        onChange(value) {
            const next = String(value ?? '').trim();
            if (next === '') {
                goToAllContexts();
                return;
            }
            submitContextFilter();
        },
    });
}

function initRankingProductsDrawerContextSelect() {
    const el = document.getElementById('ranking-filter-context');
    const mainSelect = document.getElementById('ranking-products-context');

    if (!el || el.tomselect || typeof window.TomSelect === 'undefined') {
        return;
    }

    new window.TomSelect(el, {
        allowEmptyOption: true,
        placeholder: 'All listing contexts',
        maxOptions: null,
        searchField: ['text'],
        sortField: [{ field: '$order' }],
        plugins: ['clear_button'],
        wrapperClass: 'ts-wrapper single',
        dropdownParent: 'body',
        onChange(value) {
            if (mainSelect?.tomselect) {
                mainSelect.tomselect.setValue(String(value ?? ''), true);
            } else if (mainSelect) {
                mainSelect.value = String(value ?? '');
            }
        },
    });
}

function initRankingProductsFilterDrawerForm() {
    const drawerForm = document.getElementById('ranking-products-filter-drawer-form');

    if (!drawerForm || drawerForm.dataset.etdSubmitBound === '1') {
        return;
    }

    drawerForm.dataset.etdSubmitBound = '1';

    drawerForm.addEventListener('submit', (event) => {
        event.preventDefault();

        const contextEl = document.getElementById('ranking-filter-context');
        const searchEl = document.getElementById('ranking-filter-search');
        const contextValue = readRankingContextValue(contextEl);
        const searchValue = String(searchEl?.value ?? '').trim();
        const url = new URL(drawerForm.action || window.location.href, window.location.origin);

        url.search = '';

        if (contextValue !== '') {
            url.searchParams.set('context', contextValue);
        }

        if (searchValue !== '') {
            url.searchParams.set('search', searchValue);
        }

        url.searchParams.set('page', '1');

        if (typeof window.closeFilterDrawer === 'function') {
            window.closeFilterDrawer();
        }

        window.location.assign(url.toString());
    });
}

let rankingFilterRefreshPatched = false;

function patchRankingProductsFilterRefresh() {
    if (rankingFilterRefreshPatched || !document.getElementById('ranking-products-page')) {
        return;
    }

    rankingFilterRefreshPatched = true;
    const previousRefresh = window.refreshEtdFilterControls;

    window.refreshEtdFilterControls = function refreshRankingFilterControls(root) {
        if (typeof previousRefresh === 'function') {
            previousRefresh(root);
        }

        initRankingProductsDrawerContextSelect();
    };
}

export function initRankingProductsPage() {
    initRankingProductsContextSelect();
    initRankingProductsDrawerContextSelect();
    initRankingProductsFilterDrawerForm();
    patchRankingProductsFilterRefresh();
}

if (document.getElementById('ranking-products-page')) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRankingProductsPage, { once: true });
    } else {
        initRankingProductsPage();
    }
}
