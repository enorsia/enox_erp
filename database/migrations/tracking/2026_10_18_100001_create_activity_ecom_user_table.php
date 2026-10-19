<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::create('activity_ecom_user', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->unique();
            $table->string('visitor_id', 64)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_phone', 50)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->text('landing_page')->nullable();
            $table->boolean('is_logged_in')->default(false);
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedInteger('session_duration_seconds')->nullable();
            $table->unsignedInteger('actions_count')->default(0);
            $table->decimal('max_order_value', 14, 2)->nullable();
            $table->timestamp('first_payment_at')->nullable();
            $table->timestamps();
            $table->unsignedTinyInteger('is_sync')->default(0)->comment('0=pending, 1=done, 2=fail');
            $table->unsignedSmallInteger('sync_try')->default(0);
            $table->timestamp('sync_at')->nullable();

            $table->index('user_id');
            $table->index('created_at');
            $table->index('visitor_id', 'idx_visitor_id');
            $table->index(['visitor_id', 'created_at'], 'idx_visitor_created');
            $table->index('last_active_at', 'idx_last_active');
            $table->index('user_email', 'idx_aeus_user_email');
            $table->index('user_phone', 'idx_aeus_user_phone');
            $table->index(['created_at', 'actions_count'], 'idx_aeus_created_actions_count');
            $table->index(['is_sync', 'id'], 'aeau_is_sync_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_user');
    }
};
