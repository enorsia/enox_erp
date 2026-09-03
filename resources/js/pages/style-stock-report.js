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
        return (
            product?.has_discount === true ||
            product?.has_discount === 1 ||
            String(product?.has_discount) === "1"
        );
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
                    .map((cat) => {
                        const clickable =
                            cat.count > 0
                                ? ' ssr-discount-cat-link" data-dept-key="' +
                                  escapeHtml(dept.key) +
                                  '" data-cat-key="' +
                                  escapeHtml(cat.key) +
                                  '" data-count="' +
                                  cat.count
                                : '"';

                        return (
                            '<div class="ssr-discount-cat-row' +
                            clickable +
                            '">' +
                            "<span>" +
                            escapeHtml(cat.name) +
                            "</span>" +
                            '<span class="ssr-discount-cat-count">' +
                            cat.count +
                            "</span>" +
                            "</div>"
                        );
                    })
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

        function clearFilterHidden() {
            $(".department-row, .category-row, .product-row").removeClass(
                "ssr-filter-hidden",
            );
        }

        function getProductLocation($product) {
            const classes = ($product.attr("class") || "").split(/\s+/);
            const catClass = classes.find((cls) => cls.startsWith("category-"));

            if (!catClass) return null;

            const $catRow = $(
                '.category-row[data-target="' + catClass + '"]',
            );
            if (!$catRow.length) return null;

            const deptClass = ($catRow.attr("class") || "")
                .split(/\s+/)
                .find((cls) => cls.startsWith("department-"));

            if (!deptClass) return null;

            const deptKey = deptClass.replace("department-", "");
            const catKey = String($catRow.data("target")).replace(
                "category-" + deptKey + "-",
                "",
            );

            return { deptKey, catKey };
        }

        function productRowMatchesDiscountFilter($row, discountMode) {
            const hasDiscount =
                String($row.attr("data-has-discount") || "0") === "1";

            if (discountMode === "2") return !hasDiscount;
            if (discountMode === "3") return hasDiscount;
            return true;
        }

        function applyFilters() {
            const discountMode = $("#discount_status").val() || "1";
            const deptKey = $("#search_department").val();
            const catKey = $("#search_category").val();
            const productCode = $("#search_product").val().trim().toLowerCase();
            const hasActiveFilter =
                discountMode !== "1" || deptKey || catKey || productCode;
            const autoExpandProducts =
                !!catKey || !!productCode || discountMode !== "1";
            const autoExpandCategories =
                !!deptKey || !!catKey || !!productCode || discountMode !== "1";

            clearFilterHidden();
            hideAllSubRows();

            let firstMatch = null;

            $(".department-row").each(function () {
                const $deptRow = $(this);
                const deptTarget = $deptRow.data("target");
                const currentDeptKey = String(deptTarget).replace(
                    "department-",
                    "",
                );

                if (deptKey && deptKey !== currentDeptKey) {
                    $deptRow.addClass("ssr-filter-hidden hidden");
                    return;
                }

                $deptRow.removeClass("ssr-filter-hidden hidden");

                let deptVisible = false;

                $(".category-row." + deptTarget).each(function () {
                    const $catRow = $(this);
                    const catTarget = $catRow.data("target");
                    const currentCatKey = String(catTarget).replace(
                        "category-" + currentDeptKey + "-",
                        "",
                    );

                    if (catKey && catKey !== currentCatKey) {
                        $catRow.addClass("ssr-filter-hidden hidden");
                        return;
                    }

                    let catVisible = false;

                    $("tr.product-row." + catTarget).each(function () {
                        const $product = $(this);
                        let show = productRowMatchesDiscountFilter(
                            $product,
                            discountMode,
                        );

                        if (show && productCode) {
                            const itemNo = $product
                                .find(".ssr-product-link")
                                .text()
                                .trim()
                                .toLowerCase();
                            show = itemNo.indexOf(productCode) !== -1;
                        }

                        if (show) {
                            $product.removeClass("ssr-filter-hidden");

                            if (
                                autoExpandProducts &&
                                (!catKey || catKey === currentCatKey)
                            ) {
                                $product.removeClass("hidden");
                            } else {
                                $product.addClass("hidden");
                            }

                            catVisible = true;

                            if (!firstMatch) {
                                firstMatch = $product;
                            }
                        } else {
                            $product.addClass("ssr-filter-hidden hidden");
                        }
                    });

                    if (catVisible) {
                        $catRow.removeClass("ssr-filter-hidden");
                        deptVisible = true;

                        if (autoExpandCategories) {
                            $catRow.removeClass("hidden");
                        }

                        const shouldOpenCategory =
                            (catKey && catKey === currentCatKey) ||
                            (autoExpandProducts && !catKey);

                        if (shouldOpenCategory) {
                            $catRow
                                .find(".ssr-chevron")
                                .addClass("ssr-chevron--open");
                        }
                    } else {
                        $catRow.addClass("ssr-filter-hidden hidden");
                    }
                });

                if (!deptVisible && hasActiveFilter) {
                    $deptRow.addClass("ssr-filter-hidden hidden");
                } else if (deptVisible && autoExpandCategories) {
                    $deptRow
                        .find(".ssr-chevron")
                        .addClass("ssr-chevron--open");
                }
            });

            return firstMatch;
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

        $(document).on("change", "#search_department", function () {
            const deptKey = $(this).val();

            $("#search_product").val("");
            populateCategoryOptions(deptKey);
            applyFilters();
        });

        $(document).on("change", "#search_category", function () {
            applyFilters();
        });

        $(document).on("change", "#discount_status", function () {
            applyFilters();
        });

        function searchByProductCode() {
            const code = $("#search_product").val().trim().toLowerCase();

            if (!code) {
                applyFilters();
                return;
            }

            const firstMatch = applyFilters();

            if (firstMatch && firstMatch.length) {
                const $matchedRow = firstMatch;
                const location = getProductLocation($matchedRow);

                if (location) {
                    $("#search_department").val(location.deptKey);
                    populateCategoryOptions(location.deptKey);
                    $("#search_category").val(location.catKey);
                }

                applyFilters();

                $("html, body").animate(
                    { scrollTop: $matchedRow.offset().top - 150 },
                    400,
                );
                $matchedRow.addClass("ssr-row--highlight");
                setTimeout(function () {
                    $matchedRow.removeClass("ssr-row--highlight");
                }, 2000);
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

        $(document).on("click", ".ssr-discount-cat-link", function () {
            const deptKey = String($(this).data("deptKey") || "");
            const catKey = String($(this).data("catKey") || "");
            const count = parseInt($(this).data("count"), 10) || 0;

            if (!deptKey || !catKey || count <= 0) return;

            $("#discount_status").val("3");
            $("#search_product").val("");
            $("#search_department").val(deptKey);
            populateCategoryOptions(deptKey);
            $("#search_category").val(catKey);
            applyFilters();

            const $catRow = $(
                '.category-row[data-target="category-' +
                    deptKey +
                    "-" +
                    catKey +
                    '"]',
            );

            if ($catRow.length) {
                $("html, body").animate(
                    { scrollTop: $catRow.offset().top - 150 },
                    400,
                );
                $catRow.addClass("ssr-row--highlight");
                setTimeout(function () {
                    $catRow.removeClass("ssr-row--highlight");
                }, 2000);
            }
        });

        $(document).on("click", ".department-row", function () {
            if ($(this).hasClass("ssr-filter-hidden")) return;

            const target = $(this).data("target");
            const $targets = $("." + target).filter(":not(.ssr-filter-hidden)");

            $targets.toggleClass("hidden");

            if ($targets.hasClass("hidden")) {
                $targets.each(function () {
                    const categoryTarget = $(this).data("target");
                    $("." + categoryTarget)
                        .filter(":not(.ssr-filter-hidden)")
                        .addClass("hidden");
                    $(this)
                        .find(".ssr-chevron")
                        .removeClass("ssr-chevron--open");
                });
            }

            $(this).find(".ssr-chevron").toggleClass("ssr-chevron--open");
        });

        $(document).on("click", ".category-row", function () {
            if ($(this).hasClass("ssr-filter-hidden")) return;

            const target = $(this).data("target");
            $("." + target)
                .filter(":not(.ssr-filter-hidden)")
                .toggleClass("hidden");
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
