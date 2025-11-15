<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    //
    protected $fillable = [
        'shop_id','name','code','is_active','priority','meta',
    ];


    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];


    public function shop(){ return $this->belongsTo(Shop::class); }
    public function stockItems(){ return $this->hasMany(StockItem::class); }
}
