<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user_actions', function (Blueprint $table) {
            $table->index(['action_type', 'created_at'], 'aeua_action_type_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user_actions', function (Blueprint $table) {
            $table->dropIndex('aeua_action_type_created_at_idx');
        });
    }
};
