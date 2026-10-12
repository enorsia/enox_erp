<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSessionPCat extends Model
{
    protected $table = 'tracking_session_p_cat';

    protected $fillable = [
        'tracking_session_id',
        'tracking_product_id',
        'tracking_category_id',
    ];
}
