<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelsController extends Controller
{
    //
    public function index(Request $request)
    {
        $channels = Channel::query()
            ->where('shop_id', $request->user()->shop_id)
            ->orderBy('name')
            ->get(['id','name']);
        return ['channels' => $channels];
    }
}
