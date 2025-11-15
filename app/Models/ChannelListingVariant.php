<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelListingVariant extends Model
{
    //
    protected $fillable = ['channel_listing_id','product_variant_id','external_sku','external_offer_id','price','quantity','status','raw_json'];
    protected $casts = ['raw_json' => 'array'];


    public function listing(): BelongsTo { return $this->belongsTo(ChannelListing::class,'channel_listing_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class,'product_variant_id'); }
}
