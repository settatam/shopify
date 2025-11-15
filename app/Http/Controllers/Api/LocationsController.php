<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationsController extends Controller
{
    //
    public function index(Request $r)
    {
        $shopId = $r->user()?->shop_id ?? $r->integer('shop_id');
        return Location::where('shop_id', $shopId)->orderBy('name')->get();
    }


    public function store(Request $r)
    {
        $data = $r->validate([
            'shop_id' => 'required|exists:shops,id',
            'name' => 'required|string',
            'code' => 'nullable|string',
            'is_active' => 'boolean',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'default_handling_days' => 'nullable|integer|min:0',
        ]);
        return Location::create($data);
    }
}
