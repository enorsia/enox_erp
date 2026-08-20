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
        Schema::create('selling_chart_discount_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basic_info_id')->constrained('selling_chart_basic_infos')->onDelete('cascade');
            $table->json('items')->nullable();
            $table->tinyInteger('status')->default(0)->comment('0: Not applied, 1: applied');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selling_chart_discount_histories');
    }
};
