import $ from '$';

const pageEl   = document.getElementById('discounts-page-content');
const CALC_URL = pageEl?.dataset.calculateUrl ?? '';
const SAVE_URL = pageEl?.dataset.saveUrl     ?? '';
const DEP_CATS = pageEl?.dataset.depCatsUrl  ?? '';
const CSRF     = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function isGroupedDept(department_id) {
    return department_id == 1926 || department_id == 1927;
}

function isGirlsBoysDept(department_id) {
    return department_id == 1928 || department_id == 1929;
}

function getRowPriceId($row) {
    return $row.data('price-id')
        || $row.find('.cost-basis-radio').first().data('price-id')
        || $row.find('.shipping-cost-input').first().data('price-id')
        || $row.find('.discount_price').data('price-id');
}

function getDiscountInputForRow(form, ch_price_id) {
    const $specific = form.find('.discount_price' + ch_price_id);
    return $specific.length ? $specific : form.find('.discount_price').first();
}

function getCostBasis(form, ch_price_id) {
    const $checked = form.find('input[name="cost_basis[' + ch_price_id + ']"]:checked');
    if ($checked.length) return $checked.val();
    return form.find('.cost-basis-radio:checked').first().val() || 'unit';
}

function getShippingValue(form, ch_price_id) {
    const $input = form.find('.shipping-cost-input[data-price-id="' + ch_price_id + '"]');
    if ($input.length) return $input.val();
    return form.find('.shipping-cost-input').first().val() ?? form.data('default-shipping');
}

function applyProfitResponse($row, response) {
    $row.find('.com').text('£' + response.commission.toFixed(2));
    $row.find('.com-vat').text('£' + response.commission_vat.toFixed(2));
    $row.find('.sp').text('£' + response.selling_price.toFixed(2));
    $row.find('.sl-vat').text('£' + response.selling_vat.toFixed(2));
    $row.find('.vat-val').text('£' + response.vat_value.toFixed(2));
    $row.find('.sp-vat').text('£' + response.selling_price_and_vat.toFixed(2));
    $row.find('.pm').text(response.profit_margin.toFixed(2) + '%');
    $row.find('.np').text('£' + response.net_profit.toFixed(2));
    $row.find('.dis-perc').text(response.discount_percent.toFixed(2) + '%');

    const $adjusted = $row.find('.adjusted-unit-price');
    if (response.adjusted_unit_price != null && response.cost_basis !== 'fob') {
        $adjusted.text('£' + parseFloat(response.adjusted_unit_price).toFixed(2)).removeClass('hidden');
    } else {
        $adjusted.text('').addClass('hidden');
    }
}

function applyOriginalProfit($row) {
    const original = $row.data('original-profit');
    if (original) {
        applyProfitResponse($row, original);
    }
    $row.find('.adjusted-unit-price').text('').addClass('hidden');
}

function shouldUseOriginalCalc($row, form, ch_price_id) {
    const shipping = parseFloat(getShippingValue(form, ch_price_id)) || 0;
    const originalShipping = parseFloat($row.data('original-shipping')) || 0;
    const cost_basis = getCostBasis(form, ch_price_id);
    return cost_basis === 'unit' && Math.abs(shipping - originalShipping) <= 0.005;
}

function calculateProfitForRow(form, $row, ch_price_id) {
    // if (shouldUseOriginalCalc($row, form, ch_price_id)) {
    //     applyOriginalProfit($row);
    //     return $.Deferred().resolve().promise();
    // }

    const platform_id = form.find('.platform_id').val();
    const $discountInput = getDiscountInputForRow(form, ch_price_id);
    const discount_price = parseFloat($discountInput.val()) || 0;
    const cost_basis = getCostBasis(form, ch_price_id);
    const shipping_cost = getShippingValue(form, ch_price_id);

    return $.ajax({
        url: CALC_URL,
        type: 'POST',
        data: {
            platform_id,
            discount_price,
            ch_price_id,
            cost_basis,
            shipping_cost,
            _token: CSRF,
        },
    }).done(function (response) {
        console.log("response", response);
        applyProfitResponse($row, response);
    });
}

function recalculateAllRows(form) {
    const department_id = form.find('.department_id').val();
    const requests = [];

    if (isGroupedDept(department_id)) {
        const $row = form.find('tbody tr.discount-calc-row').first();
        const ch_price_id = getRowPriceId($row);
        if (ch_price_id) requests.push(calculateProfitForRow(form, $row, ch_price_id));
    } else {
        form.find('tbody tr.discount-calc-row').each(function () {
            const $row = $(this);
            const ch_price_id = getRowPriceId($row);
            if (ch_price_id) requests.push(calculateProfitForRow(form, $row, ch_price_id));
        });
    }

    return requests.length ? $.when.apply($, requests) : $.Deferred().resolve().promise();
}

