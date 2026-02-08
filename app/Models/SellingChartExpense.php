<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellingChartExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'conversion_rate',
        'commercial_expense',
        'enorsia_expense_bd',
        'enorsia_expense_uk',
        'shipping_cost',
        'status',
    ];

}
