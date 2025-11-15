<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyFrame
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
// Optional: ensure `shop` param exists for embedded pages
        if (!$request->query('shop')) {
// try session or redirect to install
            if ($s = session('shopify_domain')) {
                return redirect()->to(route('app.home', ['shop' => $s]));
            }
            return redirect()->to(route('shopify.install'));
        }
        return $next($request);
    }


}
