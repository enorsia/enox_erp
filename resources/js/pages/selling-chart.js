import $ from '$';

const chartPageEl = document.getElementById('chart-page-content');
const formPageEl = document.getElementById('selling-chart-form-content');

const DEP_CATS = (chartPageEl ?? formPageEl)?.dataset.depCatsUrl ?? '';
const VIEW_URL = chartPageEl?.dataset.viewUrl ?? '';
const CALC_URL = chartPageEl?.dataset.calcUrl ?? '';
const SIZE_URL = formPageEl?.dataset.sizeRangeUrl ?? '';
const COLOR_URL = formPageEl?.dataset.colorSearchUrl ?? '';
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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
        if (typeof TomSelect === 'undefined') {
            setTimeout(initProductCategorySelect, 100);
            return;
        }
        productCategoryTs = new TomSelect(el, {
            create: false,
            searchField: 'text',
            sortField: 'text',
            placeholder: el.dataset.placeholder || 'Select a Product Category',
            maxOptions: 100,
        });
    }

    initProductCategorySelect();
    if (!productCategoryTs) setTimeout(initProductCategorySelect, 200);

    /* ══════════════════════════════════════════════════════
       Department select — reload categories & size range
    ══════════════════════════════════════════════════════ */
    $('#department_select').change(function () {
        const id = $(this).val();

        if (DEP_CATS) {
            $.ajax({
                type: 'GET',
                url: DEP_CATS + '/' + id,
                success: function (data) {
                    const options = Object.values(data || {}).map(item => ({
                        value: item.id,
                        text: `${item.name} (${item.category_code})`
                    }));
                    if (productCategoryTs) {
                        productCategoryTs.clearOptions();
                        productCategoryTs.addOptions(options);
                        productCategoryTs.clear();
                    }
                },
                error: function () {
                    console.error('Failed to load categories.');
                }
            });
        }

        if (SIZE_URL && $('.color-table').length > 0) {
            $.ajax({
                type: 'GET',
                url: SIZE_URL + '/' + id,
                success: function (data) {
                    $('.color-table').html(data);
                    const $btnInvisible = $('.btn-invisible');
                    if ($btnInvisible.hasClass('invisible')) {
                        $btnInvisible.removeClass('invisible');
                    } else {
                        $btnInvisible.addClass('invisible');
                    }
                },
                error: function () {
                    console.error('Failed to load size range.');
                }
            });
        }
    });

    /* ══════════════════════════════════════════════════════
       Add more row / delete row (create & edit pages)
    ══════════════════════════════════════════════════════ */
    $('.add_more_btn').on('click', function (e) {
        e.preventDefault();
        const newRow = $('table tbody tr:first').clone();
        newRow.find('input').val('');
        newRow.find('.x_price_fob').removeAttr('readonly');
        $('table tbody').append(newRow);
    });

    $(document).on('click', 'table .delete-row', function () {
        if ($('table tbody tr').length > 1) {
            $(this).closest('tr').remove();
        } else {
            alert('You cannot delete the only row!');
        }
    });

    /* ══════════════════════════════════════════════════════
       Product description — auto Title-Case
    ══════════════════════════════════════════════════════ */
    $('#product_design').on('input', function () {
        $(this).val($(this).val().toLowerCase().replace(/\b\w/g, c => c.toUpperCase()));
    });

    /* ══════════════════════════════════════════════════════
       Custom required-field error on change/input
    ══════════════════════════════════════════════════════ */
    $(document).on('change input', '.ctmr', function () {
        if (!$(this).val()) {
            $(this).after('<label class="error">This field is required.</label>');
        } else {
            $(this).siblings('.error').remove();
        }
    });

    /* ══════════════════════════════════════════════════════
       Color search (create & edit pages)
    ══════════════════════════════════════════════════════ */
    if (COLOR_URL) {
        $(document).on('input', '.color', function () {
            const val = $(this).val();
            const colorBox = $(this).parent().find('.color-box');
            if (val) {
                $.ajax({
                    type: 'GET',
                    url: COLOR_URL + '/' + val,
                    success: function (data) {
                        colorBox.html(data);
                    },
                    error: function () {
                        console.error('Color search failed.');
                    }
                });
            } else {
                colorBox.html('');
            }
        });

        $(document).on('click', function (event) {
            if (!$(event.target).closest('.color, .color-box').length) {
                $('.color-box').html('');
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       Form validation — #selling_chart (create / edit)
    ══════════════════════════════════════════════════════ */
    if ($('#selling_chart').length) {
        $('#selling_chart').validate({
            ignore: [],
            errorClass: 'is-invalid',
            validClass: 'is-valid',
            errorElement: 'div',
            errorPlacement: function (error, element) {
                if (element.hasClass('choices__input')) {
                    error.insertAfter(element.closest('.choices'));
                } else {
                    error.insertAfter(element);
                }
            },
            submitHandler: function (form) {
                if (!checkRequiredAfterSubmit()) return false;
                $('.submit-btn').html(window.loader).attr('disabled', true);
                setTimeout(() => form.submit(), 400);
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       Form validation — #import_form
    ══════════════════════════════════════════════════════ */
    if ($('#import_form').length) {
        $('#import_form').validate({
            ignore: [],
            errorClass: 'is-invalid',
            validClass: 'is-valid',
            errorElement: 'div',
            submitHandler: function (form) {
                $('.submit-btn').html(window.loader).attr('disabled', true);
                setTimeout(() => form.submit(), 400);
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       Form validation — #bulk_form
    ══════════════════════════════════════════════════════ */
    if ($('#bulk_form').length) {
        $('#bulk_form').validate({
            submitHandler: function (form) {
                if (!$('input[name="price_id[]"]:checked').length) {
                    Swal.fire({
                        title: 'No Option Selected',
                        text: 'Please select at least one price option before submitting.',
                        icon: 'warning',
                        confirmButtonColor: '#3085d6',
                    });
                    return false;
                }
                if (!checkRequiredAfterSubmit()) return false;
                $('.submit-btn').html(window.loader).attr('disabled', true);
                setTimeout(() => form.submit(), 400);
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       Discount price change/keyup (inside view-item modal)
    ══════════════════════════════════════════════════════ */
    $(document).on('change keyup', '.discount_price', function () {
        const input = $(this);
        const form = input.closest('form');
        const platform_id = form.find('.platform_id').val();
        const department_id = form.find('.department_id').val();
        const ch_price_id = input.data('price-id');
        const csp = parseFloat(input.data('csp')) || 0;
        const tr = (department_id == 1928 || department_id == 1929)
            ? input.parents('tr')
            : form;

        const rawValue = input.val().trim();
        if (rawValue !== '' && isNaN(rawValue)) {
            Swal.fire({icon: 'error', title: 'Invalid Input', text: 'Please enter a numeric value only.'});
            input.val('').focus();
            return;
        }

        const discount_price = parseFloat(rawValue) || 0;
        if (discount_price > csp) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Discount',
                text: 'Discount cannot exceed confirm selling price (£' + csp.toFixed(2) + ')'
            });
            input.val(csp.toFixed(2)).focus();
        }

        if (CALC_URL) {
            $.ajax({
                url: CALC_URL,
                type: 'POST',
                data: {platform_id, discount_price, ch_price_id, _token: CSRF},
                success: function (response) {
                    tr.find('.com').text('£' + response.commission.toFixed(2));
                    tr.find('.com-vat').text('£' + response.commission_vat.toFixed(2));
                    tr.find('.sp').text('£' + response.selling_price.toFixed(2));
                    tr.find('.sl-vat').text('£' + response.selling_vat.toFixed(2));
                    tr.find('.vat-val').text('£' + response.vat_value.toFixed(2));
                    tr.find('.sp-vat').text('£' + response.selling_price_and_vat.toFixed(2));
                    tr.find('.pm').text(response.profit_margin.toFixed(2) + '%');
                    tr.find('.np').text('£' + response.net_profit.toFixed(2));
                }
            });
        }
    });

    /* ══════════════════════════════════════════════════════
       pp-form submit — discount save confirmation
    ══════════════════════════════════════════════════════ */
    $(document).on('submit', '.pp-form', function (e) {
        const $form = $(this);
        let anyChecked = false;

        if ($form.hasClass('confirmed')) return true;

        e.preventDefault();
        $form.find('.discount_price').removeClass('is-invalid').next('.custom-error').remove();

        const saveType = $form.find('.save_type').val();
        let invalidStatus = false;
        let hasError = false;

        $form.find('input[name="sl_price_id[]"]:checked').each(function () {
            anyChecked = true;
            const chVal = $(this).val();
            const isChecked = $form.find('.status' + chVal).prop('checked');

            if (saveType == 2 && isChecked) invalidStatus = true;
            if (saveType == 3 && !isChecked) invalidStatus = true;

            const $discountInput = $form.find('.discount_price' + chVal);
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
            showCancelButton: true,
            confirmButtonText: 'Yes, Save it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                $form.addClass('confirmed');
                $form.find('.submit-btn').html(window.loader).prop('disabled', true);
                $form.submit();
            }
        });
    });

    /* ══════════════════════════════════════════════════════
       Toggle columns in view-item modal
    ══════════════════════════════════════════════════════ */
    $(document).on('change', '.toggle-column', function () {
        $('.' + $(this).val()).toggle(this.checked);
    });

    /* ══════════════════════════════════════════════════════
       Create page — price calculation (season-based)
    ══════════════════════════════════════════════════════ */
    function createPriceCal($row) {
        const selectedVal = $('#season_select').val();
        const expInput = $('.season-exp' + selectedVal);
        const conversionRate = parseFloat(expInput.data('conversion-rate')) || 0;
        const commercialExpense = parseFloat(expInput.data('commercial-expense')) || 0;
        const enorsiaBDExpense = parseFloat(expInput.data('enorsia-bd-expense')) || 0;
        const enorsiaUKExpense = parseFloat(expInput.data('enorsia-uk-expense')) || 0;
        const priceFOB = parseFloat($row.find('.x_price_fob').val()) || 0;

        const unitPrice = priceFOB
            ? (priceFOB * conversionRate) + (commercialExpense + enorsiaBDExpense + enorsiaUKExpense)
            : 0;
        $row.find('.x_unit_price').val(unitPrice.toFixed(2));
    }

    $(document).on('input', '.create_selling_chart_tbl tbody .x_price_fob', function () {
        createPriceCal($(this).closest('tr'));
    });

    $(document).on('change', '#season_select', function () {
        $('.create_selling_chart_tbl tbody tr').each(function () {
            createPriceCal($(this));
        });
    });

    /* ══════════════════════════════════════════════════════
       Edit page — full price calculation (expense-based)
    ══════════════════════════════════════════════════════ */
    $('.selling_chart_edit_table tbody').on(
        'input',
        '.price_fob, .shipping_cost, .confirm_selling_price, .discount',
        function () {
            const $row = $(this).closest('tr');
            const expInp = $row.find('.expense_input');

            if (!expInp.val()) {
                Swal.fire({
                    title: 'Expense Not Found',
                    text: 'Please insert expense first.',
                    icon: 'warning',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK',
                });
            }

            const conversionRate = parseFloat(expInp.data('conversion-rate')) || 0;
            const commercialExpense = parseFloat(expInp.data('commercial-expense')) || 0;
            const enorsiaBDExpense = parseFloat(expInp.data('enorsia-bd-expense')) || 0;
            const enorsiaUKExpense = parseFloat(expInp.data('enorsia-uk-expense')) || 0;
            const expShippingCost = parseFloat(expInp.data('shipping-cost')) || 0;
            const department = parseInt(expInp.data('department'));

            const priceFOB = parseFloat($row.find('.price_fob').val()) || 0;
            const shippingCost = parseFloat($row.find('.shipping_cost').val()) || 0;
            const unitPrice = (priceFOB * conversionRate) + (commercialExpense + enorsiaBDExpense + enorsiaUKExpense + (shippingCost || expShippingCost));
            $row.find('.unit_price').val(unitPrice.toFixed(2));

            const csp = parseFloat($row.find('.confirm_selling_price').val()) || 0;
            let selingVat, selingVatValue;
            if (department == 1926 || department == 1927) {
                selingVatValue = (csp * 20) / 120;
                selingVat = csp - selingVatValue;
            } else {
                selingVatValue = 0;
                selingVat = csp;
            }
            $row.find('.seling_vat').val(selingVat.toFixed(2));
            $row.find('.seling_vat_value').val(selingVatValue.toFixed(2));
            $row.find('.profit_margin').val(selingVat ? ((selingVat - unitPrice) / selingVat * 100).toFixed(2) : '0.00');
            $row.find('.net_profit').val((selingVat - unitPrice).toFixed(2));

            const discount = parseFloat($row.find('.discount').val()) || 0;
            const discountSellingPrice = csp - (csp * (discount / 100));
            $row.find('.discount_selling_price').val(discountSellingPrice.toFixed(2));

            let sellingVatDedactPrice, discountVatValue;
            if (department == 1926 || department == 1927) {
                sellingVatDedactPrice = (discountSellingPrice / 120) * 100;
                discountVatValue = discountSellingPrice - sellingVatDedactPrice;
            } else {
                sellingVatDedactPrice = discountSellingPrice;
                discountVatValue = 0;
            }
            $row.find('.selling_vat_dedact_price').val(sellingVatDedactPrice.toFixed(2));
            $row.find('.discount_vat_value').val(discountVatValue.toFixed(2));
            $row.find('.discount_profit_margin').val(
                sellingVatDedactPrice
                    ? ((sellingVatDedactPrice - unitPrice) / sellingVatDedactPrice * 100).toFixed(2)
                    : '0.00'
            );
            $row.find('.discount_net_profit').val((sellingVatDedactPrice - unitPrice).toFixed(2));
        }
    );

});

/* ══════════════════════════════════════════════════════
   checkRequiredAfterSubmit — validates .ctmr fields
══════════════════════════════════════════════════════ */
function checkRequiredAfterSubmit() {
    let valid = true;
    $('.ctmr').each(function () {
        if (!$(this).val()) {
            $(this).siblings('.error').remove();
            if ($(this).hasClass('x_color_code')) {
                $(this).siblings('.color').after('<label class="error">This field is required.</label>');
            } else {
                $(this).after('<label class="error">This field is required.</label>');
            }
            valid = false;
        } else {
            $(this).siblings('.error').remove();
        }
    });
    return valid;
}

/* ══════════════════════════════════════════════════════
   viewChart — global (called via onclick in blade)
══════════════════════════════════════════════════════ */
window.viewChart = function (id, page = 1) {
    if (!VIEW_URL) return;
    $.ajax({
        type: 'GET',
        url: VIEW_URL.replace(':id', id),
        data: {page},
        success: function (response) {
            if (response.status === true) {
                $('#viewSellingChartItemModal').remove();
                $('.setViewSellingChartItemModal').html(response.data);
                if (window.Alpine) {
                    window.Alpine.initTree(document.querySelector('.setViewSellingChartItemModal'));
                }
                document.body.style.overflow = 'hidden';
            }
        },
        error: function () {
            console.error('Failed to load chart view.');
        }
    });
};

/* ══════════════════════════════════════════════════════
   closeDiscountModal — global
══════════════════════════════════════════════════════ */
window.closeDiscountModal = function () {
    $('#viewSellingChartItemModal').remove();
    document.body.style.overflow = '';
};

$(document).on('click', '[data-large]', function (e) {
    e.preventDefault();
    const url = $(this).attr('data-large') || $(this).data('large');
    if (!url) return;
    console.debug('selling-chart: data-large clicked, dispatching set-image-popup', url);
    window.dispatchEvent(new CustomEvent('set-image-popup', {detail: url}));
});

/* ══════════════════════════════════════════════════════
   setColor — global (called via onclick in color-box rows)
══════════════════════════════════════════════════════ */
window.setColor = function (e, id, name, code) {
    const colorBox = $(e.target).parents('.color-box');
    colorBox.siblings('.color').val($(e.target).text());
    colorBox.siblings('.x_color_id').val(id);
    colorBox.siblings('.x_color_name').val(name);
    colorBox.siblings('.x_color_code').val(code);
    colorBox.siblings('.error').remove();
    colorBox.html('');
};

/* ══════════════════════════════════════════════════════
   approveData — global (called via onclick in view-item)
══════════════════════════════════════════════════════ */
window.approveData = function (id, action = 'approve') {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('approve-form-' + id);
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action_type';
            input.value = action;
            form.appendChild(input);
            form.submit();
        }
    });
};

