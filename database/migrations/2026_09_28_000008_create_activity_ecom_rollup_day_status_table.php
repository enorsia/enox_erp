<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_rollup_day_status', function (Blueprint $table) {
            $table->date('metric_date')->primary();
            $table->string('status', 16)->default('pending');
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('rolled_up_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_rollup_day_status');
        });

        if (Schema::hasTable('activity_ecom_daily_site_metrics')) {
            $now = now();
            $dates = DB::table('activity_ecom_daily_site_metrics')->pluck('metric_date');

            foreach ($dates as $metricDate) {
                DB::table('activity_ecom_rollup_day_status')->insertOrIgnore([
                    'metric_date' => $metricDate,
                    'status' => 'success',
                    'rolled_up_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_rollup_day_status');
    }
};
