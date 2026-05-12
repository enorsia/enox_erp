<?php

namespace App\Models;

use Carbon\Carbon;

class DailyReturn extends BaseModel
{
    protected $fillable = [
        'sale_platform_id',
        'return_reason_type_id',
        'date',
        'number_of_returns',
        'number_of_return_quantities',
        'number_of_male_returns',
        'number_of_female_returns',
        'number_of_kids_returns',
        'number_of_male_return_quantities',
        'number_of_female_return_quantities',
        'number_of_kids_return_quantities',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
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

    public function returnReasonType()
    {
        return $this->belongsTo(ReturnReasonType::class);
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['sale_platform_id'])) {
            $query->where('sale_platform_id', $filters['sale_platform_id']);
        }

        if (!empty($filters['return_reason_type_id'])) {
            $query->where('return_reason_type_id', $filters['return_reason_type_id']);
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

