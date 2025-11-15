<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelLocation extends Model
{
    //
    protected $fillable = ['channel_id','merchant_location_key','name','address_json','geo_json','is_default'];
    protected $casts = ['address_json' => 'array','geo_json' => 'array','is_default' => 'boolean'];


    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
}
