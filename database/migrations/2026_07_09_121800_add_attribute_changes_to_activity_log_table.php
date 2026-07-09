<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable($tableName)) {
            return;
        }

        if (! $schema->hasColumn($tableName, 'attribute_changes')) {
            $schema->table($tableName, function (Blueprint $table) {
                $table->text('attribute_changes')->nullable()->after('properties');
            });
        }
    }

    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable($tableName)) {
            return;
        }

        if ($schema->hasColumn($tableName, 'attribute_changes')) {
            $schema->table($tableName, function (Blueprint $table) {
                $table->dropColumn('attribute_changes');
            });
        }
    }
};
