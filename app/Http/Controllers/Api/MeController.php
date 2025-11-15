<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Shop, Channel, Product};


class MeController extends Controller
{
    public function channels(Shop $shop) { return $shop->channels()->get(); }
    public function products(Shop $shop) { return $shop->products()->latest()->limit(50)->get(); }
}
