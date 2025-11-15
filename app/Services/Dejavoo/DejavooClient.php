<?php

namespace App\Services\Dejavoo;

use App\Models\Shop;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class DejavooClient
{
    protected Client $http;
    protected Shop $shop;
    protected string $registerId;
    protected string $authKey;
    protected string $baseUrl;

    /**
     * Create a new Dejavoo client instance
     *
     * @param Shop $shop
     */
    public function __construct(Shop $shop)
    {
        $this->shop = $shop;

        // Get Dejavoo credentials from shop settings
        $dejavooSettings = $shop->settings['dejavoo'] ?? [];
        $this->registerId = $dejavooSettings['register_id'] ?? '';
        $this->authKey = $dejavooSettings['auth_key'] ?? '';

        // Dejavoo API endpoint (can be cloud or local terminal)
        $this->baseUrl = $dejavooSettings['api_url'] ?? 'https://app3.dejavoo.com';

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 60, // Card transactions can take time
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Make an API request to Dejavoo
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        try {
            // Add authentication to request
            $data['RegisterId'] = $this->registerId;
            $data['AuthKey'] = $this->authKey;

            $options = [];
            if ($method === 'POST') {
                $options['json'] = $data;
            } else {
                $options['query'] = $data;
            }

            $response = $this->http->request($method, $endpoint, $options);
            $responseData = json_decode($response->getBody()->getContents(), true);

            return $responseData ?? [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorBody = $e->getResponse()->getBody()->getContents();

            Log::error("Dejavoo API error ({$statusCode}): {$errorBody}", [
                'endpoint' => $endpoint,
                'method' => $method,
            ]);

            throw new \Exception("Dejavoo API error: {$errorBody}", $statusCode);
        } catch (\Exception $e) {
            Log::error('Dejavoo request failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test the connection to Dejavoo terminal
     */
    public function testConnection(): array
    {
        try {
            $response = $this->request('POST', '/status', [
                'Command' => 'status',
            ]);

            return [
                'success' => true,
                'status' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process a sale transaction
     *
     * @param float $amount Transaction amount
     * @param string $referenceNumber Optional reference number
     * @param array $options Additional options
     * @return array
     */
    public function sale(float $amount, string $referenceNumber = null, array $options = []): array
    {
        $data = [
            'Command' => 'sale',
            'Amount' => number_format($amount, 2, '.', ''),
            'TenderType' => $options['tender_type'] ?? 'Credit',
        ];

        if ($referenceNumber) {
            $data['RefId'] = $referenceNumber;
        }

        // Optional invoice number
        if (isset($options['invoice_number'])) {
            $data['InvoiceNumber'] = $options['invoice_number'];
        }

        // Optional tip amount
        if (isset($options['tip_amount'])) {
            $data['TipAmount'] = number_format($options['tip_amount'], 2, '.', '');
        }

        // Optional cashback amount
        if (isset($options['cashback_amount'])) {
            $data['CashbackAmount'] = number_format($options['cashback_amount'], 2, '.', '');
        }

        return $this->request('POST', '/sale', $data);
    }

    /**
     * Void a transaction
     *
     * @param string $transactionId Transaction ID to void
     * @return array
     */
    public function void(string $transactionId): array
    {
        $data = [
            'Command' => 'void',
            'RefId' => $transactionId,
        ];

        return $this->request('POST', '/void', $data);
    }

    /**
     * Refund a transaction
     *
     * @param float $amount Amount to refund
     * @param string $transactionId Optional original transaction ID
     * @return array
     */
    public function refund(float $amount, string $transactionId = null): array
    {
        $data = [
            'Command' => 'refund',
            'Amount' => number_format($amount, 2, '.', ''),
        ];

        if ($transactionId) {
            $data['RefId'] = $transactionId;
        }

        return $this->request('POST', '/refund', $data);
    }

    /**
     * Process an auth-only transaction (authorization without capture)
     *
     * @param float $amount Authorization amount
     * @param string $referenceNumber Optional reference number
     * @return array
     */
    public function auth(float $amount, string $referenceNumber = null): array
    {
        $data = [
            'Command' => 'auth',
            'Amount' => number_format($amount, 2, '.', ''),
        ];

        if ($referenceNumber) {
            $data['RefId'] = $referenceNumber;
        }

        return $this->request('POST', '/auth', $data);
    }

    /**
     * Capture a previously authorized transaction
     *
     * @param string $authCode Authorization code from auth transaction
     * @param float $amount Amount to capture (can be less than authorized)
     * @return array
     */
    public function capture(string $authCode, float $amount): array
    {
        $data = [
            'Command' => 'capture',
            'AuthCode' => $authCode,
            'Amount' => number_format($amount, 2, '.', ''),
        ];

        return $this->request('POST', '/capture', $data);
    }

    /**
     * Get transaction status
     *
     * @param string $referenceNumber Reference number or transaction ID
     * @return array
     */
    public function getStatus(string $referenceNumber): array
    {
        $data = [
            'Command' => 'status',
            'RefId' => $referenceNumber,
        ];

        return $this->request('POST', '/status', $data);
    }

    /**
     * Process a tip adjustment on an existing transaction
     *
     * @param string $transactionId Transaction ID
     * @param float $tipAmount Tip amount to add
     * @return array
     */
    public function tipAdjustment(string $transactionId, float $tipAmount): array
    {
        $data = [
            'Command' => 'tipadjust',
            'RefId' => $transactionId,
            'TipAmount' => number_format($tipAmount, 2, '.', ''),
        ];

        return $this->request('POST', '/tipadjust', $data);
    }

    /**
     * Batch close (settle transactions)
     *
     * @return array
     */
    public function batchClose(): array
    {
        $data = [
            'Command' => 'batchclose',
        ];

        return $this->request('POST', '/batchclose', $data);
    }

    /**
     * Cancel current transaction in progress
     *
     * @return array
     */
    public function cancel(): array
    {
        $data = [
            'Command' => 'cancel',
        ];

        return $this->request('POST', '/cancel', $data);
    }

    /**
     * Parse Dejavoo response
     *
     * @param array $response
     * @return array
     */
    public function parseResponse(array $response): array
    {
        $approved = false;
        $message = '';

        // Check response code
        if (isset($response['RespCode'])) {
            $approved = in_array($response['RespCode'], ['00', '000', '0']);
            $message = $response['RespMSG'] ?? $response['Message'] ?? 'Unknown response';
        }

        return [
            'approved' => $approved,
            'response_code' => $response['RespCode'] ?? null,
            'message' => $message,
            'transaction_id' => $response['TxnId'] ?? $response['RefId'] ?? null,
            'auth_code' => $response['AuthCode'] ?? null,
            'card_type' => $response['CardType'] ?? null,
            'last_four' => $response['Last4'] ?? null,
            'amount' => isset($response['Amount']) ? (float) $response['Amount'] : null,
            'tip_amount' => isset($response['TipAmount']) ? (float) $response['TipAmount'] : null,
            'total_amount' => isset($response['TotalAmount']) ? (float) $response['TotalAmount'] : null,
            'entry_mode' => $response['EntryMode'] ?? null,
            'timestamp' => $response['Timestamp'] ?? $response['TxnTimestamp'] ?? null,
            'raw_response' => $response,
        ];
    }

    /**
     * Check if response indicates approval
     *
     * @param array $response
     * @return bool
     */
    public function isApproved(array $response): bool
    {
        $respCode = $response['RespCode'] ?? null;
        return in_array($respCode, ['00', '000', '0']);
    }

    /**
     * Build payment data for POS transaction
     *
     * @param array $dejavooResponse
     * @return array
     */
    public function buildPaymentData(array $dejavooResponse): array
    {
        $parsed = $this->parseResponse($dejavooResponse);

        return [
            'payment_processor' => 'dejavoo',
            'transaction_id' => $parsed['transaction_id'],
            'auth_code' => $parsed['auth_code'],
            'card_type' => $parsed['card_type'],
            'last_four' => $parsed['last_four'],
            'amount' => $parsed['amount'],
            'tip_amount' => $parsed['tip_amount'],
            'total_amount' => $parsed['total_amount'],
            'entry_mode' => $parsed['entry_mode'],
            'approved' => $parsed['approved'],
            'response_code' => $parsed['response_code'],
            'message' => $parsed['message'],
            'timestamp' => $parsed['timestamp'],
            'raw_response' => $parsed['raw_response'],
        ];
    }
}
