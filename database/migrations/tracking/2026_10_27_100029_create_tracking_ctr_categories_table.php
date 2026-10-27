<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::create('tracking_ctr_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->comment('1=mens, 2=womens, 3=boys, 4=girls');
            $table->string('type', 32)->comment('static, dynamic_custom_link, category, micro_category');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('category_slug', 255)->nullable();
            $table->timestamps();

            $table->index('department_id', 'idx_tracking_ctr_categories_department');
            $table->index('type', 'idx_tracking_ctr_categories_type');
            $table->index('category_id', 'idx_tracking_ctr_categories_category');
            $table->index('category_slug', 'idx_tracking_ctr_categories_category_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_ctr_categories');
    }
};
