<?php

namespace App\Jobs\Xero;

use App\Models\ChannelOrder;
use App\Models\Channel;
use App\Services\Xero\XeroClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOrdersToXero implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ChannelOrder $order,
        public Channel $xeroChannel,
        public string $invoiceType = 'ACCREC' // ACCREC (sales) or ACCPAY (purchase)
    ) {}

    public function handle(): void
    {
        try {
            $client = new XeroClient($this->xeroChannel);

            // Get or create contact in Xero
            $contactId = $this->getOrCreateContact($client);

            // Get revenue account code from channel config
            $accountCode = $this->xeroChannel->policy_json['revenue_account_code'] ?? null;

            // Prepare line items
            $lineItems = $this->prepareLineItems($client, $accountCode);

            // Create invoice
            $response = $this->createInvoice($client, $contactId, $lineItems);
            $invoice = $response['Invoices'][0] ?? [];
            $invoiceId = $invoice['InvoiceID'] ?? null;

            // If order is paid, create payment
            if ($this->order->financial_status === 'paid' && $invoiceId) {
                $this->createPayment($client, $invoiceId, $this->order->total_price);
            }

            // Store Xero reference in order
            $this->order->update([
                'sync_metadata' => array_merge($this->order->sync_metadata ?? [], [
                    'xero_synced' => true,
                    'xero_invoice_id' => $invoiceId,
                    'xero_invoice_number' => $invoice['InvoiceNumber'] ?? null,
                    'xero_contact_id' => $contactId,
                    'synced_at' => now()->toIso8601String(),
                ]),
            ]);

            Log::info("Order {$this->order->id} synced to Xero as invoice {$invoiceId}");
        } catch (\Exception $e) {
            Log::error("Failed to sync order {$this->order->id} to Xero: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get or create contact in Xero
     */
    protected function getOrCreateContact(XeroClient $client): string
    {
        // Check if we already have a Xero contact ID
        $metadata = $this->order->sync_metadata ?? [];
        if (!empty($metadata['xero_contact_id'])) {
            return $metadata['xero_contact_id'];
        }

        // Try to find existing contact by email
        $email = $this->order->customer_email;
        if ($email) {
            $existingContact = $client->searchContactByEmail($email);
            if ($existingContact) {
                return $existingContact['ContactID'];
            }
        }

        // Create new contact
        $contactData = $client->buildContactData([
            'customer_name' => $this->order->customer_name,
            'customer_email' => $this->order->customer_email,
            'shipping_address' => $this->order->shipping_address ?? [],
            'billing_address' => $this->order->billing_address ?? $this->order->shipping_address ?? [],
        ]);

        $response = $client->createContact($contactData);
        $contact = $response['Contacts'][0] ?? [];

        return $contact['ContactID'];
    }

    /**
     * Prepare line items for Xero
     */
    protected function prepareLineItems(XeroClient $client, ?string $accountCode): array
    {
        $items = $this->order->items->map(function ($item) {
            return [
                'title' => $item->title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total,
            ];
        })->toArray();

        $lines = $client->buildLineItems($items, $accountCode);

        // Add shipping as a line item if present
        if (!empty($this->order->shipping_price) && $this->order->shipping_price > 0) {
            $shippingLine = [
                'Description' => 'Shipping',
                'Quantity' => 1,
                'UnitAmount' => $this->order->shipping_price,
                'LineAmount' => $this->order->shipping_price,
            ];

            if ($accountCode) {
                $shippingLine['AccountCode'] = $accountCode;
            }

            $lines[] = $shippingLine;
        }

        return $lines;
    }

    /**
     * Create invoice in Xero
     */
    protected function createInvoice(XeroClient $client, string $contactId, array $lineItems): array
    {
        $invoiceData = [
            'Type' => $this->invoiceType,
            'Contact' => [
                'ContactID' => $contactId,
            ],
            'LineItems' => $lineItems,
            'Date' => $this->order->placed_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'DueDate' => $this->order->financial_status === 'paid'
                ? $this->order->placed_at?->format('Y-m-d') ?? now()->format('Y-m-d')
                : now()->addDays(30)->format('Y-m-d'),
            'Reference' => $this->order->order_number,
            'Status' => $this->order->financial_status === 'paid' ? 'PAID' : 'AUTHORISED',
        ];

        // Add invoice number if available
        if ($this->order->order_number) {
            $invoiceData['InvoiceNumber'] = $this->order->order_number;
        }

        // Add currency if not default
        if ($this->order->currency && $this->order->currency !== 'USD') {
            $invoiceData['CurrencyCode'] = $this->order->currency;
        }

        return $client->createInvoice($invoiceData);
    }

    /**
     * Create payment for paid invoice
     */
    protected function createPayment(XeroClient $client, string $invoiceId, float $amount): void
    {
        try {
            // Get bank account from channel config
            $bankAccountCode = $this->xeroChannel->policy_json['bank_account_code'] ?? null;

            if (!$bankAccountCode) {
                Log::warning('No bank account configured for Xero payments');
                return;
            }

            $paymentData = [
                'Invoice' => [
                    'InvoiceID' => $invoiceId,
                ],
                'Account' => [
                    'Code' => $bankAccountCode,
                ],
                'Date' => $this->order->placed_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'Amount' => $amount,
            ];

            $client->createPayment($paymentData);

            Log::info("Created Xero payment for invoice {$invoiceId}");
        } catch (\Exception $e) {
            Log::warning("Failed to create Xero payment: " . $e->getMessage());
        }
    }
}
