<?php

namespace App\Models;

use Carbon\Carbon;

class DailySale extends BaseModel
{
    protected $fillable = [
        'sale_platform_id',
        'date',
        'spent',
        'sales',
        'number_of_orders',
        'number_of_quantities',
        'number_of_male_orders',
        'number_of_female_orders',
        'number_of_kids_orders',
        'number_of_male_quantities',
        'number_of_female_quantities',
        'number_of_kids_quantities',
    ];

    protected function casts(): array
    {
        return [
            'date'  => 'date',
        ];
    }

    public function getCreatedAtAttribute($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function getUpdatedAtAttribute($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    // ── Relationships ─────────────────────────────────────────────

    public function salePlatform()
    {
        return $this->belongsTo(SalePlatform::class);
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['sale_platform_id'])) {
            $query->where('sale_platform_id', $filters['sale_platform_id']);
        }

        if (!empty($filters['date'])) {
            $query->where('date', $filters['date']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }
    }
}

