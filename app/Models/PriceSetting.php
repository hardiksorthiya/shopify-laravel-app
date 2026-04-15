<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceSetting extends Model
{
    protected $fillable = [
        'gold_10kt',
        'gold_14kt',
        'gold_18kt',
        'gold_22kt',
        'silver_price',
        'platinum_price',
        'tax_percent',
    ];
}
