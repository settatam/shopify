<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelListing extends Model
{
    //
    protected $fillable = ['channel_id','product_id','listing_external_id','status','last_synced_at','raw_json'];
    protected $casts = ['raw_json' => 'array','last_synced_at' => 'datetime'];


    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variants(): HasMany { return $this->hasMany(ChannelListingVariant::class); }
}
