<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::table('tracking_daily_products', function (Blueprint $table) {
            $table->unsignedBigInteger('tracking_category_id')->nullable()->after('product_code');
            $table->index(['metric_date', 'tracking_category_id'], 'tdp_date_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_daily_products', function (Blueprint $table) {
            $table->dropIndex('tdp_date_category_idx');
            $table->dropColumn('tracking_category_id');
        });
    }
};
