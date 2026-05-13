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
        'is_spent',
        'is_sales',
        'allows_direct_entry',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_spent' => 'boolean',
            'is_sales' => 'boolean',
            'allows_direct_entry' => 'boolean',
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

        if (isset($filters['is_spent']) && $filters['is_spent'] !== '' && $filters['is_spent'] !== null) {
            $query->where('is_spent', (bool) $filters['is_spent']);
        }

        if (isset($filters['is_sales']) && $filters['is_sales'] !== '' && $filters['is_sales'] !== null) {
            $query->where('is_sales', (bool) $filters['is_sales']);
        }

        if (isset($filters['allows_direct_entry']) && $filters['allows_direct_entry'] !== '' && $filters['allows_direct_entry'] !== null) {
            $query->where('allows_direct_entry', (bool) $filters['allows_direct_entry']);
        }

        return $query;
    }
}

