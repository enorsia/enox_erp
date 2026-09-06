<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user_actions', function (Blueprint $table) {
            $table->decimal('commerce_total', 14, 2)->nullable()->after('product_price');
            $table->decimal('commerce_subtotal', 14, 2)->nullable()->after('commerce_total');
            $table->decimal('commerce_shipping', 14, 2)->nullable()->after('commerce_subtotal');
            $table->decimal('commerce_discount', 14, 2)->nullable()->after('commerce_shipping');
            $table->string('coupon_code', 100)->nullable()->after('commerce_discount');
            $table->string('discount_type', 20)->nullable()->after('coupon_code');
            $table->unsignedSmallInteger('line_count')->nullable()->after('discount_type');
            $table->string('order_id', 191)->nullable()->after('line_count');
            $table->decimal('amount_paid', 14, 2)->nullable()->after('order_id');
            $table->unsignedInteger('item_qty')->nullable()->after('amount_paid');

            $table->index('coupon_code', 'idx_aeua_coupon_code');
            $table->index('order_id', 'idx_aeua_order_id');
            $table->index(['action_type', 'commerce_total'], 'idx_aeua_action_commerce_total');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user_actions', function (Blueprint $table) {
            $table->dropIndex('idx_aeua_coupon_code');
            $table->dropIndex('idx_aeua_order_id');
            $table->dropIndex('idx_aeua_action_commerce_total');
            $table->dropColumn([
                'commerce_total',
                'commerce_subtotal',
                'commerce_shipping',
                'commerce_discount',
                'coupon_code',
                'discount_type',
                'line_count',
                'order_id',
                'amount_paid',
                'item_qty',
            ]);
        });
    }
};
