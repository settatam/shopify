<?php

namespace App\Http\Controllers\Square;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SquareAppController extends Controller
{
    /**
     * Square OAuth scopes required for the app.
     */
    protected array $scopes = [
        'MERCHANT_PROFILE_READ',
        'PAYMENTS_READ',
        'PAYMENTS_WRITE',
        'ORDERS_READ',
        'ORDERS_WRITE',
        'ITEMS_READ',
        'ITEMS_WRITE',
        'INVENTORY_READ',
        'INVENTORY_WRITE',
        'CUSTOMERS_READ',
        'CUSTOMERS_WRITE',
        'SETTLEMENTS_READ',
        'EMPLOYEES_READ',
    ];

    /**
     * Initiate Square App installation.
     * This is the entry point when a merchant clicks "Install" in Square App Marketplace.
     */
    public function install(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Generate state for CSRF protection
        $state = Str::random(40);
        session(['square_oauth_state' => $state]);

        // Store any pre-install data (e.g., referral codes, campaign tracking)
        if ($request->has('ref')) {
            session(['square_referral' => $request->input('ref')]);
        }

        // Build OAuth authorization URL
        $baseUrl = config('app.env') === 'production'
            ? 'https://connect.squareup.com/oauth2/authorize'
            : 'https://connect.squareupsandbox.com/oauth2/authorize';

        $query = http_build_query([
            'client_id' => config('services.square.application_id'),
            'scope' => implode(' ', $this->scopes),
            'session' => 'false',
            'state' => $state,
        ]);

        Log::info('Square App installation initiated', [
            'state' => $state,
            'referral' => session('square_referral'),
        ]);

        return redirect($baseUrl . '?' . $query);
    }

    /**
     * Handle OAuth callback from Square.
     * Square redirects here after merchant authorizes the app.
     */
    public function callback(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Verify state to prevent CSRF attacks
        if (!$request->has('state') || $request->state !== session('square_oauth_state')) {
            Log::error('Square OAuth state mismatch', [
                'received_state' => $request->state,
                'session_state' => session('square_oauth_state'),
            ]);
            return redirect('/install-error?error=invalid_state');
        }

        // Check for authorization errors
        if ($request->has('error')) {
            Log::error('Square OAuth authorization error', [
                'error' => $request->error,
                'error_description' => $request->error_description,
            ]);
            return redirect('/install-error?error=' . $request->error);
        }

        // Exchange authorization code for access token
        try {
            $tokenData = $this->exchangeCodeForToken($request->code);

            // Create shop, channel, and user
            DB::beginTransaction();

            $installation = $this->createInstallation($tokenData, $request);

            DB::commit();

            // Clear OAuth session data
            session()->forget(['square_oauth_state', 'square_referral']);

            Log::info('Square App installation completed successfully', [
                'shop_id' => $installation['shop']->id,
                'user_id' => $installation['user']->id,
                'merchant_id' => $tokenData['merchant_id'],
            ]);

            // Redirect to onboarding
            return redirect('/square-onboarding?token=' . $installation['onboarding_token']);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Square App installation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect('/install-error?error=installation_failed');
        }
    }

