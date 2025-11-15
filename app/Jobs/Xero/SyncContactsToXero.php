<?php

namespace App\Jobs\Xero;

use App\Models\Channel;
use App\Services\Xero\XeroClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncContactsToXero implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $contactData,
        public Channel $xeroChannel
    ) {}

    public function handle(): void
    {
        try {
            $client = new XeroClient($this->xeroChannel);

            // Check if contact already exists by email
            $email = $this->contactData['email'] ?? null;
            $existingContact = null;

            if ($email) {
                $existingContact = $client->searchContactByEmail($email);
            }

            // Prepare contact data
            $xeroContactData = [
                'Name' => $this->contactData['name'] ?? 'Guest Customer',
                'EmailAddress' => $email,
                'ContactStatus' => 'ACTIVE',
            ];

            // Add first and last name if available
            if (!empty($this->contactData['first_name'])) {
                $xeroContactData['FirstName'] = $this->contactData['first_name'];
            }
            if (!empty($this->contactData['last_name'])) {
                $xeroContactData['LastName'] = $this->contactData['last_name'];
            }

            // Add addresses
            if (!empty($this->contactData['billing_address']) || !empty($this->contactData['shipping_address'])) {
                $xeroContactData['Addresses'] = [];

                // Billing address (POBOX type)
                if (!empty($this->contactData['billing_address'])) {
                    $xeroContactData['Addresses'][] = [
                        'AddressType' => 'POBOX',
                        'AddressLine1' => $this->contactData['billing_address']['address1'] ?? '',
                        'AddressLine2' => $this->contactData['billing_address']['address2'] ?? '',
                        'City' => $this->contactData['billing_address']['city'] ?? '',
                        'Region' => $this->contactData['billing_address']['province'] ?? '',
                        'PostalCode' => $this->contactData['billing_address']['zip'] ?? '',
                        'Country' => $this->contactData['billing_address']['country'] ?? '',
                    ];
                }

                // Shipping address (STREET type)
                if (!empty($this->contactData['shipping_address'])) {
                    $xeroContactData['Addresses'][] = [
                        'AddressType' => 'STREET',
                        'AddressLine1' => $this->contactData['shipping_address']['address1'] ?? '',
                        'AddressLine2' => $this->contactData['shipping_address']['address2'] ?? '',
                        'City' => $this->contactData['shipping_address']['city'] ?? '',
                        'Region' => $this->contactData['shipping_address']['province'] ?? '',
                        'PostalCode' => $this->contactData['shipping_address']['zip'] ?? '',
                        'Country' => $this->contactData['shipping_address']['country'] ?? '',
                    ];
                }
            }

            // Add phones
            if (!empty($this->contactData['phone'])) {
                $xeroContactData['Phones'] = [
                    [
                        'PhoneType' => 'DEFAULT',
                        'PhoneNumber' => $this->contactData['phone'],
                    ],
                ];
            }

            if ($existingContact) {
                // Update existing contact
                $contactId = $existingContact['ContactID'];
                $response = $client->updateContact($contactId, $xeroContactData);
                Log::info("Updated Xero contact {$contactId}");
            } else {
                // Create new contact
                $response = $client->createContact($xeroContactData);
                $contact = $response['Contacts'][0] ?? [];
                $contactId = $contact['ContactID'] ?? null;
                Log::info("Created Xero contact {$contactId}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync contact to Xero: " . $e->getMessage());
            throw $e;
        }
    }
}
