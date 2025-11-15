<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    //
    public function adjust(Request $request, InventoryService $svc)
    {
        $data = $request->validate([
            'variant_id' => 'required|integer',
            'location_id' => 'required|integer',
            'qty' => 'required|integer',
            'unit_cost' => 'nullable|numeric',
            'reason' => 'nullable|string',
        ]);
        $item = $svc->adjust($request->user()->shop_id, $data['variant_id'], $data['location_id'], $data['qty'], $data['unit_cost'] ?? null, $data['reason'] ?? 'adjust');
        return ['item' => $item];
    }

    public function reserve(Request $request, InventoryService $svc)
    {
        $data = $request->validate([
            'variant_id' => 'required|integer',
            'location_id' => 'required|integer',
            'qty' => 'required|integer|min:1',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|string',
        ]);
        $item = $svc->reserve($request->user()->shop_id, $data['variant_id'], $data['location_id'], $data['qty'], $data['reference_type'] ?? 'order', $data['reference_id'] ?? '');
        return ['item' => $item];
    }


    public function release(Request $request, InventoryService $svc)
    {
        $data = $request->validate([
            'variant_id' => 'required|integer',
            'location_id' => 'required|integer',
            'qty' => 'required|integer|min:1',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|string',
        ]);
        $item = $svc->release($request->user()->shop_id, $data['variant_id'], $data['location_id'], $data['qty'], $data['reference_type'] ?? 'order', $data['reference_id'] ?? '');
        return ['item' => $item];
    }

    public function transfer(Request $r, InventoryService $svc)
    {
        $v = $r->validate([
            'variant_id' => 'required|integer',
            'from_location_id' => 'required|integer|different:to_location_id',
            'to_location_id' => 'required|integer',
            'qty' => 'required|integer|min:1',
            'unit_cost' => 'nullable|numeric',
        ]);
        $svc->transfer($v['variant_id'], $v['from_location_id'], $v['to_location_id'], $v['qty'], $v['unit_cost'] ?? null);
        return ['ok' => true];
    }
}
