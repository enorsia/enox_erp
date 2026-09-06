<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Ghost users (is_system = 1):
 * - Full admin access (all permissions)
 * - Hidden from admin user list
 * - Actions never written to activity_log
 * - Not editable/deletable via admin UI
 *
 * Enable on an existing user (MySQL):
 *   UPDATE users SET is_system = 1 WHERE email = 'director@company.com';
 *
 * Or create directly (generate hash: php artisan tinker --execute="echo bcrypt('secret');"):
 *   INSERT INTO users (name, email, password, status, is_system, created_at, updated_at)
 *   VALUES ('Board Director', 'director@company.com', '$2y$...', 1, 1, NOW(), NOW());
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('status');
            $table->index('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_system']);
            $table->dropColumn('is_system');
        });
    }
};
