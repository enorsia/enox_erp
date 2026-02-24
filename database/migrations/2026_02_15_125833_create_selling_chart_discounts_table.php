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
        Schema::create('selling_chart_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selling_chart_price_id')
                ->constrained('selling_chart_prices')
                ->cascadeOnDelete();
            $table->foreignId('platform_id')
                ->constrained('platforms')
                ->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selling_chart_discounts');
    }
};
