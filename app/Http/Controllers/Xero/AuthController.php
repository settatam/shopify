<?php

namespace App\Http\Controllers\Xero;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\Xero\XeroClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Show Xero connection form
     */
    public function connect(Request $request)
    {
        return inertia('Xero/Connect', [
            'shopId' => $request->user()->shop_id,
        ]);
    }

    /**
     * Initiate Xero OAuth flow
     */
    public function authorize(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        // Generate state for CSRF protection
        $state = Str::random(40);

        // Store state in session
        $request->session()->put('xero_oauth_state', $state);
        $request->session()->put('xero_oauth_shop_id', $shop->id);

        // Build authorization URL
        $params = [
            'response_type' => 'code',
            'client_id' => config('services.xero.client_id'),
            'redirect_uri' => config('services.xero.redirect_uri'),
            'scope' => config('services.xero.scopes'),
            'state' => $state,
        ];

        $authUrl = 'https://login.xero.com/identity/connect/authorize?' . http_build_query($params);

        return redirect($authUrl);
    }

    /**
     * Handle OAuth callback from Xero
     */
    public function callback(Request $request): RedirectResponse
    {
        // Verify state to prevent CSRF
        $state = $request->input('state');
        $sessionState = $request->session()->get('xero_oauth_state');

        if (!$state || $state !== $sessionState) {
            return redirect('/channels')->with('error', 'Invalid OAuth state. Please try again.');
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect('/channels')->with('error', 'No authorization code received from Xero.');
        }

        $shopId = $request->session()->get('xero_oauth_shop_id');
        if (!$shopId) {
            return redirect('/channels')->with('error', 'Session expired. Please try again.');
        }

        try {
            // Exchange authorization code for access token
            $tokenData = $this->exchangeCodeForToken($code);

            // Get connected tenants (organizations)
            $tenants = $this->getConnections($tokenData['access_token']);

            if (empty($tenants)) {
                throw new \Exception('No Xero organizations found. Please connect to at least one organization.');
            }

            // Use the first tenant (you could allow user to choose)
            $tenant = $tenants[0];

            // Get organization details
            $orgDetails = $this->getOrganisationDetails($tokenData['access_token'], $tenant['tenantId']);

            // Create or update channel
            $channel = Channel::updateOrCreate(
                [
                    'shop_id' => $shopId,
                    'type' => 'xero',
                    'external_id' => $tenant['tenantId'],
                ],
                [
                    'name' => $tenant['tenantName'] ?? 'Xero',
                    'status' => 'connected',
                    'auth_json' => [
                        'access_token' => $tokenData['access_token'],
                        'refresh_token' => $tokenData['refresh_token'],
                        'expires_at' => now()->addSeconds($tokenData['expires_in'])->timestamp,
                        'tenant_id' => $tenant['tenantId'],
                        'tenant_name' => $tenant['tenantName'],
                        'tenant_type' => $tenant['tenantType'] ?? null,
                        'organisation_name' => $orgDetails['Name'] ?? null,
                        'country_code' => $orgDetails['CountryCode'] ?? null,
                        'connected_at' => now()->toIso8601String(),
                    ],
                ]
            );

            // Cache accounts and tax rates
            $this->cacheAccountsAndTaxes($channel);

            // Clear session data
            $request->session()->forget('xero_oauth_state');
            $request->session()->forget('xero_oauth_shop_id');

            return redirect('/channels')->with('success', 'Xero connected successfully!');
        } catch (\Exception $e) {
            Log::error('Xero OAuth callback failed: ' . $e->getMessage());
            return redirect('/channels')->with('error', 'Failed to connect Xero: ' . $e->getMessage());
        }
    }

    /**
     * Exchange authorization code for access token
     */
    protected function exchangeCodeForToken(string $code): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->post('https://identity.xero.com/connect/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode(
                        config('services.xero.client_id') . ':' .
                        config('services.xero.client_secret')
                    ),
                ],
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => config('services.xero.redirect_uri'),
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (!isset($data['access_token'])) {
                throw new \Exception('No access token in response');
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Xero token exchange failed: ' . $e->getMessage());
            throw new \Exception('Failed to exchange authorization code for token');
        }
    }

    /**
     * Get connected tenants/organizations
     */
    protected function getConnections(string $accessToken): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->get('https://api.xero.com/connections', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Failed to get Xero connections: ' . $e->getMessage());
            throw new \Exception('Failed to retrieve Xero organizations');
        }
    }

    /**
     * Get organisation details
     */
    protected function getOrganisationDetails(string $accessToken, string $tenantId): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->get('https://api.xero.com/api.xro/2.0/Organisation', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Xero-tenant-id' => $tenantId,
                    'Accept' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $organisations = $data['Organisations'] ?? [];

            return !empty($organisations) ? $organisations[0] : [];
        } catch (\Exception $e) {
            Log::error('Failed to get Xero organisation details: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Disconnect Xero account
     */
    public function disconnect(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'xero') {
            return response()->json([
                'success' => false,
                'message' => 'This is not a Xero channel',
            ], 422);
        }

        if ($channel->shop_id !== $request->user()->shop_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Optionally revoke the connection with Xero
        try {
            $this->revokeConnection($channel);
        } catch (\Exception $e) {
            Log::warning('Failed to revoke Xero connection: ' . $e->getMessage());
        }

        $channel->update([
            'status' => 'disconnected',
            'auth_json' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Xero disconnected',
        ]);
    }

    /**
     * Revoke Xero connection
     */
    protected function revokeConnection(Channel $channel): void
    {
        $authJson = $channel->auth_json ?? [];
        $tenantId = $authJson['tenant_id'] ?? null;
        $accessToken = $authJson['access_token'] ?? null;

        if (!$tenantId || !$accessToken) {
            return;
        }

        $client = new \GuzzleHttp\Client();

        try {
            $client->delete("https://api.xero.com/connections/{$tenantId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to revoke Xero connection: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test Xero connection
     */
    public function test(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'xero' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Xero channel not connected',
            ], 422);
        }

        try {
            $client = new XeroClient($channel);
            $org = $client->getOrganisation();

            $organisation = $org['Organisations'][0] ?? [];

            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'data' => [
                    'organisation_name' => $organisation['Name'] ?? '',
                    'country_code' => $organisation['CountryCode'] ?? '',
                    'financial_year_end_day' => $organisation['FinancialYearEndDay'] ?? '',
                    'financial_year_end_month' => $organisation['FinancialYearEndMonth'] ?? '',
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
     * Get Xero accounts for mapping
     */
    public function getAccounts(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'xero' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Xero channel not connected',
            ], 422);
        }

        try {
            $client = new XeroClient($channel);

            $revenueAccounts = $client->getRevenueAccounts();
            $expenseAccounts = $client->getExpenseAccounts();

            return response()->json([
                'success' => true,
                'data' => [
                    'revenue_accounts' => $revenueAccounts['Accounts'] ?? [],
                    'expense_accounts' => $expenseAccounts['Accounts'] ?? [],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch accounts: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cache frequently used Xero data
     */
    protected function cacheAccountsAndTaxes(Channel $channel): void
    {
        try {
            $client = new XeroClient($channel);

            // Cache accounts
            $accounts = $client->getAccounts();
            \Cache::put("xero_accounts_{$channel->id}", $accounts, now()->addHours(24));

            // Cache tax rates
            $taxRates = $client->getTaxRates();
            \Cache::put("xero_tax_rates_{$channel->id}", $taxRates, now()->addHours(24));

            Log::info("Cached Xero accounts and tax rates for channel {$channel->id}");
        } catch (\Exception $e) {
            Log::warning('Failed to cache Xero data: ' . $e->getMessage());
        }
    }
}
