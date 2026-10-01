<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class EcomDailyRollupSchema
{
    private static ?bool $commerceViewColumns = null;

    public static function resetCommerceViewColumnsProbe(): void
    {
        self::$commerceViewColumns = null;
    }

    public static function hasCommerceViewColumns(): bool
    {
        if (self::$commerceViewColumns !== null) {
            return self::$commerceViewColumns;
        }

        if (config('tracker.daily_rollups_commerce_view_columns') !== null) {
            return self::$commerceViewColumns = (bool) config('tracker.daily_rollups_commerce_view_columns');
        }

        self::$commerceViewColumns = Schema::hasColumn('activity_ecom_daily_product_metrics', 'view_count')
            && Schema::hasColumn('activity_ecom_daily_category_metrics', 'category_view_count')
            && Schema::hasColumn('activity_ecom_daily_site_metrics', 'category_view_count');

        return self::$commerceViewColumns;
    }

    private static ?bool $catalogIdColumns = null;

    public static function hasCatalogIdColumns(): bool
    {
        if (self::$catalogIdColumns !== null) {
            return self::$catalogIdColumns;
        }

        self::$catalogIdColumns = Schema::hasColumn('activity_ecom_commerce_line_items', 'tracker_product_id')
            && Schema::hasColumn('activity_ecom_daily_product_metrics', 'tracker_product_id');

        return self::$catalogIdColumns;
    }
}
