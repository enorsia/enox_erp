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
                [
                    'created_at',
                    'has_add_to_cart',
                    'has_begin_checkout',
                    'has_proceed_checkout',
                    'has_payment_success',
                    'session_duration_seconds',
                    'visitor_id',
                ],
                'idx_aeus_created_dashboard_agg',
            );
            $table->index(['created_at', 'city', 'country'], 'idx_aeus_created_geo');
        });

        Schema::table('activity_ecom_commerce_line_items', function (Blueprint $table) {
            $table->index(
                ['staged_at', 'funnel_stage', 'session_id'],
                'idx_line_staged_funnel_session',
            );
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropIndex('idx_aeus_created_dashboard_agg');
            $table->dropIndex('idx_aeus_created_geo');
        });

        Schema::table('activity_ecom_commerce_line_items', function (Blueprint $table) {
            $table->dropIndex('idx_line_staged_funnel_session');
        });
    }
};