    /**
     * Exchange authorization code for access token.
     */
    protected function exchangeCodeForToken(string $code): array
    {
        $baseUrl = config('app.env') === 'production'
            ? 'https://connect.squareup.com/oauth2/token'
            : 'https://connect.squareupsandbox.com/oauth2/token';

        $response = Http::post($baseUrl, [
            'client_id' => config('services.square.application_id'),
            'client_secret' => config('services.square.application_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange authorization code: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Create shop, channel, and user for the Square merchant.
     */
    protected function createInstallation(array $tokenData, Request $request): array
    {
        // Get merchant information from Square
        $merchantInfo = $this->getMerchantInfo($tokenData['access_token']);

        // Create shop
        $shop = Shop::create([
            'name' => $merchantInfo['business_name'] ?? 'Square Merchant',
            'platform' => 'square',
            'square_merchant_id' => $tokenData['merchant_id'],
            'settings' => [
                'currency' => $merchantInfo['currency'] ?? 'USD',
                'country' => $merchantInfo['country'] ?? 'US',
                'timezone' => $merchantInfo['timezone'] ?? 'America/New_York',
                'business_type' => $merchantInfo['business_type'] ?? null,
                'installation_date' => now()->toIso8601String(),
                'referral' => session('square_referral'),
            ],
        ]);

        // Create Square channel
        $channel = Channel::create([
            'shop_id' => $shop->id,
            'type' => 'square',
            'name' => 'Square POS',
            'credentials' => [
                'access_token' => encrypt($tokenData['access_token']),
                'refresh_token' => encrypt($tokenData['refresh_token']),
                'expires_at' => $tokenData['expires_at'] ?? null,
                'merchant_id' => $tokenData['merchant_id'],
                'token_type' => $tokenData['token_type'] ?? 'Bearer',
            ],
            'is_active' => true,
            'settings' => [
                'auto_sync_inventory' => true,
                'auto_sync_orders' => true,
                'sync_frequency_minutes' => 15,
            ],
        ]);

        // Create user account for merchant
        $email = $merchantInfo['email'] ?? $this->generateEmailFromMerchantId($tokenData['merchant_id']);

        $user = User::create([
            'shop_id' => $shop->id,
            'name' => $merchantInfo['owner_name'] ?? 'Square Merchant',
            'email' => $email,
            'password' => Hash::make(Str::random(32)), // Random password, will be set during onboarding
            'email_verified_at' => null, // Will verify during onboarding
            'role' => 'owner',
            'settings' => [
                'square_merchant' => true,
                'onboarding_completed' => false,
            ],
        ]);

        // Generate onboarding token
        $onboardingToken = Str::random(64);
        cache()->put('square_onboarding:' . $onboardingToken, [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'email' => $email,
        ], now()->addHours(24));

        return [
            'shop' => $shop,
            'channel' => $channel,
            'user' => $user,
            'onboarding_token' => $onboardingToken,
        ];
    }

    /**
     * Get merchant information from Square API.
     */
    protected function getMerchantInfo(string $accessToken): array
    {
        $baseUrl = config('app.env') === 'production'
            ? 'https://connect.squareup.com/v2/merchants'
            : 'https://connect.squareupsandbox.com/v2/merchants';

        try {
            $response = Http::withToken($accessToken)
                ->get($baseUrl);

            if ($response->successful()) {
                $data = $response->json();
                $merchant = $data['merchant'] ?? [];

                return [
                    'business_name' => $merchant['business_name'] ?? null,
                    'owner_name' => $merchant['owner_name'] ?? null,
                    'email' => $merchant['email_address'] ?? null,
                    'country' => $merchant['country'] ?? 'US',
                    'currency' => $merchant['currency'] ?? 'USD',
                    'timezone' => $merchant['timezone'] ?? 'America/New_York',
                    'business_type' => $merchant['business_type'] ?? null,
                    'language' => $merchant['language_code'] ?? 'en-US',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch merchant info from Square', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * Generate email from merchant ID if not available.
     */
    protected function generateEmailFromMerchantId(string $merchantId): string
    {
        return strtolower($merchantId) . '@square-merchant.multichannel.app';
    }

    /**
     * Get onboarding status.
     */
    public function getOnboardingStatus(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['error' => 'Token required'], 400);
        }

        $data = cache()->get('square_onboarding:' . $token);

        if (!$data) {
            return response()->json(['error' => 'Invalid or expired token'], 404);
        }

        $shop = Shop::find($data['shop_id']);
        $user = User::find($data['user_id']);

        if (!$shop || !$user) {
            return response()->json(['error' => 'Installation not found'], 404);
        }

        return response()->json([
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'settings' => $shop->settings,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $data['email'],
            ],
            'onboarding_completed' => $user->settings['onboarding_completed'] ?? false,
        ]);
    }

    /**
     * Complete onboarding.
     */
    public function completeOnboarding(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'preferences' => 'nullable|array',
        ]);

        $data = cache()->get('square_onboarding:' . $request->token);

        if (!$data) {
            return response()->json(['error' => 'Invalid or expired token'], 404);
        }

        DB::beginTransaction();

        try {
            $user = User::find($data['user_id']);
            $shop = Shop::find($data['shop_id']);

            // Update user
            $user->update([
                'name' => $request->name,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'email_verified_at' => now(),
                'settings' => array_merge($user->settings ?? [], [
                    'onboarding_completed' => true,
                    'preferences' => $request->preferences ?? [],
                ]),
            ]);

            // Update shop settings
            if ($request->has('preferences.business_hours')) {
                $shop->update([
                    'settings' => array_merge($shop->settings ?? [], [
                        'business_hours' => $request->input('preferences.business_hours'),
                    ]),
                ]);
            }

            // Create personal access token for API access
            $token = $user->createToken('square-app-access')->plainTextToken;

            // Clear onboarding cache
            cache()->forget('square_onboarding:' . $request->token);

            DB::commit();

            Log::info('Square merchant onboarding completed', [
                'shop_id' => $shop->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Onboarding completed successfully',
                'token' => $token,
                'shop' => $shop,
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Onboarding completion failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to complete onboarding',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Uninstall the app (merchant removes app from Square).
     */
    public function uninstall(Request $request): JsonResponse
    {
        // Square will call this webhook when merchant uninstalls
        // Verify the webhook signature first

        $merchantId = $request->input('merchant_id');

        if (!$merchantId) {
            return response()->json(['error' => 'Merchant ID required'], 400);
        }

        DB::beginTransaction();

        try {
            $shop = Shop::where('square_merchant_id', $merchantId)->first();

            if (!$shop) {
                return response()->json(['error' => 'Shop not found'], 404);
            }

            // Deactivate Square channel
            Channel::where('shop_id', $shop->id)
                ->where('type', 'square')
                ->update(['is_active' => false]);

            // Optionally: soft delete or mark shop as inactive
            // For now, just deactivate to preserve data
            $shop->update([
                'settings' => array_merge($shop->settings ?? [], [
                    'square_uninstalled_at' => now()->toIso8601String(),
                ]),
            ]);

            DB::commit();

            Log::info('Square App uninstalled', [
                'shop_id' => $shop->id,
                'merchant_id' => $merchantId,
            ]);

            return response()->json(['message' => 'App uninstalled successfully']);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Uninstall failed', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Uninstall failed'], 500);
        }
    }

    /**
     * Check app installation status.
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $merchantId = $request->input('merchant_id');

        if (!$merchantId) {
            return response()->json(['error' => 'Merchant ID required'], 400);
        }

        $shop = Shop::where('square_merchant_id', $merchantId)
            ->with(['channels' => function($query) {
                $query->where('type', 'square')->where('is_active', true);
            }])
            ->first();

        if (!$shop) {
            return response()->json([
                'installed' => false,
                'message' => 'App not installed',
            ]);
        }

        $isActive = $shop->channels->isNotEmpty();

        return response()->json([
            'installed' => true,
            'active' => $isActive,
            'shop_id' => $shop->id,
            'installation_date' => $shop->settings['installation_date'] ?? null,
        ]);
    }
}
