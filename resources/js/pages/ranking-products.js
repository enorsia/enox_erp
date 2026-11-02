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
        wrapperClass: 'ts-wrapper single product-filter-ts',
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
    const drawerForm = document.getElementById('ranking-products-filter-drawer-form');
    const el = document.getElementById('ranking-filter-context');
    const mainSelect = document.getElementById('ranking-products-context');

    if (!drawerForm || !el || el.tomselect || typeof window.TomSelect === 'undefined') {
        return;
    }

    new window.TomSelect(el, {
        allowEmptyOption: true,
        placeholder: 'All listing contexts',
        maxOptions: null,
        searchField: ['text'],
        sortField: [{ field: '$order' }],
        plugins: ['clear_button'],
        wrapperClass: 'ts-wrapper single product-filter-ts',
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

export function initRankingProductsPage() {
    initRankingProductsContextSelect();
    initRankingProductsDrawerContextSelect();
}

if (document.getElementById('ranking-products-page')) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRankingProductsPage, { once: true });
    } else {
        initRankingProductsPage();
    }
}
