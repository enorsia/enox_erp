<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Platform extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'shipping_charge',
        'min_profit',
        'commission',
        'status',
        'note',
    ];

    public static function selectedPlatforms(): array
    {
        return [
            'enox'   => 'Enorsia',
            'dbz'    => 'Debenhams',
            'amz_uk' => 'Amazon',
            'rkm'    => 'Rackhams',
            'spr_uk' => 'Spartoo',
        ];
    }
}
