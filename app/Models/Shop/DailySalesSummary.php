<?php

namespace App\Models\Shop;

use App\Models\Concerns\UsesEnoxDatabase;
use Illuminate\Database\Eloquent\Model;

class DailySalesSummary extends Model
{
    use UsesEnoxDatabase;

    protected $table = 'daily_sales_summary';

    public $timestamps = false;

    protected $casts = [
        'order_date' => 'date',
        'enorsia_total_qty' => 'integer',
        'debenhams_total_qty' => 'integer',
        'amazon_uk_total_qty' => 'integer',
        'amazon_eu_total_qty' => 'integer',
        'spartoo_uk_total_qty' => 'integer',
        'spartoo_fr_total_qty' => 'integer',
        'temu_total_qty' => 'integer',
        'enorsia_total_sale' => 'float',
        'debenhams_total_sale' => 'float',
        'amazon_uk_total_sale' => 'float',
        'amazon_eu_total_sale' => 'float',
        'spartoo_uk_total_sale' => 'float',
        'spartoo_fr_total_sale' => 'float',
        'temu_total_sale' => 'float',
    ];
}