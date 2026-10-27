<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::create('tracking_ctr_categories_impression', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tracking_session_id');
            $table->unsignedBigInteger('tracking_ctr_category_id');
            $table->unsignedTinyInteger('type')->comment('0=impression, 1=grid-product-click');
            $table->string('product_code', 100);
            $table->boolean('ctr_rollup_status')->nullable();
            $table->timestamps();

            $table->index(['ctr_rollup_status', 'id'], 'idx_tcci_ctr_rollup_pending');
            $table->index('tracking_session_id', 'idx_tracking_ctr_cat_impression_session');
            $table->index('tracking_ctr_category_id', 'idx_tracking_ctr_cat_impression_category');
            $table->index('type', 'idx_tracking_ctr_cat_impression_type');
            $table->index('product_code', 'idx_tracking_ctr_cat_impression_product_code');
            $table->index(
                ['tracking_session_id', 'tracking_ctr_category_id', 'type', 'product_code'],
                'idx_tracking_ctr_cat_impression_session_cat_type_code',
            );

            $table->foreign('tracking_session_id', 'fk_tcci_tracking_session_id')
                ->references('id')
                ->on('tracking_session')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_ctr_categories_impression');
    }
};
