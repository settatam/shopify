<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeMapping extends Model
{
    //
    protected $fillable = ['channel_id','product_id','channel_category_id','mapping_json'];
    protected $casts = ['mapping_json' => 'array'];


    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
}
