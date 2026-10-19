<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::create('tracking_product', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100);
            $table->string('sku', 100)->nullable();
            $table->string('title', 500)->nullable();
            $table->timestamps();

            $table->unique(['code', 'sku'], 'uq_tracking_product_code_sku');
            $table->index('sku', 'tracking_product_sku_idx');
            $table->fullText('title', 'tracking_product_title_ft');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_product');
    }
};
