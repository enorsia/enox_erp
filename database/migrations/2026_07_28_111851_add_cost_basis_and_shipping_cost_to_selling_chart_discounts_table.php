<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('selling_chart_discounts', function (Blueprint $table) {
            $table->string('cost_basis', 10)->default('unit')->after('status');
            $table->decimal('shipping_cost', 10, 2)->nullable()->after('cost_basis');
        });
    }

    public function down(): void
    {
        Schema::table('selling_chart_discounts', function (Blueprint $table) {
            $table->dropColumn(['cost_basis', 'shipping_cost']);
        });
    }
};
