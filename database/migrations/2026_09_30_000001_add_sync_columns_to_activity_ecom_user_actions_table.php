<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user_actions', function (Blueprint $table) {
            $table->unsignedTinyInteger('sync_status')
                ->default(0)
                ->after('created_at')
                ->comment('0=pending, 1=synced, 2=failed');

            $table->unsignedSmallInteger('sync_attempts')
                ->default(0)
                ->after('sync_status');

            $table->timestamp('sync_claimed_at')
                ->nullable()
                ->after('sync_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user_actions', function (Blueprint $table) {
            $table->dropColumn(['sync_status', 'sync_attempts', 'sync_claimed_at']);
        });
    }
};
