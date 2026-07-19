<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->string('visitor_id', 64)->nullable()->after('session_id');
            $table->unsignedInteger('session_duration_seconds')->nullable()->after('last_active_at');

            $table->index('visitor_id', 'idx_visitor_id');
            $table->index(['visitor_id', 'created_at'], 'idx_visitor_created');
            $table->index('last_active_at', 'idx_last_active');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropIndex('idx_visitor_id');
            $table->dropIndex('idx_visitor_created');
            $table->dropIndex('idx_last_active');
            $table->dropColumn(['visitor_id', 'session_duration_seconds']);
        });
    }
};
