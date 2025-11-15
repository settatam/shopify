<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Shop, Channel};
use Illuminate\Http\Request;


class ChannelAuthController extends Controller
{
    public function connect(Request $req)
    {
        $data = $req->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'type' => 'required|in:ebay,amazon,etsy,walmart',
            'name' => 'required|string',
            'auth_json' => 'required|array',
            'sandbox' => 'boolean',
        ]);
        $channel = Channel::updateOrCreate(
            ['shop_id' => $data['shop_id'], 'type' => $data['type']],
            [
                'name' => $data['name'],
                'status' => 'connected',
                'auth_json' => $data['auth_json'],
                'sandbox' => $data['sandbox'] ?? true,
            ]
        );
        return response()->json($channel);
    }


    public function disconnect(Channel $channel)
    {
        $channel->update(['status' => 'disconnected']);
        return response()->json(['ok' => true]);
    }
}
