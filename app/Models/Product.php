<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    //
    use SoftDeletes;

    protected $fillable = [
      'shop_id','shopify_product_id','title','description','brand','vendor','status',
      'product_type','manufacturer','country_of_origin','hs_code','msrp',
    ];

    protected $casts = [
      'tags_json' => 'array',
      'images_json' => 'array',
      'attributes_json' => 'array',
      'bullet_points_json' => 'array',
      'compliance_json' => 'array',
    ];


    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function variants(): HasMany { return $this->hasMany(ProductVariant::class); }
    public function listings(): HasMany { return $this->hasMany(ChannelListing::class); }
}
