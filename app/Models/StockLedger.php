<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLedger extends Model
{
    //
    protected $table = 'stock_ledger';


    protected $fillable = [
        'shop_id','variant_id','location_id','type','qty','unit_cost','avg_cost_after','reference_type','reference_id','meta'
    ];


    protected $casts = [
        'meta' => 'array',
    ];
}
