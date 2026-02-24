<script>
    /*************** dropdown select2 ********/
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    document.addEventListener('DOMContentLoaded', function() {
        const advanceInput = document.getElementById('advance_search');
        const collapseEl = document.getElementById('collapseAdvance');
        if (advanceInput) {
            collapseEl.addEventListener('shown.bs.collapse', function() {
                advanceInput.value = 1;
            });

            collapseEl.addEventListener('hidden.bs.collapse', function() {
                advanceInput.value = 0;
            });
        }
    });

    $(document).ready(function() {

        /******* Add more row to table *********/
        $('.add_more_btn').on('click', function(e) {
            e.preventDefault();
            var newRow = $('table tbody tr:first').clone();
            newRow.find('input').val('');
            newRow.find('.x_price_fob').removeAttr('readonly');
            $('table tbody').append(newRow);
        });

        /******* delete row from table *********/
        $(document).on('click', 'table .delete-row', function() {
            if ($('table tbody tr').length > 1) {
                $(this).closest('tr').remove();
            } else {
                alert("You cannot delete the only row!");
            }
        });

        // custom error show remove
        $(document).on('change, input', '.ctmr', function() {
            if (!$(this).val()) {
                $(this).after(`<label class="error">This field is required.</label>`);
                valid = false;
            } else {
                $(this).siblings('.error').remove();
            }
        });
        // custom error show remove

        $('#product_design').on('input', function() {
            let capitalizedText = $(this).val().toLowerCase().replace(/\b\w/g, function(char) {
                return char.toUpperCase();
            });
            $(this).val(capitalizedText);
        });

        /******* Form validation *********/
        $('#selling_chart').validate({
            ignore: [],
            errorClass: 'is-invalid',
            validClass: 'is-valid',
            errorElement: 'div',
            errorPlacement: function(error, element) {
                if (element.hasClass('choices__input')) {
                    error.insertAfter(element.closest('.choices'));
                } else {
                    error.insertAfter(element);
                }
            },
            submitHandler: function(form) {

                // custom error show remove
                let valid = checkRequiredAfterSubmit();

                if (!valid) {
                    return false;
                }
                // custom error show remove

                $('.submit-btn').html(loader);
                $('.submit-btn').attr('disabled', true);
                setTimeout(function() {
                    form.submit();
                }, 400);

            }

        });

        $('#import_form').validate({
            ignore: [],
            errorClass: 'is-invalid',
            validClass: 'is-valid',
            errorElement: 'div',
            submitHandler: function(form) {
                $('.submit-btn').html(loader);
                $('.submit-btn').attr('disabled', true);
                setTimeout(function() {
                    form.submit();
                }, 400);

            }
        });

        $('#bulk_form').validate({
            submitHandler: function(form) {

                let isChecked = $('input[name="price_id[]"]:checked').length > 0;

                if (!isChecked) {
                    Swal.fire({
                        title: 'No Option Selected',
                        text: "Please select at least one price option before submitting.",
                        icon: 'warning',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'warning-text'
                        }
                    });
                    return false;
                }

                // custom error show remove
                let valid = checkRequiredAfterSubmit();

                if (!valid) {
                    return false;
                }
                // custom error show remove


                $('.submit-btn').html(loader);
                $('.submit-btn').attr('disabled', true);
                setTimeout(function() {
                    form.submit();
                }, 400);

            }
        });

        $(document).on('change keyup', '.discount_price', function() {
            let input = $(this);
            let form = input.closest('form');
            let platform_id = form.find('.platform_id').val();
            let department_id = form.find('.department_id').val();
            let ch_price_id = input.data('price-id');
            let csp = parseFloat(input.data('csp')) || 0;
            let tr = (department_id == 1928 || department_id == 1929) ? input.parents('tr') : form;

            let rawValue = input.val().trim();
            if (rawValue !== '' && isNaN(rawValue)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Input',
                    text: 'Please enter a numeric value only.'
                });

                input.val('');
                input.focus();
                return;
            }
            let discount_price = parseFloat(rawValue) || 0;


            if (discount_price > csp) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Discount',
                    text: 'Discount cannot exceed confirm selling price (£' + csp.toFixed(2) +
                        ')'
                });
                input.val(csp.toFixed(2));
                input.focus();
            }

            $.ajax({
                url: "{{ route('admin.selling_chart.calculate.platform.profit') }}",
                type: "POST",
                data: {
                    platform_id: platform_id,
                    discount_price: discount_price,
                    ch_price_id: ch_price_id,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    // console.log(response);
                    tr.find(".com").text('£' + response.commission.toFixed(2));
                    tr.find(".com-vat").text('£' + response.commission_vat.toFixed(2));
                    tr.find(".sp").text('£' + response.selling_price.toFixed(2));
                    tr.find(".sl-vat").text('£' + response.selling_vat.toFixed(2));
                    tr.find(".vat-val").text('£' + response.vat_value.toFixed(2));
                    tr.find(".sp-vat").text('£' + response.selling_price_and_vat.toFixed(
                        2));
                    tr.find(".pm").text(response.profit_margin.toFixed(2) + '%');
                    tr.find(".np").text('£' + response.net_profit.toFixed(2));
                }
            });

        });

        $(document).on('submit', '.pp-form', function(e) {
            let $form = $(this);
            let anyChecked = false;

            if ($form.hasClass('confirmed')) {
                return true;
            }

            e.preventDefault();
            $form.find('.discount_price').removeClass('is-invalid');

            $form.find('input[name="sl_price_id[]"]:checked').each(function() {
                anyChecked = true;
            });

            if (!anyChecked) {
                e.preventDefault();
                Swal.fire({
                    title: 'No Option Selected',
                    text: "Please select at least one price option before submitting.",
                    icon: 'warning'
                });
                return false;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to save this discount?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Save it!',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33'
            }).then((result) => {

                if (result.isConfirmed) {
                    $form.addClass('confirmed');

                    $form.find('.submit-btn')
                        .html(loader)
                        .prop('disabled', true);

                    $form.submit();

                }

            });

        });

    });

    function checkRequiredAfterSubmit() {
        let valid = true;
        $('.ctmr').each(function() {
            if (!$(this).val()) {
                if ($(this).hasClass('x_color_code')) {
                    $(this).siblings('.error').remove();
                    $(this).siblings('.color').after(
                        `<label class="error">This field is required.</label>`);
                } else {
                    $(this).siblings('.error').remove();
                    $(this).after(
                        `<label class="error">This field is required.</label>`);
                }
                valid = false;
            } else {
                $(this).siblings('.error').remove();
            }
        });

        return valid;
    }

    const baseUrl = "{{ url('/admin/selling-chart/get-size-range') }}";
    const CatbaseUrl = "{{ url('admin/selling-chart/get-dep-wise-cats') }}";
    const ColorbaseUrl = "{{ url('/admin/selling-chart/get-color-by-search') }}";

    $(document).on('input', '.color', function() {
        let val = $(this).val();
        let colorBox = $(this).parent().find('.color-box');
        if (val) {
            $.ajax({
                type: 'GET',
                url: ColorbaseUrl + '/' + val,
                success: function(data) {
                    // console.log(data);
                    colorBox.html(data);
                },
                error: function(data) {
                    console.log('Something went wrong.' + data);
                }
            });
        } else {
            colorBox.html('');
        }
    });

    function setColor(e, id, name, code) {
        let colorBox = $(e.target).parents('.color-box');
        colorBox.siblings('.color').val($(e.target).text());
        colorBox.siblings('.x_color_id').val(id);
        colorBox.siblings('.x_color_name').val(name);
        colorBox.siblings('.x_color_code').val(code);
        colorBox.siblings('.error').remove();
        colorBox.html('');
        // console.log(id, name, code);
    }

    function approveData(id, action = 'approve') {
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
                let form = document.getElementById('approve-form-' + id);
                let input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'action_type';
                input.value = action
                form.appendChild(input);
                form.submit();
            }
        });
    }

    let productCategoryChoices = null;

    function initProductCategoryChoices() {
        const select = document.querySelector('#product_category');
        if (!select) return;
        if (!productCategoryChoices) {
            productCategoryChoices = new Choices(select, {
                shouldSort: false,
                searchEnabled: true,
                allowHTML: true
            });
        }
    }

    initProductCategoryChoices();

    $('#department_select').change(function() {
        const id = $(this).val();

        let url = baseUrl + '/' + id;
        let cat_url = CatbaseUrl + '/' + id;
        $.ajax({
            type: 'GET',
            url: cat_url,
            success: function(data) {
                const dataArray = Object.values(data || {});
                const choicesData = dataArray.map(item => ({
                    value: item.id,
                    label: `${item.name} (${item.category_code})`
                }));

                productCategoryChoices.clearChoices();
                productCategoryChoices.setChoices(choicesData, 'value', 'label', true);
            },
            error: function(data) {
                console.log('Something went wrong.' + data);
            }
        });

        if ($('.color-table').length > 0) {
            $.ajax({
                type: 'GET',
                url: url,
                success: function(data) {
                    // console.log(data);
                    $('.color-table').html(data);

                    if ($('.btn-invisible.invisible')) {
                        $('.btn-invisible').removeClass('invisible');
                    } else {
                        $('.btn-invisible').addClass('invisible');
                    }
                },
                error: function(data) {
                    console.log('Create fail');
                }
            });
        }
    });

    $(document).on('click', function(event) {
        if (!$(event.target).closest('.color, .color-box').length) {
            $('.color-box').html('');
        }
    });

    /******* Table price calculation  for create page*********/

    function createEditPriceCal($row) {
        // let selectedOption = $('#season_select option:selected');
        let selectedVal = $('#season_select').val();
        let selectedInput = $('.season-exp' + selectedVal);
        // console.log(selectedOption);
        let conversionRate = parseFloat(selectedInput.data('conversion-rate')) || 0;
        let commercialExpense = parseFloat(selectedInput.data('commercial-expense')) || 0;
        let enorsiaBDExpense = parseFloat(selectedInput.data('enorsia-bd-expense')) || 0;
        let enorsiaUKExpense = parseFloat(selectedInput.data('enorsia-uk-expense')) || 0;

        const priceFOB = parseFloat($row.find('.x_price_fob').val()) || 0;
        let unitPrice = 0;
        unitPrice = (priceFOB * conversionRate) + (commercialExpense + enorsiaBDExpense + enorsiaUKExpense);
        $row.find('.x_unit_price').val(unitPrice.toFixed(2));
        if (!priceFOB) $row.find('.x_unit_price').val(0.00);
    }

    $(document).on('input', '.create_selling_chart_tbl tbody .x_price_fob', function() {
        const $row = $(this).closest('tr');
        createEditPriceCal($row);
    });

    $(document).on('change', '#season_select', function() {
        let selectedValue = $(this).val();
        let lastTwoDigits = parseInt(selectedValue.slice(-2)) || 0;
        $('.create_selling_chart_tbl tbody tr').each(function() {
            let $row = $(this);
            createEditPriceCal($row);
        });
    });


    /******* Table price calculation  for edit page*********/

    $('.selling_chart_edit_table tbody').on('input', '.price_fob, .shipping_cost, .confirm_selling_price, .discount',
        function() {
            const $row = $(this).closest('tr');

            let expenseInput = $row.find('.expense_input');

            if (!expenseInput.val()) {
                Swal.fire({
                    title: 'Expense Not Found',
                    text: "Please insert expence first.",
                    icon: 'warning',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'warning-text'
                    }
                });
            }

            let conversionRate = parseFloat(expenseInput.data('conversion-rate')) || 0;
            let commercialExpense = parseFloat(expenseInput.data('commercial-expense')) || 0;
            let enorsiaBDExpense = parseFloat(expenseInput.data('enorsia-bd-expense')) || 0;
            let enorsiaUKExpense = parseFloat(expenseInput.data('enorsia-uk-expense')) || 0;
            let expenseShippingCost = parseFloat(expenseInput.data('shipping-cost')) || 0;

            let department = parseInt(expenseInput.data('department'));



            const priceFOB = parseFloat($row.find('.price_fob').val()) || 0;
            const shippingCost = parseFloat($row.find('.shipping_cost').val()) || 0;

            let unitPrice = 0;
            unitPrice = (priceFOB * conversionRate) + (commercialExpense + enorsiaBDExpense + enorsiaUKExpense +
                (shippingCost ? shippingCost : expenseShippingCost));
            $row.find('.unit_price').val(unitPrice.toFixed(2));

            const confirmSellingPrice = parseFloat($row.find('.confirm_selling_price').val()) || 0;

            let selingVatValue, selingVat;
            if (department == 1926 || department == 1927) {
                selingVatValue = (confirmSellingPrice * 20) / 120;
                selingVat = confirmSellingPrice - selingVatValue;
            } else {
                selingVatValue = 0;
                selingVat = confirmSellingPrice;
            }

            $row.find('.seling_vat').val(selingVat.toFixed(2));
            $row.find('.seling_vat_value').val(selingVatValue.toFixed(2));

            const profitMargin = ((selingVat - unitPrice) / selingVat) * 100;
            $row.find('.profit_margin').val(profitMargin.toFixed(2));

            const netProfit = selingVat - unitPrice;
            $row.find('.net_profit').val(netProfit.toFixed(2));


            // discount
            const discount = parseFloat($row.find('.discount').val()) || 0;

            const discountSellingPrice = confirmSellingPrice - (confirmSellingPrice * (discount / 100));
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



            const discountProfitMargin = ((sellingVatDedactPrice - unitPrice) / sellingVatDedactPrice) * 100;
            $row.find('.discount_profit_margin').val(discountProfitMargin.toFixed(2));

            const discountNetProfit = sellingVatDedactPrice - unitPrice;
            $row.find('.discount_net_profit').val(discountNetProfit.toFixed(2));
            // discount
        });



    function viewChart(id, page = 1) {
        let url = "{{ route('admin.selling_chart.view.single.chart', ':id') }}";
        url = url.replace(':id', id);
        $.ajax({
            type: 'GET',
            url: url,
            data: {
                page: page
            },
            success: function(response) {
                if (response.status == true) {
                    $('#viewSellingChartItemModal').remove();
                    $('.setViewSellingChartItemModal').html(response.data);
                    $('#viewSellingChartItemModal').appendTo("body");
                    $('#viewSellingChartItemModal').modal('show');
                }
            },
            error: function(data) {
                console.log('Something went wrong.' + data);
            }
        });
    }
    $(document).on('change', '.toggle-column', function() {
        const target = $(this).val();
        $('.' + target).toggle(this.checked);
    });
</script>
