<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_daily_visitor_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->string('visitor_id', 64);
            $table->unsignedInteger('session_count')->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['metric_date', 'visitor_id'], 'uq_visitor_metrics_date_visitor');
            $table->index('metric_date', 'idx_visitor_metrics_date');
            $table->index('visitor_id', 'idx_visitor_metrics_visitor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_daily_visitor_metrics');
    }
};
