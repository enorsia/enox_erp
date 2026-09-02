<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->unsignedInteger('actions_count')->default(0)->after('session_duration_seconds');
            $table->index(['created_at', 'actions_count'], 'idx_aeus_created_actions_count');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropIndex('idx_aeus_created_actions_count');
            $table->dropColumn('actions_count');
        });
    }
};
