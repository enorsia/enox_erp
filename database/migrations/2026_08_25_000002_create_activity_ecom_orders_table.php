<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 191);
            $table->string('order_pk', 191)->nullable();
            $table->uuid('event_id');
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

            $table->unique('order_id', 'uq_orders_order_id');
            $table->unique('event_id', 'uq_orders_event_id');
            $table->index('order_pk', 'idx_orders_order_pk');
            $table->index('session_id', 'idx_orders_session_id');
            $table->index('visitor_id', 'idx_orders_visitor_id');
            $table->index('discount_type', 'idx_orders_discount_type');
            $table->index('coupon_code', 'idx_orders_coupon_code');
            $table->index('customer_email', 'idx_orders_customer_email');
            $table->index('customer_phone', 'idx_orders_customer_phone');
            $table->index('ordered_at', 'idx_orders_ordered_at');
            $table->index(['ordered_at', 'session_id'], 'idx_orders_ordered_session');
            $table->index(['visitor_id', 'ordered_at'], 'idx_orders_visitor_ordered');
            $table->index(['coupon_code', 'ordered_at'], 'idx_orders_coupon_ordered');

            $table->foreign('session_id', 'fk_orders_session_id')
                ->references('session_id')
                ->on('activity_ecom_user')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_orders');
    }
};
