<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request; use Illuminate\Support\Facades\Log;
use App\Jobs\{SyncInventoryJob, SyncPriceJob};
use App\Models\{Shop, ProductVariant};

class WebhooksController extends Controller
{
    //
    public function handle(Request $req)
    {
        $hmac = $req->header('X-Shopify-Hmac-Sha256');
        $calc = base64_encode(hash_hmac('sha256', $req->getContent(), config('services.shopify.webhook_secret'), true));
        abort_unless(hash_equals($hmac, $calc), 401, 'Invalid webhook HMAC');


        $topic = $req->header('X-Shopify-Topic');
        $shopDomain = $req->header('X-Shopify-Shop-Domain');
        $payload = $req->json()->all();


        if ($topic === 'app/uninstalled') {
            if ($shop = Shop::where('shopify_domain',$shopDomain)->first()) {
                $shop->channels()->update(['status'=>'disconnected']);
            }
            return response()->json(['ok'=>true]);
        }
        if ($topic === 'inventory_levels/update') {
            $sku = data_get($payload, 'inventory_item.sku');
            $qty = (int) data_get($payload, 'available', 0);
            $variant = ProductVariant::where('sku',$sku)->first();
            if ($variant) {
                foreach ($variant->product->shop->channels as $ch) {
                    SyncInventoryJob::dispatch($variant->id, $ch->id, $qty);
                }
            }
        }
        if ($topic === 'products/update') {
            foreach (data_get($payload, 'variants', []) as $v) {
                $variant = ProductVariant::where('shopify_variant_id', $v['id'] ?? 0)->first();
                if ($variant && isset($v['price'])) {
                    foreach ($variant->product->shop->channels as $ch) {
                        SyncPriceJob::dispatch($variant->id, $ch->id, (float)$v['price']);
                    }
                }
            }
        }
        return response()->json(['ok'=>true]);
    }


}
