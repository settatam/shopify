<?php

namespace App\Services\Xero;

use App\Models\Channel;
use App\Support\Http\HttpFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class XeroClient
{
    protected Client $http;
    protected string $baseUrl = 'https://api.xero.com/api.xro/2.0';
    protected Channel $channel;
    protected ?string $accessToken = null;
    protected ?string $tenantId = null;

    public function __construct(Channel $channel)
    {
        $this->channel = $channel;

        $this->http = HttpFactory::make([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->tenantId = $channel->auth_json['tenant_id'] ?? null;
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
            throw new \Exception('No Xero access token found. Please reconnect your Xero account.');
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
            throw new \Exception('No refresh token available. Please reconnect your Xero account.');
        }

        try {
            $client = new Client();
            $response = $client->post('https://identity.xero.com/connect/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode(
                        config('services.xero.client_id') . ':' .
                        config('services.xero.client_secret')
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
                ]),
            ]);

            return $data['access_token'];
        } catch (RequestException $e) {
            Log::error('Xero token refresh failed: ' . $e->getMessage());
            throw new \Exception('Failed to refresh Xero access token');
        }
    }

    /**
     * Make authenticated API request
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        $token = $this->getAccessToken();

        if (!$this->tenantId) {
            throw new \Exception('No Xero tenant ID found');
        }

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $token,
            'Xero-tenant-id' => $this->tenantId,
        ]);

        try {
            $response = $this->http->request($method, $uri, $options);
            $body = $response->getBody()->getContents();
            return json_decode($body, true) ?? [];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Log::error("Xero API error [{$method} {$uri}]: " . $e->getMessage() . " - " . $body);
            throw $e;
        }
    }

    // ==================== CONTACT (CUSTOMER) METHODS ====================

    /**
     * Get all contacts
     */
    public function getContacts(array $params = []): array
    {
        return $this->request('GET', 'Contacts', [
            'query' => $params,
        ]);
    }

    /**
     * Get contact by ID
     */
    public function getContact(string $contactId): array
    {
        return $this->request('GET', "Contacts/{$contactId}");
    }

    /**
     * Create contact
     */
    public function createContact(array $contactData): array
    {
        return $this->request('POST', 'Contacts', [
            'json' => ['Contacts' => [$contactData]],
        ]);
    }

    /**
     * Update contact
     */
    public function updateContact(string $contactId, array $contactData): array
    {
        $contactData['ContactID'] = $contactId;

        return $this->request('POST', 'Contacts', [
            'json' => ['Contacts' => [$contactData]],
        ]);
    }

    // ==================== ITEM METHODS ====================

    /**
     * Get all items
     */
    public function getItems(array $params = []): array
    {
        return $this->request('GET', 'Items', [
            'query' => $params,
        ]);
    }

    /**
     * Get item by ID
     */
    public function getItem(string $itemId): array
    {
        return $this->request('GET', "Items/{$itemId}");
    }

    /**
     * Create item
     */
    public function createItem(array $itemData): array
    {
        return $this->request('POST', 'Items', [
            'json' => ['Items' => [$itemData]],
        ]);
    }

    /**
     * Update item
     */
    public function updateItem(string $itemId, array $itemData): array
    {
        $itemData['ItemID'] = $itemId;

        return $this->request('POST', 'Items', [
            'json' => ['Items' => [$itemData]],
        ]);
    }

    // ==================== INVOICE METHODS ====================

    /**
     * Get all invoices
     */
    public function getInvoices(array $params = []): array
    {
        return $this->request('GET', 'Invoices', [
            'query' => $params,
        ]);
    }

    /**
     * Get invoice by ID
     */
    public function getInvoice(string $invoiceId): array
    {
        return $this->request('GET', "Invoices/{$invoiceId}");
    }

    /**
     * Create invoice
     */
    public function createInvoice(array $invoiceData): array
    {
        return $this->request('POST', 'Invoices', [
            'json' => ['Invoices' => [$invoiceData]],
        ]);
    }

    /**
     * Update invoice
     */
    public function updateInvoice(string $invoiceId, array $invoiceData): array
    {
        $invoiceData['InvoiceID'] = $invoiceId;

        return $this->request('POST', 'Invoices', [
            'json' => ['Invoices' => [$invoiceData]],
        ]);
    }

    /**
     * Delete invoice
     */
    public function deleteInvoice(string $invoiceId): array
    {
        return $this->request('POST', "Invoices/{$invoiceId}", [
            'json' => ['Invoices' => [[
                'InvoiceID' => $invoiceId,
                'Status' => 'DELETED',
            ]]],
        ]);
    }

    // ==================== PAYMENT METHODS ====================

    /**
     * Get all payments
     */
    public function getPayments(array $params = []): array
    {
        return $this->request('GET', 'Payments', [
            'query' => $params,
        ]);
    }

    /**
     * Get payment by ID
     */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', "Payments/{$paymentId}");
    }

    /**
     * Create payment
     */
    public function createPayment(array $paymentData): array
    {
        return $this->request('POST', 'Payments', [
            'json' => ['Payments' => [$paymentData]],
        ]);
    }

    // ==================== ACCOUNT METHODS ====================

    /**
     * Get all accounts
     */
    public function getAccounts(array $params = []): array
    {
        return $this->request('GET', 'Accounts', [
            'query' => $params,
        ]);
    }

    /**
     * Get account by ID
     */
    public function getAccount(string $accountId): array
    {
        return $this->request('GET', "Accounts/{$accountId}");
    }

    /**
     * Get revenue accounts
     */
    public function getRevenueAccounts(): array
    {
        return $this->request('GET', 'Accounts', [
            'query' => ['where' => 'Type=="REVENUE"'],
        ]);
    }

    /**
     * Get expense accounts
     */
    public function getExpenseAccounts(): array
    {
        return $this->request('GET', 'Accounts', [
            'query' => ['where' => 'Type=="EXPENSE"'],
        ]);
    }

    // ==================== TAX RATE METHODS ====================

    /**
     * Get all tax rates
     */
    public function getTaxRates(): array
    {
        return $this->request('GET', 'TaxRates');
    }

    // ==================== ORGANISATION METHODS ====================

    /**
     * Get organisation details
     */
    public function getOrganisation(): array
    {
        return $this->request('GET', 'Organisation');
    }

    // ==================== TRACKING CATEGORIES ====================

    /**
     * Get tracking categories
     */
    public function getTrackingCategories(): array
    {
        return $this->request('GET', 'TrackingCategories');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Build contact data from order customer
     */
    public function buildContactData(array $orderData): array
    {
        $contactData = [
            'Name' => $orderData['customer_name'] ?? 'Guest Customer',
            'EmailAddress' => $orderData['customer_email'] ?? null,
            'ContactStatus' => 'ACTIVE',
        ];

        // Add addresses
        if (!empty($orderData['billing_address']) || !empty($orderData['shipping_address'])) {
            $contactData['Addresses'] = [];

            // Billing address (POBOX type in Xero)
            if (!empty($orderData['billing_address'])) {
                $contactData['Addresses'][] = [
                    'AddressType' => 'POBOX',
                    'AddressLine1' => $orderData['billing_address']['address1'] ?? '',
                    'AddressLine2' => $orderData['billing_address']['address2'] ?? '',
                    'City' => $orderData['billing_address']['city'] ?? '',
                    'Region' => $orderData['billing_address']['province'] ?? '',
                    'PostalCode' => $orderData['billing_address']['zip'] ?? '',
                    'Country' => $orderData['billing_address']['country'] ?? 'US',
                ];
            }

            // Shipping address (STREET type in Xero)
            if (!empty($orderData['shipping_address'])) {
                $contactData['Addresses'][] = [
                    'AddressType' => 'STREET',
                    'AddressLine1' => $orderData['shipping_address']['address1'] ?? '',
                    'AddressLine2' => $orderData['shipping_address']['address2'] ?? '',
                    'City' => $orderData['shipping_address']['city'] ?? '',
                    'Region' => $orderData['shipping_address']['province'] ?? '',
                    'PostalCode' => $orderData['shipping_address']['zip'] ?? '',
                    'Country' => $orderData['shipping_address']['country'] ?? 'US',
                ];
            }
        }

        // Add phone
        if (!empty($orderData['shipping_address']['phone'])) {
            $contactData['Phones'] = [
                [
                    'PhoneType' => 'DEFAULT',
                    'PhoneNumber' => $orderData['shipping_address']['phone'],
                ],
            ];
        }

        return $contactData;
    }

    /**
     * Build invoice line items from order
     */
    public function buildLineItems(array $orderItems, ?string $accountCode = null): array
    {
        $lines = [];

        foreach ($orderItems as $item) {
            $line = [
                'Description' => $item['title'] ?? 'Product',
                'Quantity' => $item['quantity'] ?? 1,
                'UnitAmount' => $item['price'] ?? 0,
                'LineAmount' => $item['total'] ?? ($item['price'] * $item['quantity']),
            ];

            // Add item code if available
            if (!empty($item['sku'])) {
                $line['ItemCode'] = $item['sku'];
            }

            // Add account code if specified
            if ($accountCode) {
                $line['AccountCode'] = $accountCode;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Build item data from product
     */
    public function buildItemData(array $productData, ?string $salesAccountCode = null, ?string $purchaseAccountCode = null): array
    {
        $itemData = [
            'Code' => $productData['sku'] ?? substr($productData['title'], 0, 30),
            'Name' => substr($productData['title'] ?? 'Product', 0, 50),
            'Description' => $productData['description'] ?? '',
            'IsTrackedAsInventory' => true,
            'IsSold' => true,
            'IsPurchased' => true,
        ];

        // Add sales details
        if (isset($productData['price'])) {
            $itemData['SalesDetails'] = [
                'UnitPrice' => $productData['price'],
            ];

            if ($salesAccountCode) {
                $itemData['SalesDetails']['AccountCode'] = $salesAccountCode;
            }
        }

        // Add purchase details
        if (isset($productData['cost'])) {
            $itemData['PurchaseDetails'] = [
                'UnitPrice' => $productData['cost'],
            ];

            if ($purchaseAccountCode) {
                $itemData['PurchaseDetails']['AccountCode'] = $purchaseAccountCode;
            }
        }

        // Add inventory details
        if (isset($productData['quantity'])) {
            $itemData['QuantityOnHand'] = $productData['quantity'];
        }

        return $itemData;
    }

    /**
     * Search contacts by email
     */
    public function searchContactByEmail(string $email): ?array
    {
        try {
            $result = $this->request('GET', 'Contacts', [
                'query' => [
                    'where' => 'EmailAddress=="' . $email . '"',
                ],
            ]);

            $contacts = $result['Contacts'] ?? [];
            return !empty($contacts) ? $contacts[0] : null;
        } catch (\Exception $e) {
            Log::warning("Failed to search Xero contact by email: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Search items by code (SKU)
     */
    public function searchItemByCode(string $code): ?array
    {
        try {
            $result = $this->request('GET', 'Items', [
                'query' => [
                    'where' => 'Code=="' . $code . '"',
                ],
            ]);

            $items = $result['Items'] ?? [];
            return !empty($items) ? $items[0] : null;
        } catch (\Exception $e) {
            Log::warning("Failed to search Xero item by code: " . $e->getMessage());
            return null;
        }
    }
}
