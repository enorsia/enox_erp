<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE monthly_budgets CHANGE budget budget_approved DECIMAL(12,2) NOT NULL DEFAULT 0');

        Schema::table('monthly_budgets', function (Blueprint $table) {
            $table->decimal('budget_requested', 12, 2)->default(0)->after('budget_approved');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_budgets', function (Blueprint $table) {
            $table->dropColumn('budget_requested');
        });

        DB::statement('ALTER TABLE monthly_budgets CHANGE budget_approved budget DECIMAL(12,2) NOT NULL DEFAULT 0');
    }
};
