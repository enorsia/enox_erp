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
            $table->boolean('show_in_analytics')->default(true)->after('allows_direct_entry');
            $table->boolean('show_in_sale_tracking')->default(true)->after('show_in_analytics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_platforms', function (Blueprint $table) {
            $table->dropColumn(['show_in_analytics', 'show_in_sale_tracking']);
        });
    }
};

