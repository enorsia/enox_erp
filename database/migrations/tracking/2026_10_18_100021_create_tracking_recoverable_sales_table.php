<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::create('tracking_recoverable_sales', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->foreignId('tracking_session_id')->constrained('tracking_session')->cascadeOnDelete();
            $table->unsignedTinyInteger('type')
                ->comment('3=add_to_cart, 4=begin_checkout, 5=proceed_checkout, 6=payment_success (session latest stage)');
            $table->unsignedInteger('qty')->default(0);
            $table->decimal('sale_value', 12, 2)->default(0);
            $table->timestamp('occurred_at')->nullable()->comment('Time of the stage action, shown as "ago"');
            $table->timestamps();

            $table->unique(['metric_date', 'tracking_session_id'], 'uq_trs_date_session');
            $table->index(['metric_date', 'type', 'occurred_at'], 'trs_date_type_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_recoverable_sales');
    }
};
