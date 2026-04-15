<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiamondPrice extends Model
{
    protected $fillable = [
        'quality',
        'color',
        'min_ct',
        'max_ct',
        'price',
    ];
}
