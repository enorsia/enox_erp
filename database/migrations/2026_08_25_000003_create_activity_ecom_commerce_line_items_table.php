<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->unique(['event_id', 'line_no'], 'uq_line_event');
            $table->unique(['order_id', 'line_no'], 'uq_paid_order_line');
            $table->index('event_id', 'idx_line_event_id');
            $table->index('session_id', 'idx_line_session_id');
            $table->index('visitor_id', 'idx_line_visitor_id');
            $table->index('funnel_stage', 'idx_line_funnel_stage');
            $table->index('order_id', 'idx_line_order_id');
            $table->index('product_code', 'idx_line_product_code');
            $table->index('sku', 'idx_line_sku');
            $table->index('department_name', 'idx_line_department_name');
            $table->index('category_name', 'idx_line_category_name');
            $table->index('category_code', 'idx_line_category_code');
            $table->index('staged_at', 'idx_line_staged_at');
            $table->index(['funnel_stage', 'product_code', 'staged_at'], 'idx_line_funnel_product_date');
            $table->index(['session_id', 'staged_at'], 'idx_line_session_staged');
            $table->fullText('product_name', 'ft_line_product_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_commerce_line_items');
    }
};
