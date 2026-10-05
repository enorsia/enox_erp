<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_traffic_source', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('medium', 100);
            $table->timestamps();

            $table->unique('name', 'uq_tracking_traffic_source_name');
            $table->index('medium', 'tracking_traffic_source_medium_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_traffic_source');
    }
};
