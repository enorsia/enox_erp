<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingCategory extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_category';

    protected $fillable = [
        'parent_id',
        'code',
        'name',
    ];
}
