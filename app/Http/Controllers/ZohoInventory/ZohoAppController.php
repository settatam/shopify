<?php

namespace App\Http\Controllers\ZohoInventory;

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

class ZohoAppController extends Controller
{
    /**
     * Zoho OAuth scopes required for the app.
     */
    protected array $scopes = [
        'ZohoInventory.FullAccess.all',
        'ZohoBooks.FullAccess.all', // Optional, for accounting integration
    ];

    /**
     * Initiate Zoho App installation.
     * This is the entry point when a user clicks "Install" in Zoho Marketplace.
     */
    public function install(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Generate state for CSRF protection
        $state = Str::random(40);
        session(['zoho_oauth_state' => $state]);

        // Store any pre-install data
        if ($request->has('ref')) {
            session(['zoho_referral' => $request->input('ref')]);
        }

        // Determine Zoho data center
        $dataCenter = $request->input('dc', 'com'); // com, eu, in, au, jp, ca
        session(['zoho_data_center' => $dataCenter]);

        // Build OAuth authorization URL
        $baseUrl = "https://accounts.zoho.{$dataCenter}/oauth/v2/auth";

        $query = http_build_query([
            'client_id' => config('services.zoho.client_id'),
            'scope' => implode(',', $this->scopes),
            'response_type' => 'code',
            'redirect_uri' => config('services.zoho.redirect_uri'),
            'access_type' => 'offline', // Get refresh token
            'state' => $state,
        ]);

        Log::info('Zoho App installation initiated', [
            'state' => $state,
            'data_center' => $dataCenter,
            'referral' => session('zoho_referral'),
        ]);

        return redirect($baseUrl . '?' . $query);
    }

    /**
     * Handle OAuth callback from Zoho.
     * Zoho redirects here after user authorizes the app.
     */
    public function callback(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Verify state to prevent CSRF attacks
        if (!$request->has('state') || $request->state !== session('zoho_oauth_state')) {
            Log::error('Zoho OAuth state mismatch', [
                'received_state' => $request->state,
                'session_state' => session('zoho_oauth_state'),
            ]);
            return redirect('/install-error?error=invalid_state');
        }

        // Check for authorization errors
        if ($request->has('error')) {
            Log::error('Zoho OAuth authorization error', [
                'error' => $request->error,
                'error_description' => $request->error_description ?? 'No description',
            ]);
            return redirect('/install-error?error=' . $request->error);
        }

        // Exchange authorization code for access token
        try {
            $dataCenter = session('zoho_data_center', 'com');
            $tokenData = $this->exchangeCodeForToken($request->code, $dataCenter);

            // Get organization list and let user select
            $organizations = $this->getOrganizations($tokenData['access_token'], $dataCenter);

            if (empty($organizations)) {
                throw new \Exception('No Zoho Inventory organizations found');
            }

            // If only one organization, auto-select it
            if (count($organizations) === 1) {
                DB::beginTransaction();

                $installation = $this->createInstallation(
                    $tokenData,
                    $organizations[0],
                    $dataCenter,
                    $request
                );

                DB::commit();

                // Clear OAuth session data
                session()->forget(['zoho_oauth_state', 'zoho_referral', 'zoho_data_center']);

                Log::info('Zoho App installation completed successfully', [
                    'shop_id' => $installation['shop']->id,
                    'user_id' => $installation['user']->id,
                    'organization_id' => $organizations[0]['organization_id'],
                ]);

                // Redirect to onboarding
                return redirect('/zoho-onboarding?token=' . $installation['onboarding_token']);
            }

            // Multiple organizations - show selection screen
            // Store token data temporarily
            $selectionToken = Str::random(64);
            cache()->put('zoho_org_selection:' . $selectionToken, [
                'token_data' => $tokenData,
                'organizations' => $organizations,
                'data_center' => $dataCenter,
            ], now()->addHours(1));

            return redirect('/zoho-select-organization?token=' . $selectionToken);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Zoho App installation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect('/install-error?error=installation_failed');
        }
    }

