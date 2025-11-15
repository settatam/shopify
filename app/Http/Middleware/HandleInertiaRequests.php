<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shared = parent::share($request);

        // host-aware params (Shopify embedded needs ?shop=&host=)
        $shopDomain = session('shopify_domain');
        $hostParam  = $request->query('host'); // Shopify admin host, if present

        $qs = fn(array $params) => $params ? ('?' . http_build_query(array_filter($params))) : '';

        // Only show Shopify App Bridge nav when we're inside Shopify
        $isShopify = (bool) $shopDomain;

        $appBridge = [
            'nav' => $isShopify ? [
                [
                    'label' => 'Home',
                    'destination' => route('app.home', [
                        'shop' => $shopDomain,
                        'host' => $hostParam,
                    ]),
                ],
                [
                    'label' => 'Locations', // NEW
                    'destination' => route('locations.index', [
                        'shop' => $shopDomain,
                        'host' => $hostParam,
                    ]),
                ],
                [
                    'label' => 'Channels',
                    'destination' => url('/channels') . $qs([
                        'shop' => $shopDomain,
                        'host' => $hostParam,
                    ]),
                ],
                [
                    'label' => 'Mappings',
                    'destination' => url('/mappings') . $qs([
                        'shop' => $shopDomain,
                        'host' => $hostParam,
                    ]),
                ],
                [
                    'label' => 'Feeds',
                    'destination' => route('feeds.index', [
                        'shop' => $shopDomain,
                        'host' => $hostParam,
                    ]),
                ],
            ] : null,
        ];

        /**
         * In-app (Inertia) topbar nav.
         * Share route names (not closures) so the client can resolve dynamic params using Ziggy.
         * See buildNavLinks.ts and TopbarLinks.vue to resolve:
         *  - channels.policy.edit → needs channelId
         *  - channels.rules.edit  → needs channelId
         *  - variants.inventory   → needs [productId, variantId]
         */
        $nav = [
            [
                'label' => 'Locations',
                'route' => 'locations.index',
            ],
            [
                'label' => 'Channels',
                'children' => [
                    ['label' => 'Policy',     'route' => 'channels.policy.edit'],
                    ['label' => 'Price Rules','route' => 'channels.rules.edit'], // NEW
                ],
            ],
            [
                'label' => 'Products',
                'children' => [
                    ['label' => 'Variant Detail', 'route' => 'variants.inventory'],
                ],
            ],
        ];

        return array_merge($shared, [
            'nav'       => $nav, // consumed by your TopbarLinks.vue via Ziggy
            'appBridge' => array_merge($appBridge, $shared['appBridge'] ?? []),
        ]);
    }
}
