<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellingChartDiscountHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'basic_info_id',
        'items',
        'status',
        'created_by',
        'updated_by',
    ];
    public function sellingChartBasicInfo()
    {
        return $this->belongsTo(SellingChartBasicInfo::class, 'basic_info_id', "id");
    }
}
