<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariantPriceEntry extends Model
{
    protected $table = 'variant_price_entries';

    protected $fillable = [
        'shop',
        'variant_id',
        'metal_type',
        'gold_karat',
        'metal_weight',
        'diamond_quality_value',
        'diamond_weight',
        'making_charge',
        'computed_total',
    ];
}

