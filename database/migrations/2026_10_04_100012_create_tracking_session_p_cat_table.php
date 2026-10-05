<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_session_p_cat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->constrained('tracking_session')->cascadeOnDelete();
            $table->foreignId('tracking_product_id')->nullable()->constrained('tracking_product')->nullOnDelete();
            $table->foreignId('tracking_category_id')->nullable()->constrained('tracking_category')->nullOnDelete();
            $table->foreignId('tracking_color_id')->nullable()->constrained('tracking_color')->nullOnDelete();
            $table->foreignId('tracking_size_id')->nullable()->constrained('tracking_size')->nullOnDelete();
            $table->timestamps();

            $table->index('tracking_session_id', 'tracking_session_p_cat_session_idx');
            $table->index(
                ['tracking_session_id', 'tracking_product_id', 'tracking_category_id'],
                'tracking_session_p_cat_session_product_category_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_session_p_cat');
    }
};
