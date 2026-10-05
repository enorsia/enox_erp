<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class TrackingSyncTestSchema
{
    public static function up(): void
    {
        Schema::dropIfExists('tracking_session_p_cat');
        Schema::dropIfExists('tracking_session_details');
        Schema::dropIfExists('tracking_session');
        Schema::dropIfExists('tracking_daily_visitor');
        Schema::dropIfExists('tracking_main_visitor');
        Schema::dropIfExists('tracking_category');
        Schema::dropIfExists('tracking_product');
        Schema::dropIfExists('tracking_traffic_source');
        Schema::dropIfExists('tracking_browser');
        Schema::dropIfExists('tracking_device');
        Schema::dropIfExists('activity_ecom_user_actions');
        Schema::dropIfExists('activity_ecom_user');

        Schema::create('activity_ecom_user', function (Blueprint $table) {
            $table->id();
            $table->char('session_id', 36)->unique();
            $table->char('visitor_id', 36)->nullable();
            $table->string('user_name', 255)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('ip', 45)->nullable();
            $table->boolean('is_logged_in')->default(false);
            $table->unsignedInteger('session_duration_seconds')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('has_payment_success')->default(false);
            $table->unsignedTinyInteger('is_sync')->default(0);
            $table->unsignedSmallInteger('sync_try')->default(0);
            $table->timestamp('sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_ecom_user_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('session_id', 36);
            $table->string('action_type', 64);
            $table->string('category_name', 255)->nullable();
            $table->string('category_code', 100)->nullable();
            $table->string('department_name', 255)->nullable();
            $table->string('product_name', 500)->nullable();
            $table->string('product_code', 100)->nullable();
            $table->string('sku', 100)->nullable();
            $table->text('referer')->nullable();
            $table->json('add_to_cart')->nullable();
            $table->json('proceed_to_checkout')->nullable();
            $table->unsignedTinyInteger('is_sync')->default(0);
            $table->timestamp('sync_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('tracking_device', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('tracking_browser', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('tracking_traffic_source', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('medium', 100);
            $table->timestamps();
            $table->unique(['name', 'medium']);
        });

        Schema::create('tracking_product', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('sku', 100)->nullable();
            $table->string('title', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('tracking_category', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 100)->nullable();
            $table->string('name', 255);
            $table->timestamps();
        });

        Schema::create('tracking_main_visitor', function (Blueprint $table) {
            $table->id();
            $table->char('visitor_id', 36)->unique();
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('tracking_daily_visitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_main_visitor_id')->constrained('tracking_main_visitor')->cascadeOnDelete();
            $table->char('daily_visitor_id', 32);
            $table->date('visit_date');
            $table->timestamps();
            $table->unique(['tracking_main_visitor_id', 'visit_date']);
        });

        Schema::create('tracking_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_daily_visitor_id')->constrained('tracking_daily_visitor')->cascadeOnDelete();
            $table->char('session_id', 36)->unique();
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_logged_in')->default(false);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedTinyInteger('latest_funnel_stage')->nullable();
            $table->boolean('has_order')->default(false);
            $table->timestamps();
        });

        Schema::create('tracking_session_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->unique()->constrained('tracking_session')->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->foreignId('tracking_device_id')->nullable()->constrained('tracking_device')->nullOnDelete();
            $table->foreignId('tracking_browser_id')->nullable()->constrained('tracking_browser')->nullOnDelete();
            $table->foreignId('tracking_traffic_source_id')->nullable()->constrained('tracking_traffic_source')->nullOnDelete();
            $table->text('referer')->nullable();
            $table->timestamps();
        });

        Schema::create('tracking_session_p_cat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->constrained('tracking_session')->cascadeOnDelete();
            $table->foreignId('tracking_product_id')->nullable()->constrained('tracking_product')->nullOnDelete();
            $table->foreignId('tracking_category_id')->nullable()->constrained('tracking_category')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tracking_session_id', 'tracking_product_id', 'tracking_category_id'], 'uq_session_product_category');
        });
    }
}
