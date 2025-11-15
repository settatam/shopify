<?php

namespace App\Http\Controllers\Etsy;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\Etsy\EtsyClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Show Etsy connection form
     */
    public function connect(Request $request)
    {
        return inertia('Etsy/Connect', [
            'shopId' => $request->user()->shop_id,
        ]);
    }

    /**
     * Initiate Etsy OAuth flow
     */
    public function authorize(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        // Generate state for CSRF protection
        $state = Str::random(40);

        // Store state in session
        $request->session()->put('etsy_oauth_state', $state);
        $request->session()->put('etsy_oauth_shop_id', $shop->id);

        // Build authorization URL
        $params = [
            'response_type' => 'code',
            'client_id' => config('services.etsy.client_id'),
            'redirect_uri' => config('services.etsy.redirect_uri'),
            'scope' => config('services.etsy.scopes'),
            'state' => $state,
            'code_challenge' => 'none', // Etsy supports PKCE but it's optional
            'code_challenge_method' => 'S256',
        ];

        $authUrl = 'https://www.etsy.com/oauth/connect?' . http_build_query($params);

        return redirect($authUrl);
    }

    /**
     * Handle OAuth callback from Etsy
     */
    public function callback(Request $request): RedirectResponse
    {
        // Verify state to prevent CSRF
        $state = $request->input('state');
        $sessionState = $request->session()->get('etsy_oauth_state');

        if (!$state || $state !== $sessionState) {
            return redirect('/channels')->with('error', 'Invalid OAuth state. Please try again.');
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect('/channels')->with('error', 'No authorization code received from Etsy.');
        }

        $shopId = $request->session()->get('etsy_oauth_shop_id');
        if (!$shopId) {
            return redirect('/channels')->with('error', 'Session expired. Please try again.');
        }

        try {
            // Exchange authorization code for access token
            $tokenData = $this->exchangeCodeForToken($code);

            // Get user shop information
            $shopInfo = $this->getUserShops($tokenData['access_token']);

            if (empty($shopInfo)) {
                throw new \Exception('No Etsy shops found for this account');
            }

            // Use the first shop
            $etsyShop = $shopInfo[0];

            // Create or update channel
            $channel = Channel::updateOrCreate(
                [
                    'shop_id' => $shopId,
                    'type' => 'etsy',
                    'external_id' => $etsyShop['shop_id'],
                ],
                [
                    'name' => $etsyShop['shop_name'] ?? 'Etsy Shop',
                    'status' => 'connected',
                    'auth_json' => [
                        'access_token' => $tokenData['access_token'],
                        'refresh_token' => $tokenData['refresh_token'],
                        'expires_at' => now()->addSeconds($tokenData['expires_in'])->timestamp,
                        'token_type' => $tokenData['token_type'] ?? 'Bearer',
                        'shop_id' => $etsyShop['shop_id'],
                        'shop_name' => $etsyShop['shop_name'],
                        'user_id' => $etsyShop['user_id'] ?? null,
                        'connected_at' => now()->toIso8601String(),
                    ],
                ]
            );

            // Fetch and store taxonomy
            $this->fetchTaxonomy($channel);

            // Clear session data
            $request->session()->forget('etsy_oauth_state');
            $request->session()->forget('etsy_oauth_shop_id');

            return redirect('/channels')->with('success', 'Etsy shop connected successfully!');
        } catch (\Exception $e) {
            Log::error('Etsy OAuth callback failed: ' . $e->getMessage());
            return redirect('/channels')->with('error', 'Failed to connect Etsy shop: ' . $e->getMessage());
        }
    }

    /**
     * Exchange authorization code for access token
     */
    protected function exchangeCodeForToken(string $code): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->post('https://api.etsy.com/v3/public/oauth/token', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('services.etsy.client_id'),
                    'redirect_uri' => config('services.etsy.redirect_uri'),
                    'code' => $code,
                    'code_verifier' => 'none', // If using PKCE
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (!isset($data['access_token'])) {
                throw new \Exception('No access token in response');
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Etsy token exchange failed: ' . $e->getMessage());
            throw new \Exception('Failed to exchange authorization code for token');
        }
    }

    /**
     * Get user's Etsy shops
     */
    protected function getUserShops(string $accessToken): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            // First get user ID
            $userResponse = $client->get('https://openapi.etsy.com/v3/application/users/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'x-api-key' => config('services.etsy.client_id'),
                ],
            ]);

            $userData = json_decode($userResponse->getBody(), true);
            $userId = $userData['user_id'];

            // Get user's shops
            $shopsResponse = $client->get("https://openapi.etsy.com/v3/application/users/{$userId}/shops", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'x-api-key' => config('services.etsy.client_id'),
                ],
            ]);

            $shopsData = json_decode($shopsResponse->getBody(), true);

            return $shopsData['results'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get Etsy shops: ' . $e->getMessage());
            throw new \Exception('Failed to retrieve Etsy shop information');
        }
    }

    /**
     * Disconnect Etsy account
     */
    public function disconnect(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'etsy') {
            return response()->json([
                'success' => false,
                'message' => 'This is not an Etsy channel',
            ], 422);
        }

        if ($channel->shop_id !== $request->user()->shop_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $channel->update([
            'status' => 'disconnected',
            'auth_json' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Etsy shop disconnected',
        ]);
    }

    /**
     * Test Etsy connection
     */
    public function test(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'etsy' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Etsy channel not connected',
            ], 422);
        }

        try {
            $client = new EtsyClient($channel);
            $shopId = $channel->auth_json['shop_id'] ?? null;

            if (!$shopId) {
                throw new \Exception('No shop ID found in channel configuration');
            }

            $shop = $client->getShop($shopId);
            $listings = $client->getShopListings($shopId, ['limit' => 5]);

            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'data' => [
                    'shop_name' => $shop['shop_name'] ?? '',
                    'listing_count' => $listings['count'] ?? 0,
                    'active_listings' => count($listings['results'] ?? []),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Fetch and store Etsy taxonomy (seller taxonomy nodes)
     */
    protected function fetchTaxonomy(Channel $channel): void
    {
        try {
            $client = new EtsyClient($channel);
            $taxonomy = $client->getSellerTaxonomy();

            // Store categories in channel_categories table
            if (isset($taxonomy['results'])) {
                foreach ($taxonomy['results'] as $node) {
                    \App\Models\ChannelCategory::updateOrCreate(
                        [
                            'channel_id' => $channel->id,
                            'external_id' => (string) $node['id'],
                        ],
                        [
                            'name' => $node['name'] ?? '',
                            'category_path' => $node['full_path_taxonomy_ids'] ?? '',
                            'parent_id' => $node['parent_id'] ?? null,
                            'attributes_json' => [
                                'level' => $node['level'] ?? 0,
                                'children' => $node['children'] ?? [],
                            ],
                        ]
                    );
                }
            }

            Log::info("Fetched Etsy taxonomy for channel {$channel->id}");
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Etsy taxonomy: ' . $e->getMessage());
        }
    }
}
