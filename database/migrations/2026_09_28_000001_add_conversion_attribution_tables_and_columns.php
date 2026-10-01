<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->string('conversion_utm_source', 100)->nullable()->after('landing_page');
            $table->string('conversion_utm_medium', 100)->nullable()->after('conversion_utm_source');
            $table->string('conversion_utm_campaign', 100)->nullable()->after('conversion_utm_medium');
            $table->text('conversion_landing_page')->nullable()->after('conversion_utm_campaign');
            $table->timestamp('conversion_touch_captured_at')->nullable()->after('conversion_landing_page');

            $table->index('conversion_utm_source', 'idx_activity_ecom_user_conversion_source');
        });

        Schema::table('activity_ecom_orders', function (Blueprint $table) {
            $table->string('conversion_utm_source', 100)->nullable()->after('ordered_at');
            $table->string('conversion_utm_medium', 100)->nullable()->after('conversion_utm_source');
            $table->string('conversion_utm_campaign', 100)->nullable()->after('conversion_utm_medium');
            $table->text('conversion_landing_page')->nullable()->after('conversion_utm_campaign');
            $table->timestamp('conversion_touch_captured_at')->nullable()->after('conversion_landing_page');

            $table->index('conversion_utm_source', 'idx_orders_conversion_source');
        });

        Schema::create('visitor_last_paid_touch', function (Blueprint $table) {
            $table->string('visitor_id', 64)->primary();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->text('landing_page')->nullable();
            $table->string('click_param', 64)->nullable();
            $table->timestamp('captured_at');
            $table->string('source_kind', 16)->default('server');
            $table->timestamps();

            $table->index('captured_at', 'idx_visitor_last_paid_touch_captured');
        });

        Schema::create('attribution_touch_log', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 64);
            $table->string('session_id', 64)->nullable();
            $table->uuid('ingest_event_id')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->text('landing_page')->nullable();
            $table->boolean('qualifies_paid')->default(false);
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['visitor_id', 'captured_at'], 'idx_attribution_touch_log_visitor_captured');
            $table->index('captured_at', 'idx_attribution_touch_log_captured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribution_touch_log');
        Schema::dropIfExists('visitor_last_paid_touch');

        Schema::table('activity_ecom_orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_conversion_source');
            $table->dropColumn([
                'conversion_utm_source',
                'conversion_utm_medium',
                'conversion_utm_campaign',
                'conversion_landing_page',
                'conversion_touch_captured_at',
            ]);
        });

        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropIndex('idx_activity_ecom_user_conversion_source');
            $table->dropColumn([
                'conversion_utm_source',
                'conversion_utm_medium',
                'conversion_utm_campaign',
                'conversion_landing_page',
                'conversion_touch_captured_at',
            ]);
        });
    }
};
