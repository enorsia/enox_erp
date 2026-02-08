<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellingChartPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('selling_chart_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('basic_info_id');
            $table->unsignedBigInteger('color_id');
            $table->string('color_code');
            $table->string('color_name');
            $table->unsignedBigInteger('size_id')->nullable();
            $table->string('size')->nullable();
            $table->unsignedBigInteger('range_id')->nullable();
            $table->string('range')->nullable();
            $table->integer('po_order_qty')->nullable();
            $table->decimal('price_fob', 8, 2)->comment('Price $ (FOB)');
            $table->decimal('unit_price', 8, 2)->comment('Unit Price £ (Factory FOB with Enorsia Expence)');
            $table->decimal('product_shipping_cost', 8, 2)->nullable();
            $table->decimal('confirm_selling_price', 8, 2)->nullable()->comment('GBP(£) [User input]');
            $table->decimal('vat_price', 8, 2)->nullable()->comment('GBP(£) [Auto generated]');
            $table->decimal('vat_value', 8, 2)->nullable()->comment('GBP(£) [Auto generated]');
            $table->decimal('profit_margin', 8, 2)->nullable()->comment('Profit Margin % (In Factory FOB)');
            $table->double('net_profit')->nullable()->comment('GBP(£) [Auto generated]');
            $table->double('discount')->nullable()->comment('GBP(£) percent(%) [User input]');
            $table->double('discount_selling_price')->nullable()->comment('GBP(£) [Auto generated]');
            $table->double('discount_vat_price')->nullable()->comment('GBP(£) [Auto generated]');
            $table->double('discount_vat_value')->nullable()->comment('GDP(£) [Auto generated]');
            $table->double('discount_profit_margin')->nullable()->comment('Profit Margin % (In Factory FOB)');
            $table->double('discount_net_profit')->nullable()->comment('GBP(£) [Auto generated]');
            $table->timestamps();
            $table->foreign('basic_info_id')
                ->references('id')
                ->on('selling_chart_basic_infos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('selling_chart_prices');
    }
}
