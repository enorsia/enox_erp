<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingCtrSummary extends Model
{
    protected $table = 'tracking_ctr_summaries';

    protected $fillable = [
        'department_id',
        'category_id',
        'product_id',
        'product_code',
        'sku',
        'total_click',
        'total_impression',
        'ctr',
        'ctr_average',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'category_id' => 'integer',
            'product_id' => 'integer',
            'product_code' => 'string',
            'sku' => 'string',
            'total_click' => 'integer',
            'total_impression' => 'integer',
            'ctr' => 'decimal:6',
            'ctr_average' => 'decimal:6',
        ];
    }
}
