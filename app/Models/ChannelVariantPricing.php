<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelVariantPricing extends Model
{
    //
    protected $fillable = [
        'variant_id',
        'channel_id',
        'price_min',
        'price_max',
        'msrp',
        'meta'
    ];

    protected $casts = [ 'meta' => 'array' ];
}
