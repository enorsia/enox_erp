<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->index('user_email', 'idx_aeus_user_email');
            $table->index('user_phone', 'idx_aeus_user_phone');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropIndex('idx_aeus_user_email');
            $table->dropIndex('idx_aeus_user_phone');
        });
    }
};
