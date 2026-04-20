import $ from '$';

// ── Read config from data attributes set in the blade ──
const pageEl   = document.getElementById('discounts-page-content');
const CALC_URL  = pageEl?.dataset.calculateUrl   ?? '';
const VIEW_URL  = pageEl?.dataset.viewUrl        ?? '';
const DEP_CATS  = pageEl?.dataset.depCatsUrl     ?? '';
const CSRF      = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

$(document).ready(function () {

    /* ══════════════════════════════════════════════════════
       Product Category — TomSelect (replaces Choices.js)
    ══════════════════════════════════════════════════════ */
    let productCategoryTs = null;

    function initProductCategorySelect() {
        const el = document.querySelector('#product_category');
        if (!el || productCategoryTs) return;
        if (typeof TomSelect === 'undefined') { setTimeout(initProductCategorySelect, 100); return; }
        productCategoryTs = new TomSelect(el, {
            create: false,
            searchEnabled: true,
            placeholder: 'Select a Product Category',
            maxOptions: 100,
        });
    }
    initProductCategorySelect();

    /* ══════════════════════════════════════════════════════
       Department select — reload categories (unchanged logic)
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
       CRITICAL — discount_price change / keyup handler
       (logic unchanged — only Blade template vars replaced
        with data-attribute-sourced constants)
    ══════════════════════════════════════════════════════ */
    $(document).on('change keyup', '.discount_price', function () {
        let input         = $(this);
        let form          = input.closest('form');
        let platform_id   = form.find('.platform_id').val();
        let department_id = form.find('.department_id').val();
        let ch_price_id   = input.data('price-id');
        let csp           = parseFloat(input.data('csp')) || 0;
        let tr            = (department_id == 1928 || department_id == 1929)
                                ? input.parents('tr')
                                : form;

        let rawValue = input.val().trim();
        if (rawValue !== '' && isNaN(rawValue)) {
            Swal.fire({ icon: 'error', title: 'Invalid Input', text: 'Please enter a numeric value only.' });
            input.val('');
            input.focus();
            return;
        }

        let discount_price = parseFloat(rawValue) || 0;

        if (discount_price > csp) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Discount',
                text: 'Discount cannot exceed confirm selling price (£' + csp.toFixed(2) + ')'
            });
            input.val(csp.toFixed(2));
            input.focus();
        }

        $.ajax({
            url: CALC_URL,
            type: 'POST',
            data: {
                platform_id:    platform_id,
                discount_price: discount_price,
                ch_price_id:    ch_price_id,
                _token:         CSRF
            },
            success: function (response) {
                tr.find('.com').text('£'     + response.commission.toFixed(2));
                tr.find('.com-vat').text('£' + response.commission_vat.toFixed(2));
                tr.find('.sp').text('£'      + response.selling_price.toFixed(2));
                tr.find('.sl-vat').text('£'  + response.selling_vat.toFixed(2));
                tr.find('.vat-val').text('£' + response.vat_value.toFixed(2));
                tr.find('.sp-vat').text('£'  + response.selling_price_and_vat.toFixed(2));
                tr.find('.pm').text(response.profit_margin.toFixed(2) + '%');
                tr.find('.np').text('£'      + response.net_profit.toFixed(2));
            }
        });
    });

    /* ══════════════════════════════════════════════════════
       CRITICAL — pp-form submit handler
       (logic unchanged)
    ══════════════════════════════════════════════════════ */
    $(document).on('submit', '.pp-form', function (e) {
        let $form     = $(this);
        let anyChecked = false;

        if ($form.hasClass('confirmed')) return true;

        e.preventDefault();
        $form.find('.discount_price').removeClass('is-invalid');

        let saveType      = $form.find('.save_type').val();
        let invalidStatus = false;
        let hasError      = false;

        $form.find('.discount_price')
             .removeClass('is-invalid')
             .next('.custom-error')
             .remove();

        $form.find('input[name="sl_price_id[]"]:checked').each(function () {
            anyChecked = true;

            let chVal     = $(this).val();
            let isChecked = $form.find('.status' + chVal).prop('checked');

            if (saveType == 2 && isChecked)  invalidStatus = true;
            if (saveType == 3 && !isChecked) invalidStatus = true;

            let $discountInput = $form.find('.discount_price' + chVal);
            if (!$discountInput.val().trim()) {
                hasError = true;
                $discountInput.addClass('is-invalid');
                $('<div class="custom-error text-danger text-start mt-1" style="font-size:12px;">This field is required.</div>')
                    .insertAfter($discountInput);
            }
        });

        if (!anyChecked) {
            Swal.fire({
                title: 'No Option Selected',
                text: 'Please select at least one price option before submitting.',
                icon: 'warning'
            });
            return false;
        }

        if (hasError) return false;

        if (invalidStatus) {
            Swal.fire({
                title: 'Invalid Status',
                text: saveType == 2
                    ? 'All selected items must have Status OFF for Approval.'
                    : 'All selected items must have Status ON for Executor.',
                icon: 'error'
            });
            return false;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to save this discount?',
            icon: 'question',
            showCancelButton:    true,
            confirmButtonText:   'Yes, Save it!',
            cancelButtonText:    'Cancel',
            confirmButtonColor:  '#3085d6',
            cancelButtonColor:   '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                $form.addClass('confirmed');
                $form.find('.submit-btn').html(window.loader).prop('disabled', true);
                $form.submit();
            }
        });
    });

    /* ══════════════════════════════════════════════════════
       Toggle columns in view-item modal (unchanged)
    ══════════════════════════════════════════════════════ */
    $(document).on('change', '.toggle-column', function () {
        const target = $(this).val();
        $('.' + target).toggle(this.checked);
    });

});

/* ══════════════════════════════════════════════════════
   CRITICAL — viewChart (unchanged logic)
   Exposed globally so onclick="viewChart(...)" works
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

/* ══════════════════════════════════════════════════════
   approveData — unchanged, exposed globally
══════════════════════════════════════════════════════ */
window.approveData = function (id, action = 'approve') {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton:   true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor:  '#d33',
        confirmButtonText:  'Yes, approve it!'
    }).then((result) => {
        if (result.isConfirmed) {
            let form  = document.getElementById('approve-form-' + id);
            let input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'action_type';
            input.value = action;
            form.appendChild(input);
            form.submit();
        }
    });
};

