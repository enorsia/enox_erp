<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('tracking_category')->nullOnDelete();
            $table->string('code', 100)->nullable();
            $table->string('name', 255);
            $table->timestamps();

            $table->unique(['parent_id', 'name'], 'uq_tracking_category_parent_name');
            $table->index('code', 'tracking_category_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_category');
    }
};
