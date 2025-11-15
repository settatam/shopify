<?php

namespace App\Http\Controllers\ZohoInventory;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\ZohoInventory\ZohoInventoryClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Initiate Zoho Inventory OAuth flow
     */
    public function authorize(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        // Generate state for CSRF protection
        $state = Str::random(40);

        // Store state in session
        session(['zoho_inventory_state' => $state]);

        // Zoho OAuth authorization URL
        $params = http_build_query([
            'client_id' => config('services.zoho_inventory.client_id'),
            'response_type' => 'code',
            'scope' => 'ZohoInventory.FullAccess.all',
            'redirect_uri' => config('services.zoho_inventory.redirect_uri'),
            'state' => $state,
            'access_type' => 'offline', // Get refresh token
            'prompt' => 'consent',
        ]);

        // Zoho has different auth URLs for different data centers
        $datacenter = config('services.zoho_inventory.datacenter', 'com');
        $authUrl = "https://accounts.zoho.{$datacenter}/oauth/v2/auth?{$params}";

        return response()->json([
            'success' => true,
            'authorization_url' => $authUrl,
        ]);
    }

    /**
     * Handle OAuth callback from Zoho
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');
        $error = $request->input('error');

        // Check for errors
        if ($error) {
            Log::error('Zoho Inventory OAuth error', ['error' => $error]);
            return redirect(config('app.frontend_url') . '/integrations/zoho-inventory?error=' . urlencode($error));
        }

        // Verify state
        if (!$state || $state !== session('zoho_inventory_state')) {
            Log::error('Zoho Inventory OAuth state mismatch');
            return redirect(config('app.frontend_url') . '/integrations/zoho-inventory?error=invalid_state');
        }

        try {
            // Exchange code for access token
            $datacenter = config('services.zoho_inventory.datacenter', 'com');
            $response = Http::asForm()->post("https://accounts.zoho.{$datacenter}/oauth/v2/token", [
                'code' => $code,
                'client_id' => config('services.zoho_inventory.client_id'),
                'client_secret' => config('services.zoho_inventory.client_secret'),
                'redirect_uri' => config('services.zoho_inventory.redirect_uri'),
                'grant_type' => 'authorization_code',
            ]);

            if ($response->failed()) {
                Log::error('Zoho Inventory token exchange failed', [
                    'response' => $response->json(),
                ]);
                throw new \Exception('Failed to exchange code for token');
            }

            $tokenData = $response->json();

            // Fetch organizations to get organization_id
            $orgsResponse = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $tokenData['access_token'],
            ])->get("https://inventory.zoho.{$datacenter}/api/v1/organizations");

            if ($orgsResponse->failed()) {
                throw new \Exception('Failed to fetch organizations');
            }

            $orgsData = $orgsResponse->json();
            $organizations = $orgsData['organizations'] ?? [];

            if (empty($organizations)) {
                throw new \Exception('No Zoho Inventory organizations found');
            }

            // Use the first organization (or let user select later)
            $organization = $organizations[0];

            // Get user's shop
            $shop = auth()->user()->shop;

            // Create or update channel
            $channel = Channel::updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'channel_type' => 'zoho_inventory',
                ],
                [
                    'name' => 'Zoho Inventory - ' . $organization['name'],
                    'is_active' => true,
                    'auth_json' => [
                        'access_token' => $tokenData['access_token'],
                        'refresh_token' => $tokenData['refresh_token'],
                        'expires_at' => Carbon::now()->addSeconds($tokenData['expires_in'])->toDateTimeString(),
                        'organization_id' => $organization['organization_id'],
                        'organization_name' => $organization['name'],
                        'datacenter' => $datacenter,
                        'api_domain' => $tokenData['api_domain'] ?? "https://inventory.zoho.{$datacenter}",
                    ],
                    'settings' => [
                        'sync_products' => true,
                        'sync_inventory' => true,
                        'sync_orders' => true,
                        'auto_confirm_orders' => false,
                    ],
                ]
            );

            // Clear state from session
            session()->forget('zoho_inventory_state');

            return redirect(config('app.frontend_url') . '/integrations/zoho-inventory?success=1&channel_id=' . $channel->id);
        } catch (\Exception $e) {
            Log::error('Zoho Inventory OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect(config('app.frontend_url') . '/integrations/zoho-inventory?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Test the Zoho Inventory connection
     */
    public function test(Channel $channel): JsonResponse
    {
        try {
            $client = new ZohoInventoryClient($channel);
            $isConnected = $client->testConnection();

            return response()->json([
                'success' => $isConnected,
                'message' => $isConnected
                    ? 'Successfully connected to Zoho Inventory'
                    : 'Failed to connect to Zoho Inventory',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Disconnect Zoho Inventory integration
     */
    public function disconnect(Channel $channel): JsonResponse
    {
        try {
            // Delete the channel
            $channel->delete();

            return response()->json([
                'success' => true,
                'message' => 'Zoho Inventory disconnected successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get available organizations
     */
    public function getOrganizations(Channel $channel): JsonResponse
    {
        try {
            $client = new ZohoInventoryClient($channel);
            $authData = $channel->auth_json;
            $result = $client->getOrganizations($authData['access_token']);

            return response()->json([
                'success' => true,
                'organizations' => $result['organizations'] ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch organizations: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Switch to a different organization
     */
    public function switchOrganization(Request $request, Channel $channel): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|string',
        ]);

        try {
            $authData = $channel->auth_json;
            $authData['organization_id'] = $request->input('organization_id');

            $channel->update(['auth_json' => $authData]);

            return response()->json([
                'success' => true,
                'message' => 'Organization switched successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to switch organization: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get warehouses
     */
    public function getWarehouses(Channel $channel): JsonResponse
    {
        try {
            $client = new ZohoInventoryClient($channel);
            $result = $client->getWarehouses();

            return response()->json([
                'success' => true,
                'warehouses' => $result['warehouses'] ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouses: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update channel settings
     */
    public function updateSettings(Request $request, Channel $channel): JsonResponse
    {
        $request->validate([
            'sync_products' => 'boolean',
            'sync_inventory' => 'boolean',
            'sync_orders' => 'boolean',
            'auto_confirm_orders' => 'boolean',
            'default_warehouse_id' => 'nullable|string',
        ]);

        try {
            $settings = $channel->settings ?? [];

            if ($request->has('sync_products')) {
                $settings['sync_products'] = $request->input('sync_products');
            }
            if ($request->has('sync_inventory')) {
                $settings['sync_inventory'] = $request->input('sync_inventory');
            }
            if ($request->has('sync_orders')) {
                $settings['sync_orders'] = $request->input('sync_orders');
            }
            if ($request->has('auto_confirm_orders')) {
                $settings['auto_confirm_orders'] = $request->input('auto_confirm_orders');
            }
            if ($request->has('default_warehouse_id')) {
                $settings['default_warehouse_id'] = $request->input('default_warehouse_id');
            }

            $channel->update(['settings' => $settings]);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage(),
            ], 422);
        }
    }
}
