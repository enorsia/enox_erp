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
        'note',
    ];
}
