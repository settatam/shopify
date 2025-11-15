<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Models\{Channel, Variant};
use App\Jobs\SyncInventoryJob;

class ChannelSyncController extends Controller
{
    /**
     * POST /api/channels/sync-inventory
     * Body: { variantIds?: int[] }
     * Dispatches a batch: one SyncInventoryJob per channel in the current shop.
     */
    public function syncAll(Request $request)
    {
        $shopId = $request->user()->shop_id;

        $data = $request->validate([
            'variantIds'   => 'sometimes|array',
            'variantIds.*' => 'integer',
        ]);

        $channels = Channel::where('shop_id', $shopId)->get(['id','shop_id']);
        if ($channels->isEmpty()) {
            return response()->json(['ok' => true, 'dispatched' => 0, 'batchId' => null]);
        }

        $jobs = $channels->map(fn($c) => new SyncInventoryJob($c->id, $data['variantIds'] ?? []))->all();
        $batch = Bus::batch($jobs)->name("SyncInventory:shop:$shopId")->dispatch();

        return response()->json(['ok' => true, 'dispatched' => count($jobs), 'batchId' => $batch->id]);
    }

    /**
     * POST /api/channels/{channel}/sync-inventory
     * Body: { variantIds?: int[] }
     * Dispatches a single SyncInventoryJob for the given channel.
     */
    public function syncChannel(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $data = $request->validate([
            'variantIds'   => 'sometimes|array',
            'variantIds.*' => 'integer',
        ]);

        dispatch(new SyncInventoryJob($channel->id, $data['variantIds'] ?? []))->onQueue(null);

        return response()->json(['ok' => true, 'channelId' => $channel->id]);
    }
}
