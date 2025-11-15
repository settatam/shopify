<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryDisplayController extends Controller
{
    //
    public function showVariantInventory(Request $request, $productId, Variant $variant)
    {
        $this->authorize('view', $variant);
        $shopId = $request->user()->shop_id;
        $items = StockItem::with('location:id,name,priority')
            ->where('shop_id', $shopId)
            ->where('variant_id', $variant->id)
            ->get();
        $locations = Location::where('shop_id', $shopId)->orderBy('priority')->get(['id','name','priority']);
        return Inertia::render('Products/VariantDetail', [
            'variant' => $variant->only('id','sku','title','product_id','weight','length','width','height','price_min','price_max','msrp'),
            'product' => $variant->product()->first(['id','title','weight as default_weight','length as default_length','width as default_width','height as default_height','price_min as default_price_min','price_max as default_price_max','msrp as default_msrp']),
            'items' => $items,
            'locations' => $locations,
        ]);
    }
}
