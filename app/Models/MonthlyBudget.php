<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_platform_id',
        'year',
        'month',
        'budget',
        'currency',
        'notes',
    ];

    public function salePlatform()
    {
        return $this->belongsTo(SalePlatform::class);
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? false, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('year', 'like', '%' . $search . '%')
                    ->orWhere('month', 'like', '%' . $search . '%')
                    ->orWhere('budget', 'like', '%' . $search . '%')
                    ->orWhere('currency', 'like', '%' . $search . '%')
                    ->orWhereHas('salePlatform', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%');
                    });
            });
        });

        $query->when($filters['sale_platform_id'] ?? false, function ($query, $salePlatformId) {
            $query->where('sale_platform_id', $salePlatformId);
        });

        $query->when($filters['year'] ?? false, function ($query, $year) {
            $query->where('year', $year);
        });

        $query->when($filters['month'] ?? false, function ($query, $month) {
            $query->where('month', $month);
        });
    }
}
