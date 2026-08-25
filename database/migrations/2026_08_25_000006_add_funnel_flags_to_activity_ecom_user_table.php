<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->boolean('has_add_to_cart')->default(false)->after('is_logged_in');
            $table->boolean('has_begin_checkout')->default(false)->after('has_add_to_cart');
            $table->boolean('has_proceed_checkout')->default(false)->after('has_begin_checkout');
            $table->boolean('has_payment_success')->default(false)->after('has_proceed_checkout');
            $table->decimal('max_order_value', 14, 2)->nullable()->after('has_payment_success');
            $table->timestamp('first_payment_at')->nullable()->after('max_order_value');
            $table->string('latest_funnel_stage', 30)->nullable()->after('first_payment_at');
            $table->boolean('is_bot')->nullable()->after('latest_funnel_stage');

            $table->index(['has_payment_success', 'created_at'], 'idx_aeus_has_payment_created');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_user', function (Blueprint $table) {
            $table->dropIndex('idx_aeus_has_payment_created');
            $table->dropColumn([
                'has_add_to_cart',
                'has_begin_checkout',
                'has_proceed_checkout',
                'has_payment_success',
                'max_order_value',
                'first_payment_at',
                'latest_funnel_stage',
                'is_bot',
            ]);
        });
    }
};
