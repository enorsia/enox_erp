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
        Schema::create('tracking_order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_order_id')->constrained('tracking_orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('type')->default(1)->comment('1=shipping, 2=billing');
            $table->string('line_1', 255)->nullable();
            $table->string('line_2', 255)->nullable();
            $table->string('town_city', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 100)->nullable();
            $table->timestamps();

            $table->unique(['tracking_order_id', 'type'], 'uq_toa_order_type');
            $table->index('postcode', 'toa_postcode_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_order_addresses');
    }
};
