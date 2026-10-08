<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_daily_visitor_id')->constrained('tracking_daily_visitor')->cascadeOnDelete();
            $table->char('session_id', 36)->unique();
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_logged_in')->default(false);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('actions_count')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedTinyInteger('latest_funnel_stage')
                ->nullable()
                ->comment('1=category_view, 2=product_view, 3=add_to_cart, 4=begin_checkout, 5=proceed_checkout, 6=payment_success');
            $table->boolean('has_order')->default(false);
            $table->timestamps();

            $table->index(['latest_funnel_stage', 'last_active_at', 'id'], 'ts_sort_funnel_idx');
            $table->index(['last_active_at', 'id', 'has_order'], 'ts_sort_last_active_idx');
            $table->index(['created_at', 'id'], 'ts_sort_created_idx');
            $table->index(['actions_count', 'id'], 'ts_sort_actions_idx');
            $table->index(['duration_seconds', 'id'], 'ts_sort_duration_idx');
            $table->index(['has_order', 'last_active_at', 'id'], 'ts_sort_has_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_session');
    }
};
