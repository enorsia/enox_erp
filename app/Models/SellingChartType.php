<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellingChartType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    public function sellingChartBasicInfos()
    {
        return $this->hasMany(SellingChartBasicInfo::class, 'mini_category');
    }
}
