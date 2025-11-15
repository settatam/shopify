<?php

namespace App\Http\Controllers\QuickBooks;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Show QuickBooks connection form
     */
    public function connect(Request $request)
    {
        return inertia('QuickBooks/Connect', [
            'shopId' => $request->user()->shop_id,
        ]);
    }

    /**
     * Initiate QuickBooks OAuth flow
     */
    public function authorize(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        // Generate state for CSRF protection
        $state = Str::random(40);

        // Store state in session
        $request->session()->put('quickbooks_oauth_state', $state);
        $request->session()->put('quickbooks_oauth_shop_id', $shop->id);

        // Build authorization URL
        $sandbox = config('services.quickbooks.sandbox', true);
        $authUrl = $sandbox
            ? 'https://appcenter.intuit.com/connect/oauth2'
            : 'https://appcenter.intuit.com/connect/oauth2';

        $params = [
            'client_id' => config('services.quickbooks.client_id'),
            'redirect_uri' => config('services.quickbooks.redirect_uri'),
            'response_type' => 'code',
            'scope' => config('services.quickbooks.scopes'),
            'state' => $state,
        ];

        $authorizationUrl = $authUrl . '?' . http_build_query($params);

        return redirect($authorizationUrl);
    }

    /**
     * Handle OAuth callback from QuickBooks
     */
    public function callback(Request $request): RedirectResponse
    {
        // Verify state to prevent CSRF
        $state = $request->input('state');
        $sessionState = $request->session()->get('quickbooks_oauth_state');

        if (!$state || $state !== $sessionState) {
            return redirect('/channels')->with('error', 'Invalid OAuth state. Please try again.');
        }

        $code = $request->input('code');
        $realmId = $request->input('realmId'); // QuickBooks company ID

        if (!$code || !$realmId) {
            return redirect('/channels')->with('error', 'No authorization code or company ID received from QuickBooks.');
        }

        $shopId = $request->session()->get('quickbooks_oauth_shop_id');
        if (!$shopId) {
            return redirect('/channels')->with('error', 'Session expired. Please try again.');
        }

        try {
            // Exchange authorization code for access token
            $tokenData = $this->exchangeCodeForToken($code);

            // Get company info
            $companyInfo = $this->getCompanyInfo($tokenData['access_token'], $realmId);

            // Create or update channel
            $channel = Channel::updateOrCreate(
                [
                    'shop_id' => $shopId,
                    'type' => 'quickbooks',
                    'external_id' => $realmId,
                ],
                [
                    'name' => $companyInfo['CompanyName'] ?? 'QuickBooks',
                    'status' => 'connected',
                    'auth_json' => [
                        'access_token' => $tokenData['access_token'],
                        'refresh_token' => $tokenData['refresh_token'],
                        'expires_at' => now()->addSeconds($tokenData['expires_in'])->timestamp,
                        'x_refresh_token_expires_at' => now()->addSeconds($tokenData['x_refresh_token_expires_in'])->timestamp,
                        'realm_id' => $realmId,
                        'company_name' => $companyInfo['CompanyName'] ?? null,
                        'country' => $companyInfo['Country'] ?? 'US',
                        'connected_at' => now()->toIso8601String(),
                    ],
                ]
            );

            // Fetch and cache accounts, tax codes
            $this->cacheAccountsAndTaxes($channel);

            // Clear session data
            $request->session()->forget('quickbooks_oauth_state');
            $request->session()->forget('quickbooks_oauth_shop_id');

            return redirect('/channels')->with('success', 'QuickBooks connected successfully!');
        } catch (\Exception $e) {
            Log::error('QuickBooks OAuth callback failed: ' . $e->getMessage());
            return redirect('/channels')->with('error', 'Failed to connect QuickBooks: ' . $e->getMessage());
        }
    }

    /**
     * Exchange authorization code for access token
     */
    protected function exchangeCodeForToken(string $code): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->post('https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode(
                        config('services.quickbooks.client_id') . ':' .
                        config('services.quickbooks.client_secret')
                    ),
                ],
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => config('services.quickbooks.redirect_uri'),
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (!isset($data['access_token'])) {
                throw new \Exception('No access token in response');
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('QuickBooks token exchange failed: ' . $e->getMessage());
            throw new \Exception('Failed to exchange authorization code for token');
        }
    }

    /**
     * Get company info
     */
    protected function getCompanyInfo(string $accessToken, string $realmId): array
    {
        $client = new \GuzzleHttp\Client();

        try {
            $sandbox = config('services.quickbooks.sandbox', true);
            $baseUrl = $sandbox
                ? 'https://sandbox-quickbooks.api.intuit.com/v3'
                : 'https://quickbooks.api.intuit.com/v3';

            $response = $client->get("{$baseUrl}/company/{$realmId}/companyinfo/{$realmId}", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            return $data['CompanyInfo'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get QuickBooks company info: ' . $e->getMessage());
            throw new \Exception('Failed to retrieve QuickBooks company information');
        }
    }

    /**
     * Disconnect QuickBooks account
     */
    public function disconnect(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'quickbooks') {
            return response()->json([
                'success' => false,
                'message' => 'This is not a QuickBooks channel',
            ], 422);
        }

        if ($channel->shop_id !== $request->user()->shop_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Optionally revoke the token with QuickBooks
        try {
            $this->revokeToken($channel);
        } catch (\Exception $e) {
            Log::warning('Failed to revoke QuickBooks token: ' . $e->getMessage());
        }

        $channel->update([
            'status' => 'disconnected',
            'auth_json' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QuickBooks disconnected',
        ]);
    }

    /**
     * Revoke QuickBooks token
     */
    protected function revokeToken(Channel $channel): void
    {
        $authJson = $channel->auth_json ?? [];
        $token = $authJson['access_token'] ?? null;

        if (!$token) {
            return;
        }

        $client = new \GuzzleHttp\Client();

        try {
            $client->post('https://developer.api.intuit.com/v2/oauth2/tokens/revoke', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode(
                        config('services.quickbooks.client_id') . ':' .
                        config('services.quickbooks.client_secret')
                    ),
                ],
                'form_params' => [
                    'token' => $token,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to revoke QuickBooks token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test QuickBooks connection
     */
    public function test(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'quickbooks' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'QuickBooks channel not connected',
            ], 422);
        }

        try {
            $client = new QuickBooksClient($channel);
            $companyInfo = $client->getCompanyInfo();

            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'data' => [
                    'company_name' => $companyInfo['CompanyInfo']['CompanyName'] ?? '',
                    'country' => $companyInfo['CompanyInfo']['Country'] ?? '',
                    'fiscal_year_start_month' => $companyInfo['CompanyInfo']['FiscalYearStartMonth'] ?? '',
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
     * Get QuickBooks accounts for mapping
     */
    public function getAccounts(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'quickbooks' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'QuickBooks channel not connected',
            ], 422);
        }

        try {
            $client = new QuickBooksClient($channel);

            $incomeAccounts = $client->getIncomeAccounts();
            $expenseAccounts = $client->getExpenseAccounts();

            return response()->json([
                'success' => true,
                'data' => [
                    'income_accounts' => $incomeAccounts['QueryResponse']['Account'] ?? [],
                    'expense_accounts' => $expenseAccounts['QueryResponse']['Account'] ?? [],
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
     * Cache frequently used QuickBooks data
     */
    protected function cacheAccountsAndTaxes(Channel $channel): void
    {
        try {
            $client = new QuickBooksClient($channel);

            // Cache accounts
            $accounts = $client->getAccounts();
            \Cache::put("quickbooks_accounts_{$channel->id}", $accounts, now()->addHours(24));

            // Cache tax codes
            $taxCodes = $client->getTaxCodes();
            \Cache::put("quickbooks_tax_codes_{$channel->id}", $taxCodes, now()->addHours(24));

            Log::info("Cached QuickBooks accounts and tax codes for channel {$channel->id}");
        } catch (\Exception $e) {
            Log::warning('Failed to cache QuickBooks data: ' . $e->getMessage());
        }
    }
}
