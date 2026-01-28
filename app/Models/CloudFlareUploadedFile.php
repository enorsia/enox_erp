<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CloudFlareUploadedFile extends Model
{
     use HasFactory;

    protected $fillable = [
        'cloude_flare_id',
        'original_name',
        'original_path'
    ];
}
