<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingProduct extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_product';

    protected $fillable = [
        'code',
        'sku',
        'title',
    ];
}
