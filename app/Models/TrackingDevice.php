<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingDevice extends Model
{
    protected $table = 'tracking_device';

    protected $fillable = [
        'name',
    ];
}
