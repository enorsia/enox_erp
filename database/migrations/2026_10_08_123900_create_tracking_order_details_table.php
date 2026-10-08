<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracking_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_order_id')->constrained('tracking_orders')->cascadeOnDelete();
            $table->foreignId('tracking_product_id')->nullable()->constrained('tracking_product')->nullOnDelete();
            $table->foreignId('tracking_category_id')->nullable()->constrained('tracking_category')->nullOnDelete();
            $table->foreignId('tracking_color_id')->nullable()->constrained('tracking_color')->nullOnDelete();
            $table->foreignId('tracking_size_id')->nullable()->constrained('tracking_size')->nullOnDelete();
            $table->string('item_variant', 100)->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['tracking_product_id', 'tracking_order_id'], 'tod_product_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_order_details');
    }
};
