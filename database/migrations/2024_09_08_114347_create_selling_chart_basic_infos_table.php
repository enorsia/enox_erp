<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellingChartBasicInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('selling_chart_basic_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->string('department_name')->nullable();
            $table->unsignedBigInteger('season_id');
            $table->string('season_name')->nullable();
            $table->unsignedBigInteger('phase_id');
            $table->string('phase_name')->nullable();
            $table->unsignedBigInteger('initial_repeated_id')->index();
            $table->string('initial_repeated_status');
            $table->string('product_launch_month', 150)->index();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('category_name')->nullable();
            $table->unsignedBigInteger('mini_category')->nullable();
            $table->string('mini_category_name')->nullable();
            $table->string('product_code', 150)->index();
            $table->string('design_no', 150)->index();
            $table->string('inspiration_image')->nullable();
            $table->string('design_image')->nullable();
            $table->text('product_description')->nullable();
            $table->unsignedBigInteger('fabrication_id')->index();
            $table->string('fabrication')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->timestamps();

            $table->foreign('mini_category')
                ->references('id')
                ->on('selling_chart_types');

            $table->index([
                'department_id',
                'season_id',
                'phase_id',
                'initial_repeated_id',
                'category_id',
                'mini_category',
                'fabrication_id',
                'product_code',
                'design_no',
                'product_launch_month'
            ], 'scbi_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('selling_chart_basic_infos');
    }
}
