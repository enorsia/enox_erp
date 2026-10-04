<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingCategory extends Model
{
    protected $table = 'tracking_category';

    protected $fillable = [
        'parent_id',
        'code',
        'name',
    ];
}
