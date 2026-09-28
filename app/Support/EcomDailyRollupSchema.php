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
}
