<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracking_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->nullable()->constrained('tracking_session')->nullOnDelete();
            $table->string('order_number', 50)->unique();
            $table->unsignedBigInteger('order_pk')->nullable()->comment('Store order primary key');
            $table->date('metric_date');
            $table->timestamp('ordered_at')->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->char('currency', 3)->default('GBP');
            $table->string('payment_method', 50)->nullable();
            $table->string('coupon_code', 100)->nullable();
            $table->unsignedInteger('items_qty')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('priority_charge', 12, 2)->default(0);
            $table->decimal('extra_handling_cost', 12, 2)->default(0);
            $table->decimal('extra_charges_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['metric_date', 'id'], 'to_date_idx');
            $table->index('email', 'to_email_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_orders');
    }
};
