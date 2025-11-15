<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Channel, Location};
use Inertia\Inertia;

class ChannelPolicyController extends Controller
{
    //

    public function edit(Request $request, Channel $channel)
    {
        $this->authorize('view', $channel);
        $locations = Location::where('shop_id', $channel->shop_id)->orderBy('priority')->get(['id','name','priority']);
        return Inertia::render('Channels/Policy', [
            'channel' => $channel->only('id','name'),
            'policy' => $channel->inventory_policy ?? ['location_ids'=>[], 'safety_stock'=>0],
            'locations' => $locations,
        ]);
    }

    public function show(Request $request, Channel $channel)
    {
        $this->authorize('view', $channel);
        return [ 'policy' => $channel->inventory_policy ?? ['location_ids'=>[], 'safety_stock'=>0] ];
    }


    public function update(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);
        $data = $request->validate([
            'location_ids' => 'array',
            'location_ids.*' => 'integer',
            'safety_stock' => 'integer|min:0',
        ]);
        $channel->inventory_policy = [
            'location_ids' => array_values(array_unique($data['location_ids'] ?? [])),
            'safety_stock' => $data['safety_stock'] ?? 0,
        ];
        $channel->save();
        return ['ok' => true];
    }
}
