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
        Schema::table('sale_platforms', function (Blueprint $table) {
            $table->boolean('is_spent')->default(true)->after('is_active');
            $table->boolean('is_sales')->default(true)->after('is_spent');
            $table->boolean('allows_direct_entry')->default(true)->after('is_sales');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_platforms', function (Blueprint $table) {
            $table->dropColumn(['is_spent', 'is_sales', 'allows_direct_entry']);
        });
    }
};

