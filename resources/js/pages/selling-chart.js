import $ from '$';
import 'jquery-validation';
import TomSelect from 'tom-select';

const chartPageEl = document.getElementById('chart-page-content');
const formPageEl = document.getElementById('selling-chart-form-content');

const DEP_CATS = (chartPageEl ?? formPageEl)?.dataset.depCatsUrl ?? '';
const VIEW_URL = chartPageEl?.dataset.viewUrl ?? '';
const CALC_URL = chartPageEl?.dataset.calcUrl ?? '';
const SIZE_URL = formPageEl?.dataset.sizeRangeUrl ?? '';
const COLOR_URL = formPageEl?.dataset.colorSearchUrl ?? '';
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function getTomSelectWrapper($el) {
    const $next = $el.next('.ts-wrapper');
    if ($next.length) return $next;
    return $el.closest('.ts-wrapper');
}

function placeSellingChartError(error, element) {
    error.addClass('f-error f-field-error');
    const $wrapper = getTomSelectWrapper($(element));
    if ($wrapper.length) {
        error.insertAfter($wrapper);
    } else {
        error.insertAfter(element);
    }
}

function getCtmrValue(element) {
    const el = element instanceof $ ? element[0] : element;
    if (!el) return '';

    let value = '';
    if (el.tomselect) {
        const tsVal = el.tomselect.getValue();
        value = Array.isArray(tsVal) ? (tsVal[0] ?? '') : (tsVal ?? '');
    }
    if (!value) {
        value = $(el).val() ?? '';
    }

    return String(value).trim();
}

function isColorRowValid($container) {
    const colorId = String($container.find('.x_color_id').val() ?? '').trim();
    const colorCode = String($container.find('.x_color_code').val() ?? '').trim();
    return !!(colorId || colorCode);
}

function clearColorFieldError($container) {
    $container.find('.f-field-error').remove();
    $container.find('.color, .x_color_code').removeClass('f-error-validate');
}

function showColorFieldError($container, message = 'This field is required.') {
    clearColorFieldError($container);
    const $color = $container.find('.color');
    $color.addClass('f-error-validate');
    $('<p class="f-error f-field-error"></p>').text(message).insertAfter($color);
}

function validateColorTableRows() {
    let valid = true;

    $('.create_selling_chart_tbl tbody tr').each(function () {
        const $row = $(this);
        const $colorWrap = $row.find('.color-cell-wrap');

        if (!isColorRowValid($colorWrap)) {
            showColorFieldError($colorWrap);
            valid = false;
        } else {
            clearColorFieldError($colorWrap);
        }

        const $range = $row.find('select[name="range_id[]"]');
        if ($range.length) {
            if (!getCtmrValue($range[0])) {
                showFieldError($range[0]);
                valid = false;
            } else {
                clearFieldError($range[0]);
            }
        }

        $row.find('.x_po_order_qty.ctmr, .x_price_fob.ctmr').each(function () {
            if (!getCtmrValue(this)) {
                showFieldError(this);
                valid = false;
            } else {
                clearFieldError(this);
            }
        });
    });

    return valid;
}

function bindTomSelectCtmrValidation(element) {
    if (!element?.tomselect || element.tomselect._ctmrValidationBound) return;
    element.tomselect._ctmrValidationBound = true;
    element.tomselect.on('change', () => {
        if (getCtmrValue(element)) {
            clearFieldError(element);
        }
    });
}

function initColorTableTomSelect(root) {
    const scope = root && root.querySelectorAll ? root : document;
    const selects = root && root.matches && root.matches('select.tom-select')
        ? [root]
        : [...scope.querySelectorAll('.create_selling_chart_tbl select.tom-select')];

    selects.forEach((element) => {
        const savedValue = element.tomselect
            ? element.tomselect.getValue()
            : (element.value || '');

        if (element.tomselect) {
            try {
                element.tomselect.destroy();
            } catch (e) {
                /* ignore */
            }
            delete element.tomselect;
            $(element).next('.ts-wrapper').remove();
            element.classList.remove('tomselected', 'ts-hidden-accessible');
            element.style.display = '';
        }

        const ts = new TomSelect(element, {
            create: false,
            searchField: ['text'],
            sortField: [{ field: '$order' }, { field: '$score' }],
            placeholder: element.dataset.placeholder || 'Select range',
            maxOptions: 100,
            dropdownParent: 'body',
        });

        if (savedValue) {
            ts.setValue(String(savedValue), true);
        }

        ts.on('change', () => {
            if (getCtmrValue(element)) {
                clearFieldError(element);
            }
        });

        ts._ctmrValidationBound = true;
    });
}

function hasSavedColorTableRows() {
    return document.querySelector('.create_selling_chart_tbl input[name="price_id[]"]') !== null;
}

