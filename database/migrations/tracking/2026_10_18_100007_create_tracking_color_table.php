<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::create('tracking_color', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->nullable();
            $table->string('name', 255);
            $table->timestamps();

            $table->unique('name', 'uq_tracking_color_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_color');
    }
};