function recalculateDiscountRow($context) {
    const form = $context.closest('form');
    const department_id = form.find('.department_id').val();

    if (isGirlsBoysDept(department_id) && $context.hasClass('cost-basis-radio')) {
        return recalculateAllRows(form);
    }

    if (isGroupedDept(department_id)) {
        return recalculateAllRows(form);
    }

    const $row = $context.closest('tr.discount-calc-row');
    const ch_price_id = getRowPriceId($row.length ? $row : $context);
    if (!ch_price_id) return;

    return calculateProfitForRow(form, $row.length ? $row : form, ch_price_id);
}

function syncCostBasisRadios($radio) {
    const form = $radio.closest('form');
    const basis = $radio.val();
    form.find('.cost-basis-radio').each(function () {
        $(this).prop('checked', $(this).val() === basis);
    });
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

    $(document).on('change keyup', '.discount_price', function () {
        let input = $(this);
        let form  = input.closest('form');
        let csp   = parseFloat(input.data('csp')) || 0;

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

        recalculateDiscountRow(input);
    });

    $(document).on('change', '.cost-basis-radio', function () {
        syncCostBasisRadios($(this));
        recalculateAllRows($(this).closest('form'));
    });

    $(document).on('change keyup', '.shipping-cost-input', function () {
        recalculateDiscountRow($(this));
    });

    function validateDiscountForm($form, options = {}) {
        const requireDiscount = options.requireDiscount !== false;
        let saveType   = $form.find('.save_type').val();
        let hasError   = false;
        let hasDiscount = false;
        const department_id = $form.find('.department_id').val();
        const isGrouped = isGroupedDept(department_id) || isGirlsBoysDept(department_id);
        const groupStatus = $form.find('.group-status-toggle').prop('checked');

        $form.find('.discount_price').removeClass('is-invalid').next('.custom-error').remove();

        $form.find('.discount_price').each(function () {
            const val = $(this).val().trim();
            if (val) {
                hasDiscount = true;
            }
        });

        if (requireDiscount && !hasDiscount) {
            return { valid: false, hasDiscount: false };
        }

        $form.find('.discount_price').each(function () {
            const val = $(this).val().trim();
            if (val !== '' && isNaN(val)) {
                hasError = true;
                $(this).addClass('is-invalid');
            }
        });

        if (hasError) return { valid: false, hasDiscount };

        if (isGrouped && saveType == 2 && groupStatus) {
            Swal.fire({
                title: 'Invalid Status',
                text: 'All items must have Status OFF for Approval.',
                icon: 'error'
            });
            return { valid: false, hasDiscount };
        }

        if (isGrouped && saveType == 3 && !groupStatus) {
            Swal.fire({
                title: 'Invalid Status',
                text: 'All items must have Status ON for Executor.',
                icon: 'error'
            });
            return { valid: false, hasDiscount };
        }

        if (!isGrouped) {
            let invalidStatus = false;
            $form.find('input[name^="statuses"]:checked').each(function () {
                if (saveType == 2) invalidStatus = true;
            });
            $form.find('input[name^="statuses"]').each(function () {
                const id = $(this).attr('name').match(/\[(\d+)\]/);
                if (!id) return;
                const chVal = id[1];
                const $discount = $form.find('.discount_price' + chVal);
                if ($discount.length && $discount.val().trim() && !$(this).prop('checked') && saveType == 3) {
                    invalidStatus = true;
                }
            });
            if (invalidStatus) {
                Swal.fire({
                    title: 'Invalid Status',
                    text: saveType == 2
                        ? 'Selected items must have Status OFF for Approval.'
                        : 'Selected items must have Status ON for Executor.',
                    icon: 'error'
                });
                return { valid: false, hasDiscount };
            }
        }

        return { valid: true, hasDiscount };
    }

    // save summary
    function parseFormRowProfit($row) {
        return {
            pm: ($row.find('.pm').first().text() || '').trim(),
            np: ($row.find('.np').first().text() || '').trim(),
        };
    }

    function buildDiscountSummaryLine(discountPrice, pmText, npText) {
        return (
            '<div class="plat-line plat-line-dis">' +
                '<span class="plat-type plat-type-dis">Dis</span>' +
                '<span class="plat-val"><span class="plat-key">CSP</span> £' + parseFloat(discountPrice).toFixed(2) + '</span>' +
                '<span class="plat-sep">·</span>' +
                '<span class="plat-val"><span class="plat-key">PM</span> ' + pmText + '</span>' +
                '<span class="plat-sep">·</span>' +
                '<span class="plat-val"><span class="plat-key">NP</span> ' + npText + '</span>' +
            '</div>'
        );
    }

    function updatePlatCardSummary($platCard, discountPrice, pmText, npText) {
        if (!$platCard.length) return;

        $platCard.find('.plat-line-dis').remove();

        if (discountPrice > 0) {
            $platCard.addClass('plat-card-disc');
            $platCard.append(buildDiscountSummaryLine(discountPrice, pmText, npText));
            return;
        }

        $platCard.removeClass('plat-card-disc');
    }

    function getSummaryUpdatesForForm($form) {
        const department_id = $form.find('.department_id').val();
        const updates = [];

        if (isGroupedDept(department_id)) {
            const $firstRow = $form.find('tbody tr.discount-calc-row').first();
            const primaryId = getRowPriceId($firstRow);
            const discount_price = parseFloat(getDiscountInputForRow($form, primaryId).val()) || 0;
            const profit = parseFormRowProfit($firstRow);

            $form.find('input.ch_price_id').each(function () {
                updates.push({
                    ch_price_id: $(this).val(),
                    discount_price,
                    profit,
                });
            });
        } else {
            $form.find('tbody tr.discount-calc-row').each(function () {
                const $row = $(this);
                const ch_price_id = getRowPriceId($row);
                updates.push({
                    ch_price_id,
                    discount_price: parseFloat(getDiscountInputForRow($form, ch_price_id).val()) || 0,
                    profit: parseFormRowProfit($row),
                });
            });
        }

        return updates;
    }

    function updateDiscountSummaryForForm($form) {
        const $card = $form.closest('.discount-card');
        const $summary = $card.find('.discount-summary-strip');
        if (!$summary.length) return;

        const platform_id = $form.find('.platform_id').val();

        getSummaryUpdatesForForm($form).forEach(({ ch_price_id, discount_price, profit }) => {
            const $platCard = $summary
                .find('.discount-summary-color[data-price-id="' + ch_price_id + '"]')
                .find('.plat-card[data-platform-id="' + platform_id + '"]');

            updatePlatCardSummary($platCard, discount_price, profit.pm, profit.np);
        });
    }
    // save summary

    function submitDiscountForm($form) {
        return $.ajax({
            url:  SAVE_URL || $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
    }

    function isVisiblePlatformForm($form) {
        const $panel = $form.closest('[x-show]');
        return !$panel.length || $panel.is(':visible');
    }

    function saveAllDiscountForms($scope) {
        const $forms = $scope.find('.pp-form').filter(function () {
            return isVisiblePlatformForm($(this));
        });
        if (!$forms.length) return;

        const formsToSave = [];
        let hasAnyDiscount = false;

        for (let i = 0; i < $forms.length; i++) {
            const $form = $($forms[i]);
            const result = validateDiscountForm($form, { requireDiscount: false });
            if (!result.valid) return;

            if (result.hasDiscount) {
                hasAnyDiscount = true;
                formsToSave.push($form);
            }
        }

        if (!hasAnyDiscount) {
            Swal.fire({ title: 'No Discount', text: 'Enter at least one discount price to save.', icon: 'warning' });
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to save discounts for all platforms?',
            icon: 'question',
            showCancelButton:   true,
            confirmButtonText:  'Yes, Save it!',
            cancelButtonText:   'Cancel',
            confirmButtonColor: '#3085d6',
            cancelButtonColor:  '#d33'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const $btn = $scope.find('.save-all-discounts-btn');
            const btnHtml = $btn.html();
            $btn.html(window.loader || 'Saving...').prop('disabled', true);

            const requests = formsToSave.map(($form) => submitDiscountForm($form));

            $.when
                .apply($, requests)
                .done(function () {
                    formsToSave.forEach(($form) =>
                        updateDiscountSummaryForForm($form),
                    );

                    iziToast.success({
                        title: "Saved",
                        message: "Discount prices updated successfully.",
                        position: "topRight",
                        timeout: 2000,
                    });
                })
                .fail(function (xhr) {
                    const msg =
                        xhr?.responseJSON?.message ||
                        "Something went wrong. Please try again.";
                    Swal.fire({ icon: "error", title: "Error", text: msg });
                })
                .always(function () {
                    $btn.html(btnHtml).prop("disabled", false);
                });
        });
    }

    $(document).on('submit', '.pp-form', function (e) {
        e.preventDefault();
    });

    $(document).on('click', '.save-all-discounts-btn', function () {
        const $scope = $(this).closest('.discount-edit-panel');
        saveAllDiscountForms($scope);
    });

    $(document).on('change', '.toggle-column', function () {
        const target = $(this).val();
        const panel  = $(this).closest('.discount-edit-panel');
        panel.find('.toogle-item.' + target).toggle(this.checked);
    });

});

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
