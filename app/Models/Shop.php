<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = [
        'shop',
        'access_token',
        'plan',
        'charge_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
