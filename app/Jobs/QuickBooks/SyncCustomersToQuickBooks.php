<?php

namespace App\Jobs\QuickBooks;

use App\Models\Channel;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCustomersToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $customerData,
        public Channel $quickbooksChannel
    ) {}

    public function handle(): void
    {
        try {
            $client = new QuickBooksClient($this->quickbooksChannel);

            // Check if customer already exists by email
            $email = $this->customerData['email'] ?? null;
            $existingCustomerId = null;

            if ($email) {
                try {
                    $query = "SELECT * FROM Customer WHERE PrimaryEmailAddr = '{$email}' MAXRESULTS 1";
                    $result = $client->query($query);

                    if (!empty($result['QueryResponse']['Customer'])) {
                        $existingCustomerId = $result['QueryResponse']['Customer'][0]['Id'];
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to query QuickBooks customer: " . $e->getMessage());
                }
            }

            // Prepare customer data
            $qbCustomerData = [
                'DisplayName' => $this->customerData['name'] ?? 'Guest Customer',
                'GivenName' => $this->customerData['first_name'] ?? null,
                'FamilyName' => $this->customerData['last_name'] ?? null,
            ];

            if ($email) {
                $qbCustomerData['PrimaryEmailAddr'] = [
                    'Address' => $email,
                ];
            }

            if (!empty($this->customerData['phone'])) {
                $qbCustomerData['PrimaryPhone'] = [
                    'FreeFormNumber' => $this->customerData['phone'],
                ];
            }

            if (!empty($this->customerData['billing_address'])) {
                $qbCustomerData['BillAddr'] = [
                    'Line1' => $this->customerData['billing_address']['address1'] ?? null,
                    'Line2' => $this->customerData['billing_address']['address2'] ?? null,
                    'City' => $this->customerData['billing_address']['city'] ?? null,
                    'CountrySubDivisionCode' => $this->customerData['billing_address']['province'] ?? null,
                    'PostalCode' => $this->customerData['billing_address']['zip'] ?? null,
                    'Country' => $this->customerData['billing_address']['country'] ?? 'US',
                ];
            }

            if (!empty($this->customerData['shipping_address'])) {
                $qbCustomerData['ShipAddr'] = [
                    'Line1' => $this->customerData['shipping_address']['address1'] ?? null,
                    'Line2' => $this->customerData['shipping_address']['address2'] ?? null,
                    'City' => $this->customerData['shipping_address']['city'] ?? null,
                    'CountrySubDivisionCode' => $this->customerData['shipping_address']['province'] ?? null,
                    'PostalCode' => $this->customerData['shipping_address']['zip'] ?? null,
                    'Country' => $this->customerData['shipping_address']['country'] ?? 'US',
                ];
            }

            if ($existingCustomerId) {
                // Update existing customer
                $response = $client->updateCustomer($existingCustomerId, $qbCustomerData);
                Log::info("Updated QuickBooks customer {$existingCustomerId}");
            } else {
                // Create new customer
                $response = $client->createCustomer($qbCustomerData);
                $customerId = $response['Customer']['Id'];
                Log::info("Created QuickBooks customer {$customerId}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync customer to QuickBooks: " . $e->getMessage());
            throw $e;
        }
    }
}
