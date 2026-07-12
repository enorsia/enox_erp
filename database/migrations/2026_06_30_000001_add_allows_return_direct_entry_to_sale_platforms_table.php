<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_platforms', function (Blueprint $table) {
            $table->boolean('allows_return_direct_entry')->default(true)->after('allows_direct_entry');
        });
    }

    public function down(): void
    {
        Schema::table('sale_platforms', function (Blueprint $table) {
            $table->dropColumn('allows_return_direct_entry');
        });
    }
};
