<?php

namespace App\Jobs\QuickBooks;

use App\Models\ChannelOrder;
use App\Models\Channel;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOrdersToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ChannelOrder $order,
        public Channel $quickbooksChannel,
        public string $documentType = 'salesreceipt' // or 'invoice'
    ) {}

    public function handle(): void
    {
        try {
            $client = new QuickBooksClient($this->quickbooksChannel);

            // Get or create customer in QuickBooks
            $customerId = $this->getOrCreateCustomer($client);

            // Get income account ID from channel config
            $incomeAccountId = $this->quickbooksChannel->policy_json['income_account_id'] ?? null;

            // Prepare line items
            $lineItems = $this->prepareLineItems($client, $incomeAccountId);

            if ($this->documentType === 'invoice') {
                // Create invoice (for credit sales)
                $response = $this->createInvoice($client, $customerId, $lineItems);
                $documentId = $response['Invoice']['Id'] ?? null;
            } else {
                // Create sales receipt (for cash sales)
                $response = $this->createSalesReceipt($client, $customerId, $lineItems);
                $documentId = $response['SalesReceipt']['Id'] ?? null;
            }

            // Store QuickBooks reference in order
            $this->order->update([
                'sync_metadata' => array_merge($this->order->sync_metadata ?? [], [
                    'quickbooks_synced' => true,
                    'quickbooks_document_type' => $this->documentType,
                    'quickbooks_document_id' => $documentId,
                    'quickbooks_customer_id' => $customerId,
                    'synced_at' => now()->toIso8601String(),
                ]),
            ]);

            Log::info("Order {$this->order->id} synced to QuickBooks as {$this->documentType} {$documentId}");
        } catch (\Exception $e) {
            Log::error("Failed to sync order {$this->order->id} to QuickBooks: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get or create customer in QuickBooks
     */
    protected function getOrCreateCustomer(QuickBooksClient $client): string
    {
        // Check if we already have a QuickBooks customer ID
        $metadata = $this->order->sync_metadata ?? [];
        if (!empty($metadata['quickbooks_customer_id'])) {
            return $metadata['quickbooks_customer_id'];
        }

        // Try to find existing customer by email
        $email = $this->order->customer_email;
        if ($email) {
            try {
                $query = "SELECT * FROM Customer WHERE PrimaryEmailAddr = '{$email}' MAXRESULTS 1";
                $result = $client->query($query);

                if (!empty($result['QueryResponse']['Customer'])) {
                    return $result['QueryResponse']['Customer'][0]['Id'];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to query QuickBooks customer: " . $e->getMessage());
            }
        }

        // Create new customer
        $customerData = $client->buildCustomerData([
            'customer_name' => $this->order->customer_name,
            'customer_email' => $this->order->customer_email,
            'shipping_address' => $this->order->shipping_address ?? [],
            'billing_address' => $this->order->billing_address ?? $this->order->shipping_address ?? [],
        ]);

        $response = $client->createCustomer($customerData);

        return $response['Customer']['Id'];
    }

    /**
     * Prepare line items for QuickBooks
     */
    protected function prepareLineItems(QuickBooksClient $client, ?string $incomeAccountId): array
    {
        $items = $this->order->items->map(function ($item) use ($incomeAccountId) {
            return [
                'title' => $item->title,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total,
                'quickbooks_item_id' => $item->sync_metadata['quickbooks_item_id'] ?? null,
            ];
        })->toArray();

        $lines = $client->buildLineItems($items, $incomeAccountId);

        // Add shipping as a line item if present
        if (!empty($this->order->shipping_price) && $this->order->shipping_price > 0) {
            $lines[] = [
                'Amount' => $this->order->shipping_price,
                'Description' => 'Shipping',
                'DetailType' => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'Qty' => 1,
                    'UnitPrice' => $this->order->shipping_price,
                ],
            ];

            if ($incomeAccountId) {
                $lines[count($lines) - 1]['SalesItemLineDetail']['IncomeAccountRef'] = [
                    'value' => $incomeAccountId,
                ];
            }
        }

        // Add tax as a line item if present
        if (!empty($this->order->tax_price) && $this->order->tax_price > 0) {
            $lines[] = [
                'Amount' => $this->order->tax_price,
                'Description' => 'Sales Tax',
                'DetailType' => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'Qty' => 1,
                    'UnitPrice' => $this->order->tax_price,
                ],
            ];
        }

        return $lines;
    }

    /**
     * Create invoice in QuickBooks
     */
    protected function createInvoice(QuickBooksClient $client, string $customerId, array $lineItems): array
    {
        $invoiceData = [
            'CustomerRef' => [
                'value' => $customerId,
            ],
            'Line' => $lineItems,
            'TxnDate' => $this->order->placed_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'DocNumber' => $this->order->order_number,
            'PrivateNote' => "Order from {$this->order->channel->name} - {$this->order->order_number}",
        ];

        // Add due date if needed
        if ($this->order->financial_status !== 'paid') {
            $invoiceData['DueDate'] = now()->addDays(30)->format('Y-m-d');
        }

        // Add billing address
        if (!empty($this->order->billing_address)) {
            $invoiceData['BillAddr'] = [
                'Line1' => $this->order->billing_address['address1'] ?? null,
                'Line2' => $this->order->billing_address['address2'] ?? null,
                'City' => $this->order->billing_address['city'] ?? null,
                'CountrySubDivisionCode' => $this->order->billing_address['province'] ?? null,
                'PostalCode' => $this->order->billing_address['zip'] ?? null,
                'Country' => $this->order->billing_address['country'] ?? 'US',
            ];
        }

        // Add shipping address
        if (!empty($this->order->shipping_address)) {
            $invoiceData['ShipAddr'] = [
                'Line1' => $this->order->shipping_address['address1'] ?? null,
                'Line2' => $this->order->shipping_address['address2'] ?? null,
                'City' => $this->order->shipping_address['city'] ?? null,
                'CountrySubDivisionCode' => $this->order->shipping_address['province'] ?? null,
                'PostalCode' => $this->order->shipping_address['zip'] ?? null,
                'Country' => $this->order->shipping_address['country'] ?? 'US',
            ];
        }

        return $client->createInvoice($invoiceData);
    }

    /**
     * Create sales receipt in QuickBooks
     */
    protected function createSalesReceipt(QuickBooksClient $client, string $customerId, array $lineItems): array
    {
        $receiptData = [
            'CustomerRef' => [
                'value' => $customerId,
            ],
            'Line' => $lineItems,
            'TxnDate' => $this->order->placed_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'DocNumber' => $this->order->order_number,
            'PrivateNote' => "Order from {$this->order->channel->name} - {$this->order->order_number}",
        ];

        // Add payment method if configured
        $paymentMethodId = $this->quickbooksChannel->policy_json['payment_method_id'] ?? null;
        if ($paymentMethodId) {
            $receiptData['PaymentMethodRef'] = [
                'value' => $paymentMethodId,
            ];
        }

        // Add deposit to account if configured
        $depositAccountId = $this->quickbooksChannel->policy_json['deposit_account_id'] ?? null;
        if ($depositAccountId) {
            $receiptData['DepositToAccountRef'] = [
                'value' => $depositAccountId,
            ];
        }

        // Add billing address
        if (!empty($this->order->billing_address)) {
            $receiptData['BillAddr'] = [
                'Line1' => $this->order->billing_address['address1'] ?? null,
                'Line2' => $this->order->billing_address['address2'] ?? null,
                'City' => $this->order->billing_address['city'] ?? null,
                'CountrySubDivisionCode' => $this->order->billing_address['province'] ?? null,
                'PostalCode' => $this->order->billing_address['zip'] ?? null,
                'Country' => $this->order->billing_address['country'] ?? 'US',
            ];
        }

        // Add shipping address
        if (!empty($this->order->shipping_address)) {
            $receiptData['ShipAddr'] = [
                'Line1' => $this->order->shipping_address['address1'] ?? null,
                'Line2' => $this->order->shipping_address['address2'] ?? null,
                'City' => $this->order->shipping_address['city'] ?? null,
                'CountrySubDivisionCode' => $this->order->shipping_address['province'] ?? null,
                'PostalCode' => $this->order->shipping_address['zip'] ?? null,
                'Country' => $this->order->shipping_address['country'] ?? 'US',
            ];
        }

        return $client->createSalesReceipt($receiptData);
    }
}
