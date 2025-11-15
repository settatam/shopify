<?php

namespace App\Http\Controllers\Square;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\Square\SquareClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Show Square connection form
     */
    public function connect(Request $request)
    {
        return inertia('Square/Connect', [
            'shopId' => $request->user()->shop_id,
        ]);
    }

    /**
     * Initiate Square OAuth flow
     */
    public function authorize(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        // Generate state for CSRF protection
        $state = Str::random(40);

        // Store state in session
        $request->session()->put('square_oauth_state', $state);
        $request->session()->put('square_oauth_shop_id', $shop->id);

        // Build authorization URL
        $sandbox = config('services.square.sandbox', false);
        $authUrl = $sandbox
            ? 'https://connect.squareupsandbox.com/oauth2/authorize'
            : 'https://connect.squareup.com/oauth2/authorize';

        $params = [
            'client_id' => config('services.square.application_id'),
            'scope' => implode(' ', [
                'MERCHANT_PROFILE_READ',
                'ITEMS_READ',
                'ITEMS_WRITE',
                'INVENTORY_READ',
                'INVENTORY_WRITE',
                'ORDERS_READ',
                'ORDERS_WRITE',
                'PAYMENTS_READ',
            ]),
            'session' => 'false',
            'state' => $state,
        ];

        $authorizationUrl = $authUrl . '?' . http_build_query($params);

        return redirect($authorizationUrl);
    }

    /**
     * Handle OAuth callback from Square
     */
    public function callback(Request $request): RedirectResponse
    {
        // Check for authorization error
        if ($request->has('error')) {
            $error = $request->input('error');
            $errorDescription = $request->input('error_description', 'Unknown error');
            return redirect('/channels')->with('error', "Square authorization failed: {$errorDescription}");
        }

        // Verify state to prevent CSRF
        $state = $request->input('state');
        $sessionState = $request->session()->get('square_oauth_state');

        if (!$state || $state !== $sessionState) {
            return redirect('/channels')->with('error', 'Invalid OAuth state. Please try again.');
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect('/channels')->with('error', 'No authorization code received from Square.');
        }

        $shopId = $request->session()->get('square_oauth_shop_id');
        if (!$shopId) {
            return redirect('/channels')->with('error', 'Session expired. Please try again.');
        }

        try {
            // Exchange authorization code for access token
            $tokenData = $this->exchangeCodeForToken($code);

            // Get merchant info
            $merchantInfo = $this->getMerchantInfo($tokenData['access_token']);

            // Get locations
            $locationsData = $this->getLocations($tokenData['access_token']);
            $locations = $locationsData['locations'] ?? [];

            if (empty($locations)) {
                throw new \Exception('No Square locations found for this account');
            }

            // Use the first location by default
            $primaryLocation = $locations[0];

            // Create or update channel
            $channel = Channel::updateOrCreate(
                [
                    'shop_id' => $shopId,
                    'type' => 'square',
                    'external_id' => $tokenData['merchant_id'],
                ],
                [
                    'name' => $primaryLocation['name'] ?? 'Square POS',
                    'status' => 'connected',
                    'auth_json' => [
                        'access_token' => $tokenData['access_token'],
                        'refresh_token' => $tokenData['refresh_token'],
                        'expires_at' => now()->addDays(30)->timestamp, // Square tokens last 30 days
                        'merchant_id' => $tokenData['merchant_id'],
                        'location_id' => $primaryLocation['id'],
                        'location_name' => $primaryLocation['name'],
                        'currency' => $primaryLocation['currency'] ?? 'USD',
                        'country' => $primaryLocation['country'] ?? 'US',
                        'connected_at' => now()->toIso8601String(),
                    ],
                ]
            );

            // Clear session data
            $request->session()->forget('square_oauth_state');
            $request->session()->forget('square_oauth_shop_id');

            return redirect('/channels')->with('success', 'Square POS connected successfully!');
        } catch (\Exception $e) {
            Log::error('Square OAuth callback failed: ' . $e->getMessage());
            return redirect('/channels')->with('error', 'Failed to connect Square: ' . $e->getMessage());
        }
    }

    /**
     * Exchange authorization code for access token
     */
    protected function exchangeCodeForToken(string $code): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $sandbox = config('services.square.sandbox', false);
            $tokenUrl = $sandbox
                ? 'https://connect.squareupsandbox.com/oauth2/token'
                : 'https://connect.squareup.com/oauth2/token';

            $response = $client->post($tokenUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'client_id' => config('services.square.application_id'),
                    'client_secret' => config('services.square.application_secret'),
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (!isset($data['access_token'])) {
                throw new \Exception('No access token in response');
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Square token exchange failed: ' . $e->getMessage());
            throw new \Exception('Failed to exchange authorization code for token');
        }
    }

    /**
     * Get merchant information
     */
    protected function getMerchantInfo(string $accessToken): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $sandbox = config('services.square.sandbox', false);
            $baseUrl = $sandbox
                ? 'https://connect.squareupsandbox.com/v2'
                : 'https://connect.squareup.com/v2';

            $response = $client->get("{$baseUrl}/merchants", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Square-Version' => '2024-10-17',
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $merchants = $data['merchant'] ?? [];

            return !empty($merchants) ? $merchants[0] : [];
        } catch (\Exception $e) {
            Log::error('Failed to get Square merchant info: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get locations
     */
    protected function getLocations(string $accessToken): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $sandbox = config('services.square.sandbox', false);
            $baseUrl = $sandbox
                ? 'https://connect.squareupsandbox.com/v2'
                : 'https://connect.squareup.com/v2';

            $response = $client->get("{$baseUrl}/locations", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Square-Version' => '2024-10-17',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Failed to get Square locations: ' . $e->getMessage());
            throw new \Exception('Failed to retrieve Square locations');
        }
    }

    /**
     * Disconnect Square account
     */
    public function disconnect(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'square') {
            return response()->json([
                'success' => false,
                'message' => 'This is not a Square channel',
            ], 422);
        }

        if ($channel->shop_id !== $request->user()->shop_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Optionally revoke the token with Square
        try {
            $this->revokeToken($channel);
        } catch (\Exception $e) {
            Log::warning('Failed to revoke Square token: ' . $e->getMessage());
        }

        $channel->update([
            'status' => 'disconnected',
            'auth_json' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Square disconnected',
        ]);
    }

    /**
     * Revoke Square token
     */
    protected function revokeToken(Channel $channel): void
    {
        $authJson = $channel->auth_json ?? [];
        $accessToken = $authJson['access_token'] ?? null;

        if (!$accessToken) {
            return;
        }

        $client = new \GuzzleHttp\Client();

        try {
            $sandbox = config('services.square.sandbox', false);
            $revokeUrl = $sandbox
                ? 'https://connect.squareupsandbox.com/oauth2/revoke'
                : 'https://connect.squareup.com/oauth2/revoke';

            $client->post($revokeUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'client_id' => config('services.square.application_id'),
                    'access_token' => $accessToken,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to revoke Square token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test Square connection
     */
    public function test(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'square' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Square channel not connected',
            ], 422);
        }

        try {
            $client = new SquareClient($channel);
            $result = $client->testConnection();

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful',
                    'data' => [
                        'locations' => $result['locations'],
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . ($result['error'] ?? 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get available locations
     */
    public function getLocations(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'square' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Square channel not connected',
            ], 422);
        }

        try {
            $client = new SquareClient($channel);
            $locationsData = $client->getLocations();

            return response()->json([
                'success' => true,
                'locations' => $locationsData['locations'] ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch locations: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update configured location
     */
    public function updateLocation(Request $request, Channel $channel): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|string',
        ]);

        if ($channel->type !== 'square' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Square channel not connected',
            ], 422);
        }

        try {
            $client = new SquareClient($channel);
            $location = $client->getLocation($request->input('location_id'));

            $authJson = $channel->auth_json ?? [];
            $authJson['location_id'] = $request->input('location_id');
            $authJson['location_name'] = $location['location']['name'] ?? '';

            $channel->update(['auth_json' => $authJson]);

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Sync catalog to Square
     */
    public function syncCatalog(Request $request, Channel $channel): JsonResponse
    {
        $request->validate([
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        if ($channel->type !== 'square' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Square channel not connected',
            ], 422);
        }

        try {
            $productIds = $request->input('product_ids');

            if ($productIds) {
                // Sync specific products
                foreach ($productIds as $productId) {
                    $product = \App\Models\Product::find($productId);
                    if ($product) {
                        \App\Jobs\Square\SyncCatalogToSquare::dispatch($channel, $product);
                    }
                }
                $message = 'Queued ' . count($productIds) . ' products for catalog sync';
            } else {
                // Sync all products
                $products = $channel->shop->products;
                foreach ($products as $product) {
                    \App\Jobs\Square\SyncCatalogToSquare::dispatch($channel, $product);
                }
                $message = 'Queued all products for catalog sync';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue catalog sync: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Sync inventory to Square
     */
    public function syncInventory(Request $request, Channel $channel): JsonResponse
    {
        $request->validate([
            'variant_ids' => 'nullable|array',
            'variant_ids.*' => 'exists:product_variants,id',
        ]);

        if ($channel->type !== 'square' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Square channel not connected',
            ], 422);
        }

        try {
            $variantIds = $request->input('variant_ids');

            if ($variantIds) {
                // Sync specific variants
                foreach ($variantIds as $variantId) {
                    $variant = \App\Models\ProductVariant::find($variantId);
                    if ($variant) {
                        \App\Jobs\Square\SyncInventoryToSquare::dispatch($channel, $variant);
                    }
                }
                $message = 'Queued ' . count($variantIds) . ' variants for inventory sync';
            } else {
                // Sync all variants
                $variants = \App\Models\ProductVariant::whereHas('product', function ($query) use ($channel) {
                    $query->where('shop_id', $channel->shop_id);
                })->get();

                foreach ($variants as $variant) {
                    \App\Jobs\Square\SyncInventoryToSquare::dispatch($channel, $variant);
                }
                $message = 'Queued all variants for inventory sync';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue inventory sync: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Fetch orders from Square
     */
    public function fetchOrders(Request $request, Channel $channel): JsonResponse
    {
        $request->validate([
            'begin_time' => 'nullable|date',
        ]);

        if ($channel->type !== 'square' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Square channel not connected',
            ], 422);
        }

        try {
            $beginTime = $request->input('begin_time')
                ? \Carbon\Carbon::parse($request->input('begin_time'))->toIso8601String()
                : null;

            \App\Jobs\Square\FetchSquareOrders::dispatch($channel, $beginTime);

            return response()->json([
                'success' => true,
                'message' => 'Queued order fetch from Square',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue order fetch: ' . $e->getMessage(),
            ], 422);
        }
    }
}
