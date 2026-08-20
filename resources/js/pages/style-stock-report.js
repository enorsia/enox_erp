// ============================================
// MODULE: Style Stock Report
// ============================================

import { Fancybox } from "@fancyapps/ui";
import "@fancyapps/ui/dist/fancybox/fancybox.css";
import { prepareFancyboxPublicLinks } from "./fancybox-public-url";

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

    function productHasDiscount(product) {
        const maxDiscount = product?.itemPrice?.maxDiscountPrice;
        return Number(maxDiscount) > 0;
    }

    function iterateProducts(products, callback) {
        if (!products) return;
        if (Array.isArray(products)) {
            products.forEach(callback);
            return;
        }
        Object.values(products).forEach(callback);
    }

    function buildDiscountSummary(data) {
        const departments = [];

        Object.entries(data || {}).forEach(([deptKey, department]) => {
            let deptDiscountCount = 0;
            const categories = [];

            Object.entries(department?.categories || {}).forEach(
                ([catKey, category]) => {
                    let catDiscountCount = 0;

                    iterateProducts(category?.products, (product) => {
                        if (productHasDiscount(product)) {
                            catDiscountCount++;
                        }
                    });

                    deptDiscountCount += catDiscountCount;

                    categories.push({
                        key: catKey,
                        name: category?.category_name || "—",
                        count: catDiscountCount,
                    });
                },
            );

            departments.push({
                key: deptKey,
                name: department?.department_name || "—",
                count: deptDiscountCount,
                categories,
            });
        });

        return departments;
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function renderDiscountSummary(data) {
        const $container = $("#ssr_discount_summary");
        const $badge = $("#ssr_discount_total_badge");

        if (!$container.length) return;

        const departments = buildDiscountSummary(data);
        const grandTotal = departments.reduce(
            (sum, dept) => sum + dept.count,
            0,
        );

        if ($badge.length) {
            $badge.text(grandTotal + " item" + (grandTotal === 1 ? "" : "s"));
        }

        if (!departments.length) {
            $container.html(
                '<p class="ssr-discount-empty">No department data available.</p>',
            );
            return;
        }

        const html = departments
            .map((dept) => {
                const categoryRows = dept.categories
                    .map(
                        (cat) =>
                            '<div class="ssr-discount-cat-row">' +
                            "<span>" +
                            escapeHtml(cat.name) +
                            "</span>" +
                            '<span class="ssr-discount-cat-count">' +
                            cat.count +
                            "</span>" +
                            "</div>",
                    )
                    .join("");

                return (
                    '<div class="ssr-discount-dept">' +
                    '<div class="ssr-discount-dept-header">' +
                    '<span class="ssr-discount-dept-name">' +
                    escapeHtml(dept.name) +
                    "</span>" +
                    '<span class="ssr-discount-dept-count">' +
                    dept.count +
                    " discount</span>" +
                    "</div>" +
                    '<div class="ssr-discount-cat-list">' +
                    categoryRows +
                    "</div>" +
                    "</div>"
                );
            })
            .join("");

        $container.html(html);
    }

    function init() {
        const $ = window.$;
        if (!$ || !document.getElementById("enox_style_stock_report")) return;

        const styleStockData = getStyleStockData();

        renderDiscountSummary(styleStockData);

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

            $.each(
                styleStockData[deptKey].categories,
                function (catKey, category) {
                    $categorySelect.append(
                        $("<option>", {
                            value: catKey,
                            text: category.category_name,
                        }),
                    );
                },
            );

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

        $(document).on("click", ".discount-btn", function () {
            let url = $(this).data("url");
            $.ajax({
                type: "GET",
                url: url,
                beforeSend: function () {
                    $("#discountContent").html(`
                <div class="flex flex-col items-center justify-center py-12">
                    <svg
                        class="w-10 h-10 animate-spin text-indigo-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>

                    <p class="mt-4 text-sm text-slate-500">
                        Fetching data ...
                    </p>
                </div>
            `);
                },
                success: function (response) {
                    if (response.status) {
                        $("#discountContent").html(response.data);
                        const ecomSku = $('p[identify="Ecom SKU"]')
                            .text()
                            .trim();
                        $("#setEcomSku").text("#"+ecomSku);
                    }
                },
                error: function () {
                    $("#discountContent").html(`
                <div class="py-8 text-center text-red-500">
                    Something went wrong.
                </div>
            `);
                },
            });
        });

        prepareFancyboxPublicLinks('[data-fancybox^="gallery-style-stock-"]');
        Fancybox.bind('[data-fancybox^="gallery-style-stock-"]', {
            closeButton: "top",
            Thumbs: {
                type: "classic",
            },
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
