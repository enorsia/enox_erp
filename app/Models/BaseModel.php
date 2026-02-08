<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BaseModel extends Model
{
    protected function formatDate($value)
    {
        return $value
            ? Carbon::parse($value)->format('Y-m-d h:i A')
            : null;
    }

    public function getCreatedAtAttribute($value)
    {
        return $this->formatDate($value);
    }

    public function getUpdatedAtAttribute($value)
    {
        return $this->formatDate($value);
    }
}
