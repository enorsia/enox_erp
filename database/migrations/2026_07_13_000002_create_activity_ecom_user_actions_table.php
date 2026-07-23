<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_user_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('session_id', 64);
            $table->string('action_type', 191);
            $table->string('category_name')->nullable();
            $table->string('category_code', 191)->nullable();
            $table->string('product_name')->nullable();
            $table->string('product_code', 191)->nullable();
            $table->string('product_color_id', 191)->nullable();
            $table->string('product_color_code', 191)->nullable();
            $table->string('general_color_name')->nullable();
            $table->decimal('product_price', 11, 2)->nullable();
            $table->text('page_url')->nullable();
            $table->text('referer')->nullable();
            $table->json('add_to_cart')->nullable();
            $table->json('begin_checkout')->nullable();
            $table->json('proceed_to_checkout')->nullable();
            $table->json('payment_success')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('activity_ecom_user')
                ->cascadeOnDelete();

            $table->index(['session_id', 'action_type']);
            $table->index('product_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_user_actions');
    }
};
