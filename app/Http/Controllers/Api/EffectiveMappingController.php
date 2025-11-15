<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Product;
use App\Services\MappingService;
use Illuminate\Http\Request;

class EffectiveMappingController extends Controller
{
    //
    public function show(Request $req)
    {
        $data = $req->validate([
            'channel_id' => 'required|exists:channels,id',
            'product_id' => 'required|exists:products,id',
            'channel_category_id' => 'nullable|exists:channel_categories,id',
        ]);
        $channel = Channel::findOrFail($data['channel_id']);
        $product = Product::with('variants')->findOrFail($data['product_id']);
        $merged = MappingService::for($channel, $product)->compose($data['channel_category_id'] ?? null);
        return response()->json($merged);
    }
}
