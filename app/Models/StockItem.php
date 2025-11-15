<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    //
    protected $fillable = [
        'shop_id','variant_id','location_id','on_hand','reserved','avg_cost','meta'
    ];


    protected $casts = [
        'meta' => 'array',
    ];


    public function location(){ return $this->belongsTo(Location::class); }
    public function variant(){ return $this->belongsTo(Variant::class); }
}
