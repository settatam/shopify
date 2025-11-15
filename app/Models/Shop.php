<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    //
    protected $fillable = ['shopify_domain','access_token','scope','email'];


    public function channels(): HasMany { return $this->hasMany(Channel::class); }
    public function products(): HasMany { return $this->hasMany(Product::class); }
    public function locations(): HasMany {
        return $this->hasMany(Location::class);
    }
}
