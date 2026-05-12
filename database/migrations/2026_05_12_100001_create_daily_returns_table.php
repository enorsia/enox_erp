<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_platform_id')->constrained('sale_platforms')->cascadeOnDelete();
            $table->foreignId('return_reason_type_id')->constrained('return_reason_types')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('number_of_returns')->default(0);
            $table->unsignedInteger('number_of_return_quantities')->default(0);
            $table->unsignedInteger('number_of_male_returns')->default(0);
            $table->unsignedInteger('number_of_female_returns')->default(0);
            $table->unsignedInteger('number_of_kids_returns')->default(0);
            $table->unsignedInteger('number_of_male_return_quantities')->default(0);
            $table->unsignedInteger('number_of_female_return_quantities')->default(0);
            $table->unsignedInteger('number_of_kids_return_quantities')->default(0);
            $table->timestamps();

            $table->unique(['sale_platform_id', 'date', 'return_reason_type_id']);
            $table->index('date');
            $table->index('return_reason_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_returns');
    }
};

