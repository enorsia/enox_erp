<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::table('tracking_hourly_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'stay_seconds',
                'cart_sessions',
                'checkout_sessions',
                'proceed_sessions',
                'payments',
                'items_sold',
                'sale_amount',
            ]);
        });

        Schema::table('tracking_hourly_summaries', function (Blueprint $table) {
            $table->decimal('product_sale_amount', 12, 2)->default(0)->after('product_paid_units');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_hourly_summaries', function (Blueprint $table) {
            $table->dropColumn('product_sale_amount');
        });

        Schema::table('tracking_hourly_summaries', function (Blueprint $table) {
            $table->unsignedBigInteger('stay_seconds')->default(0)->after('sessions');
            $table->unsignedInteger('cart_sessions')->default(0)->after('product_paid_units');
            $table->unsignedInteger('checkout_sessions')->default(0)->after('cart_sessions');
            $table->unsignedInteger('proceed_sessions')->default(0)->after('checkout_sessions');
            $table->unsignedInteger('payments')->default(0)->after('proceed_sessions');
            $table->unsignedInteger('items_sold')->default(0)->after('payments');
            $table->decimal('sale_amount', 12, 2)->default(0)->after('items_sold');
        });
    }
};
