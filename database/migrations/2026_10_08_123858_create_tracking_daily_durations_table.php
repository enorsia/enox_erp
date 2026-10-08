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
        Schema::create('tracking_daily_durations', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->unsignedTinyInteger('bucket')->comment('TrackingDailyDuration::BUCKETS key');
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedBigInteger('duration_seconds')->default(0)->comment('Total duration of the sessions in this bucket');
            $table->timestamps();

            $table->unique(['metric_date', 'bucket'], 'uq_tdd_date_bucket');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_daily_durations');
    }
};
