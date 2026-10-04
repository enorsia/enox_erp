<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_daily_visitor_id')->constrained('tracking_daily_visitor')->cascadeOnDelete();
            $table->char('session_id', 36)->unique();
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_logged_in')->default(false);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedTinyInteger('latest_funnel_stage')->nullable();
            $table->boolean('has_order')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_session');
    }
};
