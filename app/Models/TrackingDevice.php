<?php

namespace App\Models;

use App\Models\Concerns\UsesTrackingDatabase;

use Illuminate\Database\Eloquent\Model;

class TrackingDevice extends Model
{
    use UsesTrackingDatabase;

    protected $table = 'tracking_device';

    protected $fillable = [
        'name',
    ];
}
