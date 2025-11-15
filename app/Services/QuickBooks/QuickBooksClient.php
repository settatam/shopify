<?php

namespace App\Services\QuickBooks;

use App\Models\Channel;
use App\Support\Http\HttpFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QuickBooksClient
{
    protected Client $http;
    protected string $baseUrl;
    protected Channel $channel;
    protected ?string $accessToken = null;
    protected ?string $realmId = null;

    public function __construct(Channel $channel)
    {
        $this->channel = $channel;

        // Use sandbox or production
        $sandbox = config('services.quickbooks.sandbox', true);
        $this->baseUrl = $sandbox
            ? 'https://sandbox-quickbooks.api.intuit.com/v3'
            : 'https://quickbooks.api.intuit.com/v3';

        $this->http = HttpFactory::make([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->realmId = $channel->auth_json['realm_id'] ?? null;
    }

    /**
     * Get access token from channel
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $authJson = $this->channel->auth_json ?? [];
        $token = $authJson['access_token'] ?? null;

        if (!$token) {
            throw new \Exception('No QuickBooks access token found. Please reconnect your QuickBooks account.');
        }

        // Check if token is expired and refresh if needed
        $expiresAt = $authJson['expires_at'] ?? null;
        if ($expiresAt && now()->timestamp >= $expiresAt) {
            $token = $this->refreshAccessToken();
        }

        $this->accessToken = $token;
        return $token;
    }

    /**
     * Refresh access token
     */
    protected function refreshAccessToken(): string
    {
        $authJson = $this->channel->auth_json ?? [];
        $refreshToken = $authJson['refresh_token'] ?? null;

        if (!$refreshToken) {
            throw new \Exception('No refresh token available. Please reconnect your QuickBooks account.');
        }

        try {
            $client = new Client();
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
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            // Update channel with new tokens
            $this->channel->update([
                'auth_json' => array_merge($authJson, [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'],
                    'expires_at' => now()->addSeconds($data['expires_in'])->timestamp,
                    'x_refresh_token_expires_at' => now()->addSeconds($data['x_refresh_token_expires_in'])->timestamp,
                ]),
            ]);

            return $data['access_token'];
        } catch (RequestException $e) {
            Log::error('QuickBooks token refresh failed: ' . $e->getMessage());
            throw new \Exception('Failed to refresh QuickBooks access token');
        }
    }

    /**
     * Make authenticated API request
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        $token = $this->getAccessToken();

        if (!$this->realmId) {
            throw new \Exception('No QuickBooks company ID (realm ID) found');
        }

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        try {
            $response = $this->http->request($method, "/company/{$this->realmId}/{$uri}", $options);
            $body = $response->getBody()->getContents();
            return json_decode($body, true) ?? [];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Log::error("QuickBooks API error [{$method} {$uri}]: " . $e->getMessage() . " - " . $body);
            throw $e;
        }
    }

    /**
     * Query entities using SQL-like syntax
     */
    public function query(string $query): array
    {
        return $this->request('GET', 'query', [
            'query' => ['query' => $query],
        ]);
    }

    // ==================== CUSTOMER METHODS ====================

    /**
     * Get all customers
     */
    public function getCustomers(int $maxResults = 100): array
    {
        return $this->query("SELECT * FROM Customer MAXRESULTS {$maxResults}");
    }

    /**
     * Get customer by ID
     */
    public function getCustomer(string $customerId): array
    {
        return $this->request('GET', "customer/{$customerId}");
    }

    /**
     * Create customer
     */
    public function createCustomer(array $customerData): array
    {
        return $this->request('POST', 'customer', [
            'json' => $customerData,
        ]);
    }

    /**
     * Update customer
     */
    public function updateCustomer(string $customerId, array $customerData): array
    {
        // QuickBooks requires SyncToken for updates
        $existing = $this->getCustomer($customerId);
        $customerData['SyncToken'] = $existing['Customer']['SyncToken'];
        $customerData['Id'] = $customerId;

        return $this->request('POST', 'customer', [
            'json' => $customerData,
        ]);
    }

    // ==================== ITEM (PRODUCT) METHODS ====================

    /**
     * Get all items
     */
    public function getItems(int $maxResults = 100): array
    {
        return $this->query("SELECT * FROM Item MAXRESULTS {$maxResults}");
    }

    /**
     * Get item by ID
     */
    public function getItem(string $itemId): array
    {
        return $this->request('GET', "item/{$itemId}");
    }

    /**
     * Create item
     */
    public function createItem(array $itemData): array
    {
        return $this->request('POST', 'item', [
            'json' => $itemData,
        ]);
    }

    /**
     * Update item
     */
    public function updateItem(string $itemId, array $itemData): array
    {
        $existing = $this->getItem($itemId);
        $itemData['SyncToken'] = $existing['Item']['SyncToken'];
        $itemData['Id'] = $itemId;

        return $this->request('POST', 'item', [
            'json' => $itemData,
        ]);
    }

    // ==================== INVOICE METHODS ====================

    /**
     * Get all invoices
     */
    public function getInvoices(int $maxResults = 100): array
    {
        return $this->query("SELECT * FROM Invoice MAXRESULTS {$maxResults}");
    }

    /**
     * Get invoice by ID
     */
    public function getInvoice(string $invoiceId): array
    {
        return $this->request('GET', "invoice/{$invoiceId}");
    }

    /**
     * Create invoice
     */
    public function createInvoice(array $invoiceData): array
    {
        return $this->request('POST', 'invoice', [
            'json' => $invoiceData,
        ]);
    }

    /**
     * Update invoice
     */
    public function updateInvoice(string $invoiceId, array $invoiceData): array
    {
        $existing = $this->getInvoice($invoiceId);
        $invoiceData['SyncToken'] = $existing['Invoice']['SyncToken'];
        $invoiceData['Id'] = $invoiceId;

        return $this->request('POST', 'invoice', [
            'json' => $invoiceData,
        ]);
    }

    /**
     * Delete (void) invoice
     */
    public function deleteInvoice(string $invoiceId): array
    {
        $existing = $this->getInvoice($invoiceId);

        return $this->request('POST', 'invoice', [
            'query' => ['operation' => 'delete'],
            'json' => [
                'Id' => $invoiceId,
                'SyncToken' => $existing['Invoice']['SyncToken'],
            ],
        ]);
    }

    // ==================== SALES RECEIPT METHODS ====================

    /**
     * Get all sales receipts
     */
    public function getSalesReceipts(int $maxResults = 100): array
    {
        return $this->query("SELECT * FROM SalesReceipt MAXRESULTS {$maxResults}");
    }

    /**
     * Get sales receipt by ID
     */
    public function getSalesReceipt(string $receiptId): array
    {
        return $this->request('GET', "salesreceipt/{$receiptId}");
    }

    /**
     * Create sales receipt
     */
    public function createSalesReceipt(array $receiptData): array
    {
        return $this->request('POST', 'salesreceipt', [
            'json' => $receiptData,
        ]);
    }

    /**
     * Update sales receipt
     */
    public function updateSalesReceipt(string $receiptId, array $receiptData): array
    {
        $existing = $this->getSalesReceipt($receiptId);
        $receiptData['SyncToken'] = $existing['SalesReceipt']['SyncToken'];
        $receiptData['Id'] = $receiptId;

        return $this->request('POST', 'salesreceipt', [
            'json' => $receiptData,
        ]);
    }

    // ==================== PAYMENT METHODS ====================

    /**
     * Get all payments
     */
    public function getPayments(int $maxResults = 100): array
    {
        return $this->query("SELECT * FROM Payment MAXRESULTS {$maxResults}");
    }

    /**
     * Get payment by ID
     */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', "payment/{$paymentId}");
    }

    /**
     * Create payment
     */
    public function createPayment(array $paymentData): array
    {
        return $this->request('POST', 'payment', [
            'json' => $paymentData,
        ]);
    }

    // ==================== ACCOUNT METHODS ====================

    /**
     * Get all accounts
     */
    public function getAccounts(int $maxResults = 100): array
    {
        return $this->query("SELECT * FROM Account MAXRESULTS {$maxResults}");
    }

    /**
     * Get account by ID
     */
    public function getAccount(string $accountId): array
    {
        return $this->request('GET', "account/{$accountId}");
    }

    /**
     * Get income accounts (for sales)
     */
    public function getIncomeAccounts(): array
    {
        return $this->query("SELECT * FROM Account WHERE AccountType = 'Income'");
    }

    /**
     * Get expense accounts
     */
    public function getExpenseAccounts(): array
    {
        return $this->query("SELECT * FROM Account WHERE AccountType = 'Expense'");
    }

    // ==================== TAX METHODS ====================

    /**
     * Get all tax codes
     */
    public function getTaxCodes(int $maxResults = 100): array
    {
        return $this->query("SELECT * FROM TaxCode MAXRESULTS {$maxResults}");
    }

    /**
     * Get tax rates
     */
    public function getTaxRates(int $maxResults = 100): array
    {
        return $this->query("SELECT * FROM TaxRate MAXRESULTS {$maxResults}");
    }

    // ==================== COMPANY INFO ====================

    /**
     * Get company info
     */
    public function getCompanyInfo(): array
    {
        return $this->request('GET', 'companyinfo/' . $this->realmId);
    }

    /**
     * Get preferences
     */
    public function getPreferences(): array
    {
        return $this->request('GET', 'preferences');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Build customer data from order
     */
    public function buildCustomerData(array $orderData): array
    {
        return [
            'DisplayName' => $orderData['customer_name'] ?? 'Guest Customer',
            'PrimaryEmailAddr' => [
                'Address' => $orderData['customer_email'] ?? null,
            ],
            'PrimaryPhone' => [
                'FreeFormNumber' => $orderData['shipping_address']['phone'] ?? null,
            ],
            'BillAddr' => [
                'Line1' => $orderData['billing_address']['address1'] ?? $orderData['shipping_address']['address1'] ?? null,
                'Line2' => $orderData['billing_address']['address2'] ?? $orderData['shipping_address']['address2'] ?? null,
                'City' => $orderData['billing_address']['city'] ?? $orderData['shipping_address']['city'] ?? null,
                'CountrySubDivisionCode' => $orderData['billing_address']['province'] ?? $orderData['shipping_address']['province'] ?? null,
                'PostalCode' => $orderData['billing_address']['zip'] ?? $orderData['shipping_address']['zip'] ?? null,
                'Country' => $orderData['billing_address']['country'] ?? $orderData['shipping_address']['country'] ?? 'US',
            ],
            'ShipAddr' => [
                'Line1' => $orderData['shipping_address']['address1'] ?? null,
                'Line2' => $orderData['shipping_address']['address2'] ?? null,
                'City' => $orderData['shipping_address']['city'] ?? null,
                'CountrySubDivisionCode' => $orderData['shipping_address']['province'] ?? null,
                'PostalCode' => $orderData['shipping_address']['zip'] ?? null,
                'Country' => $orderData['shipping_address']['country'] ?? 'US',
            ],
        ];
    }

    /**
     * Build invoice/sales receipt line items from order
     */
    public function buildLineItems(array $orderItems, ?string $incomeAccountId = null): array
    {
        $lines = [];

        foreach ($orderItems as $item) {
            $line = [
                'Amount' => $item['total'] ?? ($item['price'] * $item['quantity']),
                'DetailType' => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'Qty' => $item['quantity'] ?? 1,
                    'UnitPrice' => $item['price'] ?? 0,
                ],
            ];

            // Add item reference if we have it
            if (!empty($item['quickbooks_item_id'])) {
                $line['SalesItemLineDetail']['ItemRef'] = [
                    'value' => $item['quickbooks_item_id'],
                ];
            }

            // Add income account if specified
            if ($incomeAccountId) {
                $line['SalesItemLineDetail']['IncomeAccountRef'] = [
                    'value' => $incomeAccountId,
                ];
            }

            // Add description
            if (!empty($item['title'])) {
                $line['Description'] = $item['title'];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Build item data from product
     */
    public function buildItemData(array $productData, ?string $incomeAccountId = null): array
    {
        $itemData = [
            'Name' => substr($productData['title'] ?? 'Product', 0, 100),
            'Description' => $productData['description'] ?? '',
            'Type' => 'Inventory', // or 'NonInventory' or 'Service'
            'TrackQtyOnHand' => true,
            'QtyOnHand' => $productData['quantity'] ?? 0,
            'InvStartDate' => now()->format('Y-m-d'),
        ];

        // Add SKU if available
        if (!empty($productData['sku'])) {
            $itemData['Sku'] = $productData['sku'];
        }

        // Add income account
        if ($incomeAccountId) {
            $itemData['IncomeAccountRef'] = [
                'value' => $incomeAccountId,
            ];
        }

        // Add expense account (required for inventory items)
        // You'll need to configure this based on your QuickBooks setup
        if (!empty($productData['expense_account_id'])) {
            $itemData['ExpenseAccountRef'] = [
                'value' => $productData['expense_account_id'],
            ];
        }

        // Add asset account (required for inventory items)
        if (!empty($productData['asset_account_id'])) {
            $itemData['AssetAccountRef'] = [
                'value' => $productData['asset_account_id'],
            ];
        }

        // Add unit price
        if (isset($productData['price'])) {
            $itemData['UnitPrice'] = $productData['price'];
        }

        return $itemData;
    }
}
