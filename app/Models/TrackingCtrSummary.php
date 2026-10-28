<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingCtrSummary extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_ctr_summaries';

    protected $fillable = [
        'department_id',
        'department_slug',
        'category_id',
        'category_slug',
        'type',
        'product_code',
        'total_click',
        'total_impression',
        'ctr',
        'ctr_average',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'department_slug' => 'string',
            'category_id' => 'integer',
            'category_slug' => 'string',
            'type' => 'string',
            'product_code' => 'string',
            'total_click' => 'integer',
            'total_impression' => 'integer',
            'ctr' => 'decimal:6',
            'ctr_average' => 'decimal:6',
        ];
    }
}
