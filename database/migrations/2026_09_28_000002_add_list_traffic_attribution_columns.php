<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->string('list_traffic_utm_source', 100)->nullable()->after('conversion_touch_captured_at');
            $table->string('list_traffic_utm_medium', 100)->nullable()->after('list_traffic_utm_source');

            $table->index('list_traffic_utm_source', 'idx_activity_ecom_user_list_traffic_source');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropIndex('idx_activity_ecom_user_list_traffic_source');
            $table->dropColumn([
                'list_traffic_utm_source',
                'list_traffic_utm_medium',
            ]);
        });
    }
};
