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
                ['created_at', 'has_payment_success', 'last_active_at', 'id'],
                'idx_aeus_created_funnel_sort',
            );
            $table->index(
                ['created_at', 'list_traffic_utm_source', 'list_traffic_utm_medium'],
                'idx_aeus_created_list_traffic_pair',
            );
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropIndex('idx_aeus_created_funnel_sort');
            $table->dropIndex('idx_aeus_created_list_traffic_pair');
        });
    }
};