    /**
     * Handle organization selection (when user has multiple orgs).
     */
    public function selectOrganization(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'token' => 'required|string',
            'organization_id' => 'required|string',
        ]);

        $data = cache()->get('zoho_org_selection:' . $request->token);

        if (!$data) {
            return redirect('/install-error?error=session_expired');
        }

        DB::beginTransaction();

        try {
            // Find selected organization
            $selectedOrg = collect($data['organizations'])
                ->firstWhere('organization_id', $request->organization_id);

            if (!$selectedOrg) {
                throw new \Exception('Selected organization not found');
            }

            $installation = $this->createInstallation(
                $data['token_data'],
                $selectedOrg,
                $data['data_center'],
                $request
            );

            DB::commit();

            // Clear cache
            cache()->forget('zoho_org_selection:' . $request->token);

            Log::info('Zoho organization selected and installation completed', [
                'shop_id' => $installation['shop']->id,
                'organization_id' => $selectedOrg['organization_id'],
            ]);

            return redirect('/zoho-onboarding?token=' . $installation['onboarding_token']);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Zoho organization selection failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect('/install-error?error=organization_selection_failed');
        }
    }

    /**
     * Exchange authorization code for access token.
     */
    protected function exchangeCodeForToken(string $code, string $dataCenter): array
    {
        $tokenUrl = "https://accounts.zoho.{$dataCenter}/oauth/v2/token";

        $response = Http::asForm()->post($tokenUrl, [
            'client_id' => config('services.zoho.client_id'),
            'client_secret' => config('services.zoho.client_secret'),
            'code' => $code,
            'redirect_uri' => config('services.zoho.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange authorization code: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get list of Zoho Inventory organizations.
     */
    protected function getOrganizations(string $accessToken, string $dataCenter): array
    {
        $apiUrl = "https://www.zohoapis.{$dataCenter}/inventory/v1/organizations";

        $response = Http::withToken($accessToken)
            ->get($apiUrl);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch organizations: ' . $response->body());
        }

        $data = $response->json();
        return $data['organizations'] ?? [];
    }

    /**
     * Create shop, channel, and user for the Zoho organization.
     */
    protected function createInstallation(
        array $tokenData,
        array $organization,
        string $dataCenter,
        Request $request
    ): array {
        // Create shop
        $shop = Shop::create([
            'name' => $organization['name'],
            'platform' => 'zoho',
            'zoho_organization_id' => $organization['organization_id'],
            'settings' => [
                'currency' => $organization['currency_code'] ?? 'USD',
                'country' => $organization['country_code'] ?? 'US',
                'timezone' => $organization['time_zone'] ?? 'America/New_York',
                'data_center' => $dataCenter,
                'installation_date' => now()->toIso8601String(),
                'referral' => session('zoho_referral'),
            ],
        ]);

        // Create Zoho Inventory channel
        $channel = Channel::create([
            'shop_id' => $shop->id,
            'type' => 'zoho_inventory',
            'name' => 'Zoho Inventory',
            'credentials' => [
                'access_token' => encrypt($tokenData['access_token']),
                'refresh_token' => encrypt($tokenData['refresh_token']),
                'expires_in' => $tokenData['expires_in'] ?? 3600,
                'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600)->toIso8601String(),
                'api_domain' => $tokenData['api_domain'] ?? "https://www.zohoapis.{$dataCenter}",
                'organization_id' => $organization['organization_id'],
                'data_center' => $dataCenter,
            ],
            'is_active' => true,
            'settings' => [
                'auto_sync_inventory' => true,
                'auto_sync_orders' => true,
                'sync_frequency_minutes' => 15,
            ],
        ]);

        // Create user account
        $email = $organization['email'] ?? $this->generateEmailFromOrgId($organization['organization_id']);

        $user = User::create([
            'shop_id' => $shop->id,
            'name' => $organization['contact_name'] ?? 'Zoho User',
            'email' => $email,
            'password' => Hash::make(Str::random(32)), // Random password, will be set during onboarding
            'email_verified_at' => null,
            'role' => 'owner',
            'settings' => [
                'zoho_user' => true,
                'onboarding_completed' => false,
            ],
        ]);

        // Generate onboarding token
        $onboardingToken = Str::random(64);
        cache()->put('zoho_onboarding:' . $onboardingToken, [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'email' => $email,
            'organization_id' => $organization['organization_id'],
        ], now()->addHours(24));

        return [
            'shop' => $shop,
            'channel' => $channel,
            'user' => $user,
            'onboarding_token' => $onboardingToken,
        ];
    }

    /**
     * Generate email from organization ID if not available.
     */
    protected function generateEmailFromOrgId(string $orgId): string
    {
        return 'org-' . strtolower($orgId) . '@zoho-user.multichannel.app';
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

        $data = cache()->get('zoho_onboarding:' . $token);

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
            'organization_id' => $data['organization_id'],
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

        $data = cache()->get('zoho_onboarding:' . $request->token);

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

            // Create personal access token for API access
            $token = $user->createToken('zoho-app-access')->plainTextToken;

            // Clear onboarding cache
            cache()->forget('zoho_onboarding:' . $request->token);

            DB::commit();

            Log::info('Zoho user onboarding completed', [
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

            Log::error('Zoho onboarding completion failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to complete onboarding',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get organization selection options.
     */
    public function getOrganizationOptions(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['error' => 'Token required'], 400);
        }

        $data = cache()->get('zoho_org_selection:' . $token);

        if (!$data) {
            return response()->json(['error' => 'Invalid or expired token'], 404);
        }

        return response()->json([
            'organizations' => $data['organizations'],
            'data_center' => $data['data_center'],
        ]);
    }

    /**
     * Uninstall the app (user removes app from Zoho).
     */
    public function uninstall(Request $request): JsonResponse
    {
        $organizationId = $request->input('organization_id');

        if (!$organizationId) {
            return response()->json(['error' => 'Organization ID required'], 400);
        }

        DB::beginTransaction();

        try {
            $shop = Shop::where('zoho_organization_id', $organizationId)->first();

            if (!$shop) {
                return response()->json(['error' => 'Shop not found'], 404);
            }

            // Deactivate Zoho channel
            Channel::where('shop_id', $shop->id)
                ->where('type', 'zoho_inventory')
                ->update(['is_active' => false]);

            // Mark shop as uninstalled
            $shop->update([
                'settings' => array_merge($shop->settings ?? [], [
                    'zoho_uninstalled_at' => now()->toIso8601String(),
                ]),
            ]);

            DB::commit();

            Log::info('Zoho App uninstalled', [
                'shop_id' => $shop->id,
                'organization_id' => $organizationId,
            ]);

            return response()->json(['message' => 'App uninstalled successfully']);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Zoho uninstall failed', [
                'organization_id' => $organizationId,
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
        $organizationId = $request->input('organization_id');

        if (!$organizationId) {
            return response()->json(['error' => 'Organization ID required'], 400);
        }

        $shop = Shop::where('zoho_organization_id', $organizationId)
            ->with(['channels' => function($query) {
                $query->where('type', 'zoho_inventory')->where('is_active', true);
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
