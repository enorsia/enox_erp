<?php

namespace App\Services\Shop;

/**
 * Platform rollups for daily_sales_summary (matches Enorsia EnoxTrackingController).
 */
final class ShopDailySalesMetrics
{
    public const TOTAL_QTY_SQL = '(COALESCE(dss.enorsia_total_qty, 0)
        + COALESCE(dss.debenhams_total_qty, 0)
        + COALESCE(dss.amazon_uk_total_qty, 0)
        + COALESCE(dss.amazon_eu_total_qty, 0)
        + COALESCE(dss.spartoo_uk_total_qty, 0)
        + COALESCE(dss.spartoo_fr_total_qty, 0)
        + COALESCE(dss.temu_total_qty, 0))';

    public const TOTAL_SALE_SQL = '(COALESCE(dss.enorsia_total_sale, 0)
        + COALESCE(dss.debenhams_total_sale, 0)
        + COALESCE(dss.amazon_uk_total_sale, 0)
        + COALESCE(dss.amazon_eu_total_sale, 0)
        + COALESCE(dss.spartoo_uk_total_sale, 0)
        + COALESCE(dss.spartoo_fr_total_sale, 0)
        + COALESCE(dss.temu_total_sale, 0))';
}