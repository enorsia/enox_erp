<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellingChartExpensesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('selling_chart_expenses', function (Blueprint $table) {
            $table->id();
            $table->year('year')->index();
            $table->decimal('conversion_rate', 8, 2);
            $table->decimal('commercial_expense', 8, 2);
            $table->decimal('enorsia_expense_bd', 8, 2);
            $table->decimal('enorsia_expense_uk', 8, 2);
            $table->decimal('shipping_cost', 8, 2)->nullable();
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('selling_chart_expenses');
    }
}
