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
        Schema::create('tracking_daily_audiences', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->unsignedTinyInteger('type')->comment('1=device, 2=browser, 3=traffic_source');
            $table->unsignedBigInteger('dimension_id')
                ->comment('tracking_device.id / tracking_browser.id / tracking_traffic_source.id by type');
            $table->string('source', 100)->default('')->comment('Traffic source only');
            $table->string('medium', 100)->default('')->comment('Traffic source only');
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('add_to_carts')->default(0);
            $table->unsignedInteger('begin_checkouts')->default(0);
            $table->unsignedInteger('proceed_checkouts')->default(0);
            $table->unsignedInteger('payments')->default(0)->comment('Sessions with payment success');
            $table->unsignedInteger('sold_qty')->default(0);
            $table->decimal('sale_amount', 12, 2)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0)->comment('payments / sessions * 100 for the day');
            $table->timestamps();

            $table->unique(['metric_date', 'type', 'dimension_id', 'source', 'medium'], 'uq_tda_date_type_dimension');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_daily_audiences');
    }
};
