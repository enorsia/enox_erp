<?php

namespace App\Models\Shop;

use App\Models\Concerns\UsesEnoxDatabase;
use Illuminate\Database\Eloquent\Model;

class EcommerceProduct extends Model
{
    use UsesEnoxDatabase;

    protected $table = 'ecommerce_products';

    public $timestamps = false;
}