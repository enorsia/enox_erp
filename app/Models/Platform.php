<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Platform extends BaseModel
{
    protected $fillable = [
        'name',
        'shipping_charge',
        'note',
    ];
}
