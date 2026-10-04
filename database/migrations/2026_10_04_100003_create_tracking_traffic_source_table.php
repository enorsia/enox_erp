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

            $table->unique(['name', 'medium']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_traffic_source');
    }
};
