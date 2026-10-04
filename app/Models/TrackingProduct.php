<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingProduct extends Model
{
    protected $table = 'tracking_product';

    protected $fillable = [
        'code',
        'sku',
        'title',
    ];
}
