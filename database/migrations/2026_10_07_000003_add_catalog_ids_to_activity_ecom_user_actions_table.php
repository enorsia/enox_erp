<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user_actions', function (Blueprint $table) {
            $table->string('category_id', 50)->nullable()->after('category_code');
            $table->string('department_id', 50)->nullable()->after('department_name');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user_actions', function (Blueprint $table) {
            $table->dropColumn(['category_id', 'department_id']);
        });
    }
};
