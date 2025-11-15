<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelCategory extends Model
{
    //
    protected $fillable = ['channel_id','external_category_id','name','attributes_json'];
    protected $casts = ['attributes_json' => 'array'];


    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
}
