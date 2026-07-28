// ============================================
// MODULE: Style Stock Report
// ============================================

import { Fancybox } from '@fancyapps/ui';
import '@fancyapps/ui/dist/fancybox/fancybox.css';
import { prepareFancyboxPublicLinks } from './fancybox-public-url';

(function () {
    "use strict";

    function getStyleStockData() {
        const el = document.getElementById("enox_style_stock_report");
        if (!el || !el.dataset.styleStocks) return {};
        try {
            return JSON.parse(el.dataset.styleStocks);
        } catch (e) {
            return {};
        }
    }

    function init() {
        const $ = window.$;
        if (!$ || !document.getElementById("enox_style_stock_report")) return;

        const styleStockData = getStyleStockData();

        function resetToggleIcons() {
            $(".ssr-chevron").removeClass("ssr-chevron--open");
        }

        function hideAllSubRows() {
            $(".category-row, .product-row").addClass("hidden");
            resetToggleIcons();
        }

        function populateCategoryOptions(deptKey) {
            const $categorySelect = $("#search_category");
            $categorySelect
                .empty()
                .append('<option value="">-- Select Category --</option>');

            if (!deptKey || !styleStockData[deptKey]) {
                $categorySelect.prop("disabled", true);
                return;
            }

            $.each(styleStockData[deptKey].categories, function (catKey, category) {
                $categorySelect.append(
                    $("<option>", { value: catKey, text: category.category_name }),
                );
            });

            $categorySelect.prop("disabled", false);
        }

        function expandDepartment(deptKey) {
            $(".department-" + deptKey).removeClass("hidden");
            $('.department-row[data-target="department-' + deptKey + '"]')
                .find(".ssr-chevron")
                .addClass("ssr-chevron--open");
        }

        function expandCategory(deptKey, catKey) {
            $(".category-" + deptKey + "-" + catKey).removeClass("hidden");
            $(
                '.category-row[data-target="category-' +
                    deptKey +
                    "-" +
                    catKey +
                    '"]',
            )
                .find(".ssr-chevron")
                .addClass("ssr-chevron--open");
        }

        $(document).on("change", "#search_department", function () {
            const deptKey = $(this).val();

            $("#search_product").val("");
            hideAllSubRows();
            populateCategoryOptions(deptKey);

            if (deptKey) {
                expandDepartment(deptKey);
            }
        });

        $(document).on("change", "#search_category", function () {
            const catKey = $(this).val();
            const deptKey = $("#search_department").val();

            if (!deptKey) return;

            $(".product-row").addClass("hidden");
            $(".category-row .ssr-chevron").removeClass("ssr-chevron--open");

            if (catKey) {
                expandCategory(deptKey, catKey);
            }
        });

        function searchByProductCode() {
            const code = $("#search_product").val().trim().toLowerCase();

            if (!code) return;

            hideAllSubRows();

            let foundDept = null;
            let foundCat = null;

            $.each(styleStockData, function (deptKey, department) {
                if (foundDept) return;

                $.each(department.categories, function (catKey, category) {
                    if (foundDept) return;

                    $.each(category.products, function (i, product) {
                        if (
                            product.item_no &&
                            product.item_no.toLowerCase().indexOf(code) !== -1
                        ) {
                            foundDept = deptKey;
                            foundCat = catKey;
                            return false;
                        }
                    });
                });
            });

            if (foundDept) {
                $("#search_department").val(foundDept);
                populateCategoryOptions(foundDept);
                $("#search_category").val(foundCat);

                expandDepartment(foundDept);
                expandCategory(foundDept, foundCat);

                const $matchedRow = $(
                    ".category-" + foundDept + "-" + foundCat,
                ).filter(function () {
                    return $(this).text().toLowerCase().indexOf(code) !== -1;
                });

                if ($matchedRow.length) {
                    $("html, body").animate(
                        { scrollTop: $matchedRow.offset().top - 150 },
                        400,
                    );
                    $matchedRow.addClass("ssr-row--highlight");
                    setTimeout(function () {
                        $matchedRow.removeClass("ssr-row--highlight");
                    }, 2000);
                }
            } else {
                $("#search_department").val("");
                $("#search_category")
                    .empty()
                    .append('<option value="">-- Select Category --</option>')
                    .prop("disabled", true);
                alert(
                    "No product found with code: " + $("#search_product").val(),
                );
            }
        }

        $(document).on("click", "#search_product_btn", searchByProductCode);

        $(document).on("keypress", "#search_product", function (e) {
            if (e.which === 13) {
                e.preventDefault();
                searchByProductCode();
            }
        });

        $(document).on("click", ".department-row", function () {
            const target = $(this).data("target");
            const $targets = $("." + target);

            $targets.toggleClass("hidden");

            if ($targets.hasClass("hidden")) {
                $targets.each(function () {
                    const categoryTarget = $(this).data("target");
                    $("." + categoryTarget).addClass("hidden");
                    $(this)
                        .find(".ssr-chevron")
                        .removeClass("ssr-chevron--open");
                });
            }

            $(this).find(".ssr-chevron").toggleClass("ssr-chevron--open");
        });

        $(document).on("click", ".category-row", function () {
            const target = $(this).data("target");
            $("." + target).toggleClass("hidden");
            $(this).find(".ssr-chevron").toggleClass("ssr-chevron--open");
        });

        prepareFancyboxPublicLinks('[data-fancybox^="gallery-style-stock-"]');
        Fancybox.bind('[data-fancybox^="gallery-style-stock-"]', {
            closeButton: 'top',
            Thumbs: {
                type: 'classic',
            },
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
