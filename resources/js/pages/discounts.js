import $ from '$';

const pageEl   = document.getElementById('discounts-page-content');
const CALC_URL = pageEl?.dataset.calculateUrl ?? '';
const SAVE_URL = pageEl?.dataset.saveUrl     ?? '';
const DEP_CATS = pageEl?.dataset.depCatsUrl  ?? '';
const CSRF     = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

$(document).ready(function () {

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

    /* ── Live profit calculation on discount input ── */
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

    /* ── Validate form before AJAX save ── */
    function validateDiscountForm($form) {
        let anyChecked = false;
        let saveType   = $form.find('.save_type').val();
        let invalidStatus = false;
        let hasError   = false;

        $form.find('.discount_price').removeClass('is-invalid').next('.custom-error').remove();

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
                $('<div class="custom-error text-danger text-start mt-1" style="font-size:11px;">Required.</div>')
                    .insertAfter($discountInput);
            }
        });

        if (!anyChecked) {
            Swal.fire({ title: 'No Option Selected', text: 'Please select at least one price option.', icon: 'warning' });
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
        return true;
    }

    /* ── AJAX form submit ── */
    $(document).on('submit', '.pp-form', function (e) {
        e.preventDefault();

        let $form = $(this);
        if (!validateDiscountForm($form)) return false;

        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to save this discount?',
            icon: 'question',
            showCancelButton:   true,
            confirmButtonText:  'Yes, Save it!',
            cancelButtonText:   'Cancel',
            confirmButtonColor: '#3085d6',
            cancelButtonColor:  '#d33'
        }).then((result) => {
            if (!result.isConfirmed) return;

            let $btn = $form.find('.submit-btn');
            let btnHtml = $btn.html();
            $btn.html(window.loader || 'Saving...').prop('disabled', true);

            $.ajax({
                url:  SAVE_URL,
                type: 'POST',
                data: $form.serialize(),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function (response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved',
                        text: response.message || 'Discount prices updated successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                error: function (xhr) {
                    let msg = xhr.responseJSON?.message || 'Something went wrong. Please try again.';
                    Swal.fire({ icon: 'error', title: 'Error', text: msg });
                },
                complete: function () {
                    $btn.html(btnHtml).prop('disabled', false);
                }
            });
        });
    });

    /* ── Toggle columns — scoped to nearest edit panel ── */
    $(document).on('change', '.toggle-column', function () {
        const target = $(this).val();
        const panel  = $(this).closest('.discount-edit-panel');
        panel.find('.toogle-item.' + target).toggle(this.checked);
    });

});

/* ── approveData — kept for other pages ── */
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
