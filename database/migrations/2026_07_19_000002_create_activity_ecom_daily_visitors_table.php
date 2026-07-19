<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_daily_visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 64);
            $table->date('visit_date');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('total_duration_seconds')->default(0);
            $table->unsignedInteger('session_count')->default(0);
            $table->timestamps();

            $table->unique(['visitor_id', 'visit_date'], 'uniq_visitor_date');
            $table->index('visit_date', 'idx_visit_date');
            $table->index('first_seen_at', 'idx_first_seen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_daily_visitors');
    }
};
