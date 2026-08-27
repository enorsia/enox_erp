<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CommerceTestSchema
{
    public static function up(): void
    {
        Schema::dropIfExists('activity_ecom_commerce_line_items');
        Schema::dropIfExists('activity_ecom_orders');
        Schema::dropIfExists('activity_ecom_user_actions');
        Schema::dropIfExists('activity_ecom_user');

        Schema::create('activity_ecom_user', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->unique();
            $table->string('visitor_id', 64)->nullable();
            $table->boolean('has_add_to_cart')->default(false);
            $table->boolean('has_begin_checkout')->default(false);
            $table->boolean('has_proceed_checkout')->default(false);
            $table->boolean('has_payment_success')->default(false);
            $table->decimal('max_order_value', 14, 2)->nullable();
            $table->timestamp('first_payment_at')->nullable();
            $table->string('latest_funnel_stage', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('activity_ecom_user_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('session_id', 64);
            $table->string('action_type', 64);
            $table->json('payment_success')->nullable();
            $table->json('add_to_cart')->nullable();
            $table->json('begin_checkout')->nullable();
            $table->json('proceed_to_checkout')->nullable();
            $table->decimal('commerce_total', 14, 2)->nullable();
            $table->decimal('commerce_subtotal', 14, 2)->nullable();
            $table->decimal('commerce_shipping', 14, 2)->nullable();
            $table->decimal('commerce_discount', 14, 2)->nullable();
            $table->string('coupon_code', 100)->nullable();
            $table->string('discount_type', 20)->nullable();
            $table->unsignedSmallInteger('line_count')->nullable();
            $table->string('order_id', 191)->nullable();
            $table->decimal('amount_paid', 14, 2)->nullable();
            $table->unsignedInteger('item_qty')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_ecom_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 191)->unique();
            $table->string('order_pk', 191)->nullable();
            $table->uuid('event_id')->unique();
            $table->string('session_id', 64);
            $table->string('visitor_id', 64)->nullable();
            $table->decimal('amount_paid', 14, 2);
            $table->decimal('subtotal', 14, 2)->nullable();
            $table->decimal('grand_total', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->unsignedInteger('item_qty')->nullable();
            $table->decimal('shipping_charge', 14, 2)->nullable();
            $table->decimal('service_charge', 14, 2)->nullable();
            $table->decimal('extra_charges_total', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->nullable();
            $table->decimal('coupon_discount', 14, 2)->nullable();
            $table->decimal('scs_discount', 14, 2)->nullable();
            $table->decimal('sms_discount', 14, 2)->nullable();
            $table->string('discount_type', 20)->nullable();
            $table->string('coupon_code', 100)->nullable();
            $table->string('customer_email', 255)->nullable();
            $table->string('customer_phone', 64)->nullable();
            $table->timestamp('ordered_at');
            $table->timestamps();
        });

        Schema::create('activity_ecom_commerce_line_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('session_id', 64);
            $table->string('visitor_id', 64)->nullable();
            $table->string('funnel_stage', 30);
            $table->string('order_id', 191)->nullable();
            $table->unsignedSmallInteger('line_no');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_code', 255)->nullable();
            $table->string('sku', 255)->nullable();
            $table->string('product_name', 500)->nullable();
            $table->string('department_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('category_code', 255)->nullable();
            $table->string('color_name', 255)->nullable();
            $table->string('size_name', 255)->nullable();
            $table->decimal('qty', 14, 2);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('line_total', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('product_snapshot_json')->nullable();
            $table->timestamp('staged_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['event_id', 'line_no']);
            $table->unique(['order_id', 'line_no']);
        });
    }

    public static function down(): void
    {
        Schema::dropIfExists('activity_ecom_commerce_line_items');
        Schema::dropIfExists('activity_ecom_orders');
        Schema::dropIfExists('activity_ecom_user_actions');
        Schema::dropIfExists('activity_ecom_user');
    }
}
