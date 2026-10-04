<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activity_ecom_rollup_day_status');
    }

    public function down(): void
    {
        // Recreate via 2026_09_28_000008 if rollback is required.
    }
};
