<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Channel, Product, AttributeMapping, ChannelCategory};
use Illuminate\Http\Request;


class MappingsController extends Controller
{
    public function store(Request $req)
    {
        $data = $req->validate([
            'channel_id' => 'required|exists:channels,id',
            'product_id' => 'nullable|exists:products,id',
            'channel_category_id' => 'nullable|exists:channel_categories,id',
            'mapping_json' => 'required|array',
        ]);
        $m = AttributeMapping::create($data);
        return response()->json($m);
    }


    public function categories(Channel $channel)
    {
// Return cached categories (populate via adapter elsewhere)
        return $channel->categories()->select('id','external_category_id','name','attributes_json')->get();
    }
}
