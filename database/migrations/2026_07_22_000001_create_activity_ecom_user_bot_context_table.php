<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_user_bot_context', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->unique();
            $table->ipAddress('client_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_country', 8)->nullable();
            $table->string('cf_ray', 64)->nullable();
            $table->smallInteger('cf_bot_score')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->string('bot_confidence', 16)->nullable();
            $table->string('bot_reason')->nullable();
            $table->timestamps();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('activity_ecom_user')
                ->cascadeOnDelete();

            $table->index('is_bot', 'activity_ecom_user_bot_context_is_bot_idx');
            $table->index('ip_country', 'activity_ecom_user_bot_context_ip_country_idx');
            $table->index('client_ip', 'activity_ecom_user_bot_context_client_ip_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_user_bot_context');
    }
};
