<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    //
    protected $fillable = [
        'product_id','shopify_variant_id','sku','barcode',
        'mpn','gtin','gtin_type','isbn',
        'price','compare_at_price','msrp','min_price','max_price','cost',
        'weight','weight_unit','length','width','height','dim_unit',
        'tax_code','condition','fulfillment_latency',
        'option1','option2','option3','attributes_json', 'price_min','price_max','msrp'
    ];

    protected $casts = [
        'attributes_json' => 'array',
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
        'msrp'      => 'decimal:2',
    ];


    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
