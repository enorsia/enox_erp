<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_daily_visitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_main_visitor_id')->constrained('tracking_main_visitor')->cascadeOnDelete();
            $table->char('daily_visitor_id', 36)->unique();
            $table->date('visit_date');
            $table->timestamps();

            $table->unique(['tracking_main_visitor_id', 'visit_date'], 'uq_main_visitor_visit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_daily_visitor');
    }
};
