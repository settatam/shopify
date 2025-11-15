<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;

class AmazonSettingsController extends Controller
{
    //
    public function edit(Channel $channel): Response
    {
        return Inertia::render('Channels/AmazonSettings', [
            'channel' => [ 'id' => $channel->id, 'name' => $channel->name ],
            'auth' => $channel->auth_json,
        ]);
    }


    public function update(Request $r, Channel $channel)
    {
        $data = $r->validate([
            'seller_id' => 'required|string',
            'marketplace_ids' => 'required|array',
            'marketplace_ids.*' => 'string',
            'region' => 'required|string',
            'host' => 'required|string',
            'currency' => 'required|string',
        ]);
        $auth = array_merge($channel->auth_json ?? [], $data);
        $channel->update(['auth_json' => $auth]);
        return back()->with('success', 'Amazon settings saved');
    }
}
