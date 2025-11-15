<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PublishListingJob;
use Illuminate\Http\Request;


class PublishController extends Controller
{
    public function publish(Request $req)
    {
        $data = $req->validate([
            'product_id' => 'required|integer',
            'channel_id' => 'required|integer',
            'channel_category_id' => 'nullable|integer',
        ]);

        PublishListingJob::dispatch($data['product_id'], $data['channel_id'], $data['channel_category_id'] ?? null);
        return response()->json(['queued' => true]);
    }
}
