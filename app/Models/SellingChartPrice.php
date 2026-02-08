<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellingChartPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'basic_info_id',
        'color_id',
        'color_code',
        'color_name',
        'size_id',
        'size',
        'range_id',
        'range',
        'po_order_qty',
        'price_fob',
        'unit_price',
        'product_shipping_cost',
        'confirm_selling_price',
        'vat_price',
        'vat_value',
        'profit_margin',
        'net_profit',
        'discount',
        'discount_selling_price',
        'discount_vat_price',
        'discount_vat_value',
        'discount_profit_margin',
        'discount_net_profit'
    ];

    public function sellingChartBasicInfo()
    {
        return $this->belongsTo(SellingChartBasicInfo::class, 'basic_info_id', "id");
    }
}
