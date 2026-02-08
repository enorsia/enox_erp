<?php

namespace App\Models;

use App\ApiServices\SellingChartApiService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SellingChartType;
use App\Models\SellingChartPrice;
use Illuminate\Support\Facades\Auth;

class SellingChartBasicInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'department_name',
        'season_id',
        'season_name',
        'phase_id',
        'phase_name',
        'initial_repeated_id',
        'initial_repeated_status',
        'product_launch_month',
        'category_id',
        'category_name',
        'mini_category',
        'mini_category_name',
        'product_code',
        'design_no',
        'inspiration_image',
        'design_image',
        'product_description',
        'fabrication_id',
        'fabrication',
        'status'
    ];

    public function sellingChartPrices()
    {
        return $this->hasMany(SellingChartPrice::class, 'basic_info_id');
    }

    public function miniCategory()
    {
        return $this->belongsTo(SellingChartType::class, 'mini_category', "id");
    }

    public function scopeFilter($query, $filters)
    {
        return $query->when($filters->filled('name'), function ($q) use ($filters) {
            $designName = null;

            $exists = $q->clone()
                ->where('product_code', $filters->name)
                ->orWhere('design_no', $filters->name)
                ->orWhere('product_launch_month', $filters->name)
                ->exists();

            if (!$exists) {
                $sellingChartApiService = app(SellingChartApiService::class);

                $ecommerceProducts = $sellingChartApiService->getEcomProducts([
                    'designNos' => [$filters->name]
                ]);
                $ecomP = $ecommerceProducts->first();

                $designName = $ecomP['style']['name'] ?? null;
            }

            $q->where(function ($subQuery) use ($filters, $designName) {
                $subQuery->where('product_code', $filters->name)
                    ->orWhere('design_no', $filters->name)
                    ->orWhere('product_launch_month', $filters->name);

                if ($designName) {
                    $subQuery->orWhere('design_no', $designName);
                }
            });
        })
            ->when($filters->filled('department_id'), function ($q) use ($filters) {
                $q->where('department_id', $filters->department_id);
            })
            ->when($filters->filled('season_id'), function ($q) use ($filters) {
                $q->where('season_id', $filters->season_id);
            })
            ->when($filters->filled('season_phase_id'), function ($q) use ($filters) {
                $q->where('phase_id', $filters->season_phase_id);
            })
            ->when($filters->filled('initial_repeat_id'), function ($q) use ($filters) {
                $q->where('initial_repeated_id', $filters->initial_repeat_id);
            })
            ->when($filters->filled('fabrication_id'), function ($q) use ($filters) {
                $q->where('fabrication_id', $filters->fabrication_id);
            })
            ->when($filters->filled('product_category_id'), function ($q) use ($filters) {
                $q->where('category_id', $filters->product_category_id);
            })
            ->when($filters->filled('mini_category'), function ($q) use ($filters) {
                $q->where('mini_category', $filters->mini_category);
            });
    }

    public function scopeSellingAccessUsers()
    {
        $accessUsers = [
            [
                'email' => 'admin@app.com',
                'full_access' => true,
            ],
            [
                'email' => 'tanvir@enorsia.com',
                'full_access' => true,
            ],
            [
                'email' => 'sabbir@enorsia.com',
                'full_access' => true,
            ],
            [
                'email' => 'tanzil@enorsia.com',
                'full_access' => true,
            ],
            [
                'email' => 'abdullah.mm@enorsia.com',
                'full_access' => true,
            ],
            [
                'email' => 'nasimul.mm@enorsia.com',
                'full_access' => false,
            ],
            [
                'email' => 'pintu.mm@enorsia.com',
                'full_access' => false,
            ]
        ];

        return $accessUsers;
    }

    public function scopeSellingApprovedUsers()
    {
        $accessUsers = [
            [
                'email' => 'tanvir@enorsia.com',
            ],
            [
                'email' => 'admin@app.com',
            ]
        ];

        return $accessUsers;
    }

    public function scopeSellingSidebarAccess()
    {
        $email = Auth::user()?->email;
        $accessUser = collect(self::sellingAccessUsers())->where('email', $email)->first();
        if (!$accessUser) return false;
        return true;
    }

    public function scopeSellingFullAccess()
    {
        $email = Auth::user()?->email;
        $accessUser = collect(self::sellingAccessUsers())->where('email', $email)->first();
        if ($accessUser && $accessUser['full_access'] == true) return true;
        return false;
    }

    public function scopeSellingApprovedBtnAccess()
    {
        $email = Auth::user()?->email;
        $accessUser = collect(self::sellingApprovedUsers())->where('email', $email)->first();
        if (!$accessUser) return false;
        return true;
    }
}
