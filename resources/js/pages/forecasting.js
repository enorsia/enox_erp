import $ from '$';

// ── Read config from data attributes set in the blade ──
const pageEl  = document.getElementById('forecasting-page-content');
const VIEW_URL = pageEl?.dataset.viewUrl    ?? '';
const DEP_CATS = pageEl?.dataset.depCatsUrl ?? '';

$(document).ready(function () {

    /* ══════════════════════════════════════════════════════
       Product Category — TomSelect
       common.js already initialises ALL .tom-select elements
       so we just grab the existing instance instead of
       creating a new one (TomSelect throws if you double-init).
    ══════════════════════════════════════════════════════ */
    let productCategoryTs = null;

    function initProductCategorySelect() {
        const el = document.querySelector('#product_category');
        if (!el) return;
        if (productCategoryTs) return;

        if (el.tomselect) {
            productCategoryTs = el.tomselect;
            return;
        }

        if (typeof TomSelect === 'undefined') { setTimeout(initProductCategorySelect, 100); return; }
        productCategoryTs = new TomSelect(el, {
            create: false,
            searchEnabled: true,
            placeholder: 'Select a Product Category',
            maxOptions: 100,
        });
    }

    initProductCategorySelect();
    if (!productCategoryTs) setTimeout(initProductCategorySelect, 200);

    /* ══════════════════════════════════════════════════════
       Department select — reload categories
    ══════════════════════════════════════════════════════ */
    $('#department_select').change(function () {
        const id = $(this).val();
        $.ajax({
            type: 'GET',
            url: DEP_CATS + '/' + id,
            success: function (data) {
                const dataArray = Object.values(data || {});
                if (productCategoryTs) {
                    productCategoryTs.clearOptions();
                    productCategoryTs.addOptions(dataArray.map(item => ({
                        value: item.id,
                        text: `${item.name} (${item.category_code})`
                    })));
                    productCategoryTs.clear();
                }
            },
            error: function () {
                console.error('Failed to load categories.');
            }
        });
    });

    /* ══════════════════════════════════════════════════════
       Toggle columns in view-item modal
    ══════════════════════════════════════════════════════ */
    $(document).on('change', '.toggle-column', function () {
        const target = $(this).val();
        $('.' + target).toggle(this.checked);
    });

});

/* ══════════════════════════════════════════════════════
   viewChart — exposed globally so onclick="viewChart(...)" works
   Uses Tailwind overlay approach (no Bootstrap modal).
══════════════════════════════════════════════════════ */
window.viewChart = function (id, page = 1) {
    let url = VIEW_URL.replace(':id', id);
    $.ajax({
        type: 'GET',
        url:  url,
        data: { page: page },
        success: function (response) {
            if (response.status == true) {
                $('#viewSellingChartItemModal').remove();
                $('.setViewSellingChartItemModal').html(response.data);
                // Init Alpine.js on the freshly injected markup
                if (window.Alpine) {
                    window.Alpine.initTree(document.querySelector('.setViewSellingChartItemModal'));
                }
                // Lock body scroll while modal is open
                document.body.style.overflow = 'hidden';
            }
        },
        error: function (data) {
            console.log('Something went wrong.' + data);
        }
    });
};

window.closeDiscountModal = function () {
    $('#viewSellingChartItemModal').remove();
    document.body.style.overflow = '';
};

