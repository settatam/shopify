<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $hmac = base64_decode($request->header('X-Shopify-Hmac-Sha256',''));
        $calc = hash_hmac('sha256', $request->getContent(), config('services.shopify.webhook_secret'), true);
        abort_unless(hash_equals($hmac, $calc), 401, 'Invalid HMAC');
        return $next($request);
    }
}