function positionColorDropdown($colorInput) {
    const $colorBox = $colorInput.closest('.color-cell-wrap').find('.color-box');
    if (!$colorBox.length || !$colorBox.children().length) {
        return;
    }

    const rect = $colorInput[0].getBoundingClientRect();
    $colorBox.css({
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${Math.max(rect.width, 224)}px`,
        zIndex: 10050,
    });
}

function resetColorDropdownPosition($colorBox) {
    $colorBox.css({
        position: '',
        top: '',
        left: '',
        width: '',
        zIndex: '',
    });
}

function destroyRowTomSelects($row) {
    $row.find('select.tom-select').each(function () {
        if (this.tomselect) {
            try {
                this.tomselect.destroy();
            } catch (e) {
                /* ignore */
            }
            delete this.tomselect;
        }
        $(this).next('.ts-wrapper').remove();
        this.classList.remove('tomselected', 'ts-hidden-accessible');
        this.style.display = '';
        this.style.visibility = '';
        this.style.position = '';
    });
}

function cloneColorTableRow() {
    const $table = $('.create_selling_chart_tbl');
    const $newRow = $table.find('tbody tr:first').clone();

    destroyRowTomSelects($newRow);

    $newRow.find('.f-field-error').remove();
    $newRow.find('.f-error-validate').removeClass('f-error-validate');
    $newRow.find('.ts-wrapper').remove();

    $newRow.find('select.tom-select').each(function () {
        this.selectedIndex = 0;
        this.value = '';
    });

    $newRow.find('select').not('.tom-select').val('');
    $newRow.find('input').val('');
    $newRow.find('.x_price_fob').removeAttr('readonly');
    $newRow.find('input[name="price_id[]"]').remove();
    $newRow.find('.delete-row').parent().append('<input type="hidden" name="price_id[]" value="">');
    $newRow.find('.color-box').empty();

    return $newRow;
}

function applyColorSelection($container, id, name, code, label) {
    $container.find('.color').val(label || `${name} (${code})`);
    $container.find('.x_color_id').val(id);
    $container.find('.x_color_name').val(name);
    $container.find('.x_color_code').val(code);
    clearColorFieldError($container);
    const $colorBox = $container.find('.color-box');
    $colorBox.empty();
    resetColorDropdownPosition($colorBox);
}

function handleCtmrFieldChange(element) {
    const $el = $(element);

    if ($el.hasClass('x_color_code')) {
        const $container = $el.closest('.color-cell-wrap');
        if (isColorRowValid($container)) {
            clearColorFieldError($container);
        } else {
            showColorFieldError($container);
        }
        return;
    }

    if (getCtmrValue(element)) {
        clearFieldError(element);
    } else {
        showFieldError(element);
    }
}

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

    let sizeRangeRequest = null;

    function showFormActions() {
        $('.submit-btn, .add_more_btn').removeClass('invisible');
    }

    function hideFormActions() {
        $('.submit-btn, .add_more_btn').addClass('invisible');
    }

    function getDepartmentId() {
        const el = document.querySelector('#department_select');
        if (!el) return '';
        if (el.tomselect) {
            const value = el.tomselect.getValue();
            return Array.isArray(value) ? (value[0] ?? '') : (value ?? '');
        }
        return $(el).val() ?? '';
    }

    function loadDepartmentDependencies(departmentId) {
        if (!departmentId) {
            hideFormActions();
            $('.color-table').empty();
            return;
        }

        if (DEP_CATS) {
            $.ajax({
                type: 'GET',
                url: DEP_CATS + '/' + departmentId,
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

        if (!SIZE_URL || $('.color-table').length === 0) {
            return;
        }

        if (sizeRangeRequest) {
            sizeRangeRequest.abort();
        }

        sizeRangeRequest = $.ajax({
            type: 'GET',
            url: SIZE_URL + '/' + departmentId,
            success: function (data) {
                $('.color-table').html(data);
                showFormActions();
                initColorTableTomSelect(document.querySelector('.color-table'));
            },
            error: function (_xhr, status) {
                if (status === 'abort') return;
                console.error('Failed to load size range.');
            },
            complete: function () {
                sizeRangeRequest = null;
            }
        });
    }

    function handleDepartmentChange() {
        clearTimeout(handleDepartmentChange._timer);
        handleDepartmentChange._timer = setTimeout(() => {
            loadDepartmentDependencies(getDepartmentId());
        }, 0);
    }

    /* ══════════════════════════════════════════════════════
       Department select — reload categories & size range
    ══════════════════════════════════════════════════════ */
    $(document).on('change.sellingChartDept', '#department_select', handleDepartmentChange);

    function bindDepartmentTomSelect() {
        const el = document.querySelector('#department_select');
        if (!el) return;
        if (el.tomselect) {
            el.tomselect.off('change', handleDepartmentChange);
            el.tomselect.on('change', handleDepartmentChange);
            return;
        }
        setTimeout(bindDepartmentTomSelect, 100);
    }

    bindDepartmentTomSelect();

    if (getDepartmentId() && !hasSavedColorTableRows()) {
        handleDepartmentChange();
    }

    const colorTableRoot = document.querySelector('.color-table');
    if (colorTableRoot?.querySelector('.create_selling_chart_tbl')) {
        initColorTableTomSelect(colorTableRoot);
    }

    /* ══════════════════════════════════════════════════════
       Add more row / delete row (create & edit pages)
    ══════════════════════════════════════════════════════ */
    $('.add_more_btn').on('click', function (e) {
        e.preventDefault();
        const $table = $('.create_selling_chart_tbl');
        if (!$table.length) return;

        const $newRow = cloneColorTableRow();
        $table.find('tbody').append($newRow);
        initColorTableTomSelect($newRow[0]);
    });

    $(document).on('click', '.create_selling_chart_tbl .delete-row', function () {
        const $row = $(this).closest('tr');
        if ($('.create_selling_chart_tbl tbody tr').length > 1) {
            destroyRowTomSelects($row);
            $row.remove();
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
       Custom required-field error on change/input (color table)
    ══════════════════════════════════════════════════════ */
    $(document).on('change input', '.create_selling_chart_tbl .ctmr', function () {
        handleCtmrFieldChange(this);
    });

    /* ══════════════════════════════════════════════════════
       Color search (create & edit pages)
    ══════════════════════════════════════════════════════ */
    if (COLOR_URL) {
        $(document).on('input', '.create_selling_chart_tbl .color', function () {
            const $input = $(this);
            const $container = $input.closest('.color-cell-wrap');
            const val = String($input.val() ?? '').trim();
            const colorBox = $container.find('.color-box');

            if (!val) {
                $container.find('.x_color_id, .x_color_name, .x_color_code').val('');
                colorBox.empty();
                resetColorDropdownPosition(colorBox);
            } else {
                $.ajax({
                    type: 'GET',
                    url: COLOR_URL + '/' + encodeURIComponent(val),
                    success: function (data) {
                        colorBox.html(data);
                        positionColorDropdown($input);
                    },
                    error: function () {
                        console.error('Color search failed.');
                    }
                });
            }

            if (isColorRowValid($container)) {
                clearColorFieldError($container);
            }
        });

        $(document).on('focus', '.create_selling_chart_tbl .color', function () {
            positionColorDropdown($(this));
        });

        $(window).on('resize scroll', function () {
            $('.create_selling_chart_tbl .color').each(function () {
                const $input = $(this);
                if ($input.closest('.color-cell-wrap').find('.color-box').children().length) {
                    positionColorDropdown($input);
                }
            });
        });

        $(document).on('click', '.color-pick-item', function () {
            const $item = $(this);
            const $container = $item.closest('.color-box').closest('.color-cell-wrap');
            applyColorSelection(
                $container,
                $item.attr('data-color-id'),
                $item.attr('data-color-name'),
                $item.attr('data-color-code'),
                $item.text().trim()
            );
        });

        $(document).on('click', function (event) {
            if (!$(event.target).closest('.color, .color-box').length) {
                $('.color-box').each(function () {
                    $(this).empty();
                    resetColorDropdownPosition($(this));
                });
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       Form validation — #selling_chart (create / edit)
    ══════════════════════════════════════════════════════ */
    if ($('#selling_chart').length) {
        $('#selling_chart').validate({
            ignore: (index, element) => $(element).closest('.create_selling_chart_tbl').length > 0,
            errorClass: 'f-error-validate',
            validClass: '',
            errorElement: 'p',
            errorPlacement: function (error, element) {
                placeSellingChartError(error, element);
            },
            highlight: function (element) {
                const $el = $(element);
                $el.addClass('f-error-validate');
                getTomSelectWrapper($el).addClass('f-error-validate');
            },
            unhighlight: function (element) {
                const $el = $(element);
                $el.removeClass('f-error-validate');
                getTomSelectWrapper($el).removeClass('f-error-validate');
            },
            submitHandler: function (form) {
                if (!checkRequiredAfterSubmit()) return false;
                $('.submit-btn').html(window.loader).attr('disabled', true);
                setTimeout(() => form.submit(), 400);
            }
        });

        $(document).on('change input', '#selling_chart select, #selling_chart .f-input', function () {
            const $form = $('#selling_chart');
            if ($form.data('validator')) {
                $(this).valid();
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
       Save all discount platforms — single button
    ══════════════════════════════════════════════════════ */
    $(document).on('submit', '.pp-form', function (e) {
        e.preventDefault();
    });

    $(document).on('click', '.save-all-discounts-btn', function () {
        const $scope = $(this).closest('.discount-edit-panel');
        const $forms = $scope.find('.pp-form');
        if (!$forms.length) return;

        const formsToSave = [];
        let hasAnyDiscount = false;
        let hasError = false;

        $forms.each(function () {
            const $form = $(this);
            $form.find('.discount_price').removeClass('is-invalid').next('.custom-error').remove();

            let hasDiscount = false;
            $form.find('.discount_price').each(function () {
                const val = $(this).val().trim();
                if (val) {
                    hasDiscount = true;
                    if (isNaN(val)) {
                        hasError = true;
                        $(this).addClass('is-invalid');
                    }
                }
            });

            if (hasDiscount) {
                hasAnyDiscount = true;
                formsToSave.push($form);
            }
        });

        if (hasError) return;

        if (!hasAnyDiscount) {
            Swal.fire({ title: 'No Discount', text: 'Enter at least one discount price to save.', icon: 'warning' });
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to save discounts for all platforms?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Save it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const $btn = $scope.find('.save-all-discounts-btn');
            const btnHtml = $btn.html();
            $btn.html(window.loader || 'Saving...').prop('disabled', true);

            const requests = formsToSave.map(($form) => $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }));

            $.when.apply($, requests)
                .done(function () {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved',
                        text: 'Discount prices updated successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                })
                .fail(function (xhr) {
                    const msg = xhr?.responseJSON?.message || 'Something went wrong. Please try again.';
                    Swal.fire({ icon: 'error', title: 'Error', text: msg });
                })
                .always(function () {
                    $btn.html(btnHtml).prop('disabled', false);
                });
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
        console.log(expInput);
        const conversionRate = parseFloat(expInput.data('conversion-rate')) || 0;
        const commercialExpense = parseFloat(expInput.data('commercial-expense')) || 0;
        const enorsiaBDExpense = parseFloat(expInput.data('enorsia-bd-expense')) || 0;
        const enorsiaUKExpense = parseFloat(expInput.data('enorsia-uk-expense')) || 0;
        const expShippingCost = parseFloat(expInput.data('shipping-cost')) || 0;
        const priceFOB = parseFloat($row.find('.x_price_fob').val()) || 0;
        const unitPrice = priceFOB
            ? (priceFOB * conversionRate) + (commercialExpense + enorsiaBDExpense + enorsiaUKExpense + expShippingCost)
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

            // console.log((priceFOB * conversionRate) + (commercialExpense + enorsiaBDExpense + enorsiaUKExpense + (shippingCost || expShippingCost)));

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
   Field validation helpers (create / edit color table)
══════════════════════════════════════════════════════ */
function showFieldError(element, message = 'This field is required.') {
    const $el = $(element);

    if ($el.hasClass('x_color_code')) {
        showColorFieldError($el.parent(), message);
        return;
    }

    const $wrapper = getTomSelectWrapper($el);

    if ($wrapper.length) {
        $wrapper.next('.f-field-error').remove();
    } else {
        $el.next('.f-field-error').remove();
    }

    $el.addClass('f-error-validate');
    if ($wrapper.length) {
        $wrapper.addClass('f-error-validate');
    }

    const $error = $('<p class="f-error f-field-error"></p>').text(message);

    if ($wrapper.length) {
        $error.insertAfter($wrapper);
    } else {
        $error.insertAfter($el);
    }
}

function clearFieldError(element) {
    const $el = $(element);

    if ($el.hasClass('x_color_code')) {
        clearColorFieldError($el.parent());
        return;
    }

    const $wrapper = getTomSelectWrapper($el);

    $el.removeClass('f-error-validate');
    if ($wrapper.length) {
        $wrapper.removeClass('f-error-validate');
        $wrapper.next('.f-field-error').remove();
        return;
    }

    $el.next('.f-field-error').remove();
}

/* ══════════════════════════════════════════════════════
   checkRequiredAfterSubmit — validates .ctmr fields
══════════════════════════════════════════════════════ */
function checkRequiredAfterSubmit() {
    return validateColorTableRows();
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
                if (typeof window.initTomSelectElements === 'function') {
                    const modalRoot = document.querySelector('.setViewSellingChartItemModal');
                    if (modalRoot) window.initTomSelectElements(modalRoot);
                }
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
    const $target = $(e.target).closest('.color-pick-item, li');
    const $container = $target.closest('.color-box').closest('.color-cell-wrap');
    applyColorSelection(
        $container,
        id,
        name,
        code,
        $target.text().trim()
    );
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

