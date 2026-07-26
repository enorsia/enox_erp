<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->string('user_phone', 50)->nullable()->after('user_email');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropColumn('user_phone');
        });
    }
};
