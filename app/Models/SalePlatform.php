<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalePlatform extends BaseModel
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'type',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getCreatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value) : null;
    }

    public function getUpdatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value) : null;
    }

    /**
     * Get parent platform
     */
    public function parent()
    {
        return $this->belongsTo(SalePlatform::class, 'parent_id');
    }

    /**
     * Get child platforms
     */
    public function children()
    {
        return $this->hasMany(SalePlatform::class, 'parent_id');
    }

    public function dailySales()
    {
        return $this->hasMany(DailySale::class);
    }

    public function dailyReturns()
    {
        return $this->hasMany(DailyReturn::class);
    }

    public function monthlyBudgets()
    {
        return $this->hasMany(MonthlyBudget::class);
    }

    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (isset($filters['type']) && $filters['type'] !== '' && $filters['type'] !== null) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query;
    }
}

