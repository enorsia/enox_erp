<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::table('tracking_daily_summaries', function (Blueprint $table) {
            $table->unsignedInteger('product_view_units')->default(0)->after('product_views');
            $table->unsignedInteger('product_add_units')->default(0)->after('product_view_units');
            $table->unsignedInteger('product_begin_units')->default(0)->after('product_add_units');
            $table->unsignedInteger('product_proceed_units')->default(0)->after('product_begin_units');
            $table->unsignedInteger('product_paid_units')->default(0)->after('product_proceed_units');
        });

        Schema::table('tracking_hourly_summaries', function (Blueprint $table) {
            $table->unsignedInteger('product_view_units')->default(0)->after('product_views');
            $table->unsignedInteger('product_add_units')->default(0)->after('product_view_units');
            $table->unsignedInteger('product_begin_units')->default(0)->after('product_add_units');
            $table->unsignedInteger('product_proceed_units')->default(0)->after('product_begin_units');
            $table->unsignedInteger('product_paid_units')->default(0)->after('product_proceed_units');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_hourly_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'product_view_units',
                'product_add_units',
                'product_begin_units',
                'product_proceed_units',
                'product_paid_units',
            ]);
        });

        Schema::table('tracking_daily_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'product_view_units',
                'product_add_units',
                'product_begin_units',
                'product_proceed_units',
                'product_paid_units',
            ]);
        });
    }
};
