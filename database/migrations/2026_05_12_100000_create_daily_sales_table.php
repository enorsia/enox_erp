<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_platform_id')->constrained('sale_platforms')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('spent', 12, 2)->default(0);
            $table->decimal('sales', 12, 2)->default(0);
            $table->unsignedInteger('number_of_orders')->default(0);
            $table->unsignedInteger('number_of_quantities')->default(0);
            $table->unsignedInteger('number_of_male_orders')->default(0);
            $table->unsignedInteger('number_of_female_orders')->default(0);
            $table->unsignedInteger('number_of_kids_orders')->default(0);
            $table->unsignedInteger('number_of_male_quantities')->default(0);
            $table->unsignedInteger('number_of_female_quantities')->default(0);
            $table->unsignedInteger('number_of_kids_quantities')->default(0);
            $table->timestamps();

            $table->unique(['sale_platform_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales');
    }
};

