<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariantChannelPricing extends Model
{
    protected $fillable = [
        'shop_id', 'variant_id', 'channel_id',
        'price_min', 'price_max', 'msrp', 'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
        'msrp'      => 'decimal:2',
    ];

    public function variant(){ return $this->belongsTo(Variant::class); }
    public function channel(){ return $this->belongsTo(Channel::class); }
}
