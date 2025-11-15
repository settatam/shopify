<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Shop;

class ShopifyAuth
{
    public function handle(Request $request, Closure $next)
    {
        // read from query for embedded admin
        $shopDomain = $request->query('shop') ?: session('shopify_domain');
        $hostParam  = $request->query('host');

        if ($shopDomain) {
            $shop = Shop::where('shopify_domain', $shopDomain)->first();
            if ($shop) {
                // keep these around for links + App Bridge
                session([
                    'shopify_domain' => $shop->shopify_domain,
                    'shop_id'        => $shop->id,
                    'shopify_host'   => $hostParam,
                ]);
                // attach for controllers if you like
                $request->attributes->set('shop', $shop);
                return $next($request);
            }
        }

        // not installed → kick to install route
        return redirect()->route('shopify.install', [
            'shop' => $shopDomain,
            'host' => $hostParam,
        ]);
    }
}
