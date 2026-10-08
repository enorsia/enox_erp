<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_session_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->unique()->constrained('tracking_session')->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->foreignId('tracking_device_id')->nullable()->constrained('tracking_device')->nullOnDelete();
            $table->foreignId('tracking_browser_id')->nullable()->constrained('tracking_browser')->nullOnDelete();
            $table->foreignId('tracking_traffic_source_id')->nullable()->constrained('tracking_traffic_source')->nullOnDelete();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->text('landing_page')->nullable();
            $table->text('referer')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('ip', 'tsd_search_ip_idx');
            $table->index(['tracking_session_id', 'tracking_device_id', 'tracking_traffic_source_id'], 'tsd_filter_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_session_details');
    }
};
