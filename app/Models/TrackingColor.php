<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingColor extends Model
{
    protected $table = 'tracking_color';

    protected $fillable = [
        'code',
        'name',
    ];
}
