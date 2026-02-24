<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellingChartDiscount extends Model
{
    protected $fillable = [
        'selling_chart_price_id',
        'platform_id',
        'price',
        'status',
    ];

    public function sellingChartPrice()
    {
        return $this->belongsTo(SellingChartPrice::class);
    }

    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    public static function approvalEmails(): array
    {
        return [
            "tanvir@enorsia.com",
            "sabbir@enorsia.com",
        ];
    }

    public static function executorEmails(): array
    {
        return [
            "abid.ict@enorsia.com",
            "mijan.ict@enorsia.com",
            "sakib.ict@enorsia.com"
        ];
    }
}
