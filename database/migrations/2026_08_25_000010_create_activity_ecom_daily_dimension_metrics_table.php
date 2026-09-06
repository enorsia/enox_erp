<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_daily_dimension_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->string('dimension_type', 64);
            $table->string('dimension_value', 500);
            $table->unsignedInteger('session_count')->default(0);
            $table->unsignedInteger('visitor_count')->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['metric_date', 'dimension_type', 'dimension_value'],
                'uq_dimension_metrics_date_type_value'
            );
            $table->index('metric_date', 'idx_dimension_metrics_date');
            $table->index(['dimension_type', 'dimension_value'], 'idx_dimension_metrics_type_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_daily_dimension_metrics');
    }
};
