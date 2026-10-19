<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::create('activity_ecom_user_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('session_id', 64);
            $table->string('action_type', 191);
            $table->string('category_id', 50)->nullable();
            $table->string('category_code', 191)->nullable();
            $table->string('category_name')->nullable();
            $table->string('department_id', 50)->nullable();
            $table->string('department_name')->nullable();
            $table->string('product_code', 191)->nullable();
            $table->string('product_name')->nullable();
            $table->string('sku', 191)->nullable();
            $table->string('product_color_id', 191)->nullable();
            $table->string('product_color_code', 191)->nullable();
            $table->string('general_color_name')->nullable();
            $table->decimal('product_price', 11, 2)->nullable();
            $table->decimal('amount_paid', 14, 2)->nullable();
            $table->decimal('commerce_discount', 14, 2)->nullable();
            $table->decimal('commerce_shipping', 14, 2)->nullable();
            $table->decimal('commerce_subtotal', 14, 2)->nullable();
            $table->decimal('commerce_total', 14, 2)->nullable();
            $table->string('coupon_code', 100)->nullable();
            $table->string('discount_type', 20)->nullable();
            $table->unsignedSmallInteger('line_count')->nullable();
            $table->unsignedInteger('item_qty')->nullable();
            $table->string('order_id', 191)->nullable();
            $table->text('page_url')->nullable();
            $table->text('referer')->nullable();
            $table->json('add_to_cart')->nullable();
            $table->json('begin_checkout')->nullable();
            $table->json('payment_success')->nullable();
            $table->json('proceed_to_checkout')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->boolean('ctr_tracking_status')->nullable();
            $table->unsignedTinyInteger('is_sync')->default(0)->comment('0=pending, 1=done, 2=fail');
            $table->unsignedSmallInteger('sync_try')->default(0);
            $table->timestamp('sync_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('activity_ecom_user')
                ->cascadeOnDelete();

            $table->index(['session_id', 'action_type']);
            $table->index('product_code');
            $table->index('created_at');
            $table->index('sku');
            $table->index(['action_type', 'created_at'], 'aeua_action_type_created_at_idx');
            $table->index('coupon_code', 'idx_aeua_coupon_code');
            $table->index('order_id', 'idx_aeua_order_id');
            $table->index(['action_type', 'commerce_total'], 'idx_aeua_action_commerce_total');
            $table->index(['is_sync', 'id'], 'aeua_is_sync_queue_idx');
            $table->index(['session_id', 'is_sync'], 'aeua_session_sync_idx');
            $table->index(['ctr_tracking_status', 'action_type', 'id'], 'idx_activity_ecom_actions_ctr_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_user_actions');
    }
};
