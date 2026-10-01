<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->index(
                ['created_at', 'list_traffic_utm_source'],
                'idx_aeus_created_list_traffic_source',
            );
            $table->index(['created_at', 'device_type'], 'idx_aeus_created_device_type');
            $table->index(['created_at', 'is_logged_in'], 'idx_aeus_created_logged_in');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropIndex('idx_aeus_created_list_traffic_source');
            $table->dropIndex('idx_aeus_created_device_type');
            $table->dropIndex('idx_aeus_created_logged_in');
        });
    }
};
