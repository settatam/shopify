<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    //
    protected $fillable = [
        'shop_id','type','name','status','auth_json','sandbox','included_location_ids','safety_stock'
    ];
    protected $casts = [
        'auth_json' => 'array',
        'sandbox' => 'boolean',
        'included_location_ids' => 'array',
        'config' => 'array',
        'inventory_policy' => 'array',
    ];


    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function listings(): HasMany { return $this->hasMany(ChannelListing::class); }
    public function categories(): HasMany { return $this->hasMany(ChannelCategory::class); }



}
