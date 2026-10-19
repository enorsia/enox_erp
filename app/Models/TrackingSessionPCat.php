<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingSessionPCat extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_session_p_cat';

    protected $fillable = [
        'tracking_session_id',
        'tracking_product_id',
        'tracking_category_id',
    ];
}
