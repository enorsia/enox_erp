<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy commerce rollups and sync checkpoints removed in favor of the tracking_* schema.
     * Create migrations for these tables were deleted; this migration drops them on existing databases.
     */
    public function up(): void
    {
        Schema::dropIfExists('activity_ecom_commerce_line_items');
        Schema::dropIfExists('activity_ecom_orders');

        Schema::dropIfExists('activity_ecom_daily_site_metrics');
        Schema::dropIfExists('activity_ecom_daily_product_metrics');
        Schema::dropIfExists('activity_ecom_daily_category_metrics');
        Schema::dropIfExists('activity_ecom_daily_dimension_metrics');
        Schema::dropIfExists('activity_ecom_daily_visitor_metrics');
        Schema::dropIfExists('activity_ecom_daily_visitors');

        Schema::dropIfExists('tracker_backfill_checkpoints');
    }

    public function down(): void
    {
        // Intentionally empty — recreate via archived migrations if needed.
    }
};
