<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingColor extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_color';

    protected $fillable = [
        'code',
        'name',
    ];
}
