<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSize extends Model
{
    protected $table = 'tracking_size';

    protected $fillable = [
        'code',
        'name',
    ];
}
