<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\{SyncInventoryJob, SyncPriceJob};
use App\Models\{Shop, Channel, ProductVariant};
use Illuminate\Http\Request;


class ShopifyWebhooksController extends Controller
{
    public function handle(Request $req)
    {
        $hmac = base64_decode($req->header('X-Shopify-Hmac-Sha256',''));
        $calc = hash_hmac('sha256', $req->getContent(), config('services.shopify.webhook_secret'), true);
        abort_unless(hash_equals($hmac, $calc), 401, 'Invalid HMAC');


        $topic = $req->header('X-Shopify-Topic');
        $payload = $req->json()->all();


        if ($topic === 'inventory_levels/update') {
// Map to variants by SKU or variant id
            $sku = data_get($payload, 'inventory_item.sku');
            $qty = (int) data_get($payload, 'available', 0);
            $variant = ProductVariant::where('sku', $sku)->first();
            if ($variant) {
                foreach ($variant->product->shop->channels as $ch) {
                    SyncInventoryJob::dispatch($variant->id, $ch->id, $qty);
                }
            }
        }


        if ($topic === 'products/update') {
// Optional: price sync for changed variants
            foreach (data_get($payload, 'variants', []) as $v) {
                $variant = ProductVariant::where('shopify_variant_id', $v['id'] ?? 0)->first();
                if ($variant && isset($v['price'])) {
                    foreach ($variant->product->shop->channels as $ch) {
                        SyncPriceJob::dispatch($variant->id, $ch->id, (float)$v['price']);
                    }
                }
            }
        }


        return response()->json(['ok' => true]);
    }
}
