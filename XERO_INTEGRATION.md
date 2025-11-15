# Xero Accounting Integration Guide

## Overview

This guide covers the complete integration with Xero, allowing you to automatically sync orders, customers, products, and financial data for seamless accounting and bookkeeping.

## Features

- ✅ OAuth 2.0 authentication with automatic token refresh
- ✅ Order syncing as sales invoices
- ✅ Contact (customer) management and synchronization
- ✅ Item (product/inventory) syncing
- ✅ Payment tracking and recording
- ✅ Multi-organization support
- ✅ Configurable chart of accounts mapping
- ✅ Tax rate integration
- ✅ Tracking categories support
- ✅ Real-time sync or scheduled batch processing

## Prerequisites

### 1. Xero Account

You must have an active Xero subscription:

1. Go to [Xero](https://www.xero.com/)
2. Sign up or sign in
3. Choose a subscription plan (Starter, Standard, or Premium)
4. Set up your organization
5. Ensure you have administrator or adviser access

### 2. Xero Developer Account and App

1. Go to [Xero Developer Portal](https://developer.xero.com/)
2. Sign in with your Xero account
3. Click **"New app"**
4. Select app type: **Web app**
5. Fill in the app details:
   - **App name**: Your app name (e.g., "Multichannel Sales Manager")
   - **Company or application URL**: Your website
   - **Redirect URI**: `https://yourdomain.com/api/xero/callback`
6. Get your credentials:
   - **Client ID**
   - **Client Secret**

### 3. Environment Setup

Add Xero credentials to `.env`:

```env
# Xero Configuration
XERO_CLIENT_ID=your_client_id_here
XERO_CLIENT_SECRET=your_client_secret_here
XERO_REDIRECT_URI=https://yourdomain.com/api/xero/callback
XERO_SCOPES="accounting.transactions accounting.contacts accounting.settings.read offline_access"
```

## Setup & Connection

### Via Frontend

1. Navigate to **Channels** page
2. Click **Connect Xero**
3. Click **"Connect to Xero"** button
4. You'll be redirected to Xero
5. Log in to your Xero account (if not already)
6. Select the organization you want to connect
7. Authorize the requested permissions
8. You'll be redirected back and your organization will be connected

The system will:
- Complete OAuth flow and get access/refresh tokens
- Fetch organization information
- Cache chart of accounts and tax rates
- Enable automatic syncing

### Via API

```bash
# Initiate OAuth flow
GET /api/xero/authorize
# User will be redirected to Xero for authorization
# Xero will redirect back to: /api/xero/callback?code=xxx&state=xxx
```

## Initial Configuration

After connecting, configure your Xero channel settings:

### 1. Chart of Accounts Mapping

Map your sales channels to Xero accounts:

```php
// Set revenue account for sales
$channel->update([
    'policy_json' => [
        'sales_account_code' => '200', // Revenue account code
        'purchase_account_code' => '310', // COGS account code (optional)
        'bank_account_code' => '090', // Bank account code (for payments)
    ],
]);
```

### 2. Get Available Accounts

```bash
GET /api/xero/channels/{channel}/accounts
```

This returns revenue and expense accounts from your Xero organization.

## Order Syncing

### How It Works

Orders are synced to Xero as **Sales Invoices** (ACCREC type):

- Invoice created for each order
- Contact (customer) created if doesn't exist
- Line items added for products
- Shipping and taxes included
- Payment recorded for paid orders

### Sync an Order

```php
use App\Jobs\Xero\SyncOrdersToXero;

// Sync order to Xero
SyncOrdersToXero::dispatch($order, $xeroChannel);

// Sync as ACCREC (sales invoice)
SyncOrdersToXero::dispatch($order, $xeroChannel, 'ACCREC');
```

### What Gets Synced

From each order:
- **Contact** (created if doesn't exist)
- **Line Items** (products/services)
- **Shipping Charges**
- **Taxes**
- **Payment** (for paid orders)
- **Order Number** as invoice reference
- **Order Date** as invoice date

### Invoice Example

```json
{
  "Type": "ACCREC",
  "Contact": {
    "ContactID": "xxx-xxx-xxx"
  },
  "LineItems": [
    {
      "Description": "Product Title",
      "Quantity": 1,
      "UnitAmount": 29.99,
      "LineAmount": 29.99,
      "ItemCode": "SKU-123",
      "AccountCode": "200"
    }
  ],
  "Date": "2024-01-15",
  "DueDate": "2024-02-14",
  "Reference": "ORD-12345",
  "Status": "AUTHORISED"
}
```

## Contact Management

### Sync Contacts

```php
use App\Jobs\Xero\SyncContactsToXero;

$contactData = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '555-1234',
    'billing_address' => [...],
    'shipping_address' => [...],
];

SyncContactsToXero::dispatch($contactData, $xeroChannel);
```

### Automatic Contact Matching

The system automatically:
1. Searches for existing contacts by email
2. Updates existing contact if found
3. Creates new contact if not found

### Contact Data Structure

```php
$contactData = [
    'Name' => 'John Doe',
    'EmailAddress' => 'john@example.com',
    'ContactStatus' => 'ACTIVE',
    'Addresses' => [
        [
            'AddressType' => 'POBOX', // Billing
            'AddressLine1' => '123 Main St',
            'City' => 'Springfield',
            'Region' => 'IL',
            'PostalCode' => '62701',
            'Country' => 'US',
        ],
        [
            'AddressType' => 'STREET', // Shipping
            'AddressLine1' => '456 Oak Ave',
            'City' => 'Springfield',
            'Region' => 'IL',
            'PostalCode' => '62702',
            'Country' => 'US',
        ],
    ],
    'Phones' => [
        [
            'PhoneType' => 'DEFAULT',
            'PhoneNumber' => '555-1234',
        ],
    ],
];
```

## Item Management

### Sync Items

```php
use App\Jobs\Xero\SyncItemsToXero;

// Sync product and all its variants
SyncItemsToXero::dispatch($product, $xeroChannel);
```

### Required Account Configuration

Before syncing items, configure these accounts:

1. **Sales Account** - Where sales revenue is recorded
2. **Purchase Account** - Cost of Goods Sold (optional)

### Item Type

Products are synced as **tracked inventory items**:

```php
$itemData = [
    'Code' => 'SKU-12345',
    'Name' => 'Product Name - Variant',
    'Description' => 'Product description',
    'IsTrackedAsInventory' => true,
    'IsSold' => true,
    'IsPurchased' => true,
    'SalesDetails' => [
        'UnitPrice' => 29.99,
        'AccountCode' => '200',
    ],
    'PurchaseDetails' => [
        'UnitPrice' => 15.00,
        'AccountCode' => '310',
    ],
    'QuantityOnHand' => 100,
];
```

### Automatic Item Matching

The system:
1. Searches for existing items by Code (SKU)
2. Updates existing item if found
3. Creates new item if not found
4. Stores Xero Item ID in variant metadata

## API Client Usage

### Initialize Client

```php
use App\Services\Xero\XeroClient;

$client = new XeroClient($xeroChannel);
```

### Available Methods

#### Contacts (Customers)

```php
// Get all contacts
$contacts = $client->getContacts();

// Get specific contact
$contact = $client->getContact('xxx-xxx-xxx');

// Create contact
$result = $client->createContact($contactData);

// Update contact
$result = $client->updateContact('xxx-xxx-xxx', $contactData);

// Search by email
$contact = $client->searchContactByEmail('john@example.com');
```

#### Items (Products)

```php
// Get all items
$items = $client->getItems();

// Get specific item
$item = $client->getItem('xxx-xxx-xxx');

// Create item
$result = $client->createItem($itemData);

// Update item
$result = $client->updateItem('xxx-xxx-xxx', $itemData);

// Search by code (SKU)
$item = $client->searchItemByCode('SKU-123');
```

#### Invoices

```php
// Get all invoices
$invoices = $client->getInvoices();

// Get specific invoice
$invoice = $client->getInvoice('xxx-xxx-xxx');

// Create invoice
$result = $client->createInvoice($invoiceData);

// Update invoice
$result = $client->updateInvoice('xxx-xxx-xxx', $invoiceData);

// Delete invoice
$result = $client->deleteInvoice('xxx-xxx-xxx');
```

#### Payments

```php
// Get all payments
$payments = $client->getPayments();

// Get specific payment
$payment = $client->getPayment('xxx-xxx-xxx');

// Create payment
$result = $client->createPayment($paymentData);
```

#### Accounts

```php
// Get all accounts
$accounts = $client->getAccounts();

// Get specific account
$account = $client->getAccount('xxx-xxx-xxx');

// Get revenue accounts
$revenueAccounts = $client->getRevenueAccounts();

// Get expense accounts
$expenseAccounts = $client->getExpenseAccounts();
```

#### Tax Rates

```php
// Get all tax rates
$taxRates = $client->getTaxRates();
```

#### Organization

```php
// Get organization details
$org = $client->getOrganisation();

// Get tracking categories
$categories = $client->getTrackingCategories();
```

## Authentication

Xero uses OAuth 2.0 authorization code flow:

1. **Redirect to Xero**: User authorizes app
2. **Authorization Code**: Xero redirects back with code
3. **Exchange for Token**: Exchange code for access/refresh tokens
4. **Get Connections**: Fetch connected organizations (tenants)
5. **Select Organization**: User or system selects organization
6. **Access Token**: Valid for 1800 seconds (30 minutes)
7. **Refresh Token**: Valid for 60 days
8. **Auto-Refresh**: Tokens automatically refreshed when expired

### Token Storage

Tokens are stored in `channels.auth_json`:

```json
{
  "access_token": "xxx",
  "refresh_token": "xxx",
  "expires_at": 1234567890,
  "tenant_id": "xxx-xxx-xxx",
  "tenant_name": "My Organization",
  "tenant_type": "ORGANISATION",
  "organisation_name": "My Company Ltd",
  "country_code": "US"
}
```

### Token Refresh

Automatic token refresh happens when:
- Access token is expired (before API call)
- API returns 401 Unauthorized

## Best Practices

### 1. Account Mapping

- **Sales Account**: Map to your main sales revenue account (typically 200-299)
- **COGS Account**: Map to Cost of Goods Sold (typically 300-399)
- **Bank Account**: Use checking/savings for payments
- Keep mappings consistent across channels

### 2. Invoice Management

- **References**: Use order numbers as invoice references
- **Dates**: Set due dates based on payment terms
- **Status**: AUTHORISED for unpaid, PAID for paid orders
- Always include customer contact

### 3. Contact Management

- Always search for existing contacts by email first
- Use consistent naming conventions
- Keep contact data synchronized
- Update addresses when changed

### 4. Inventory Tracking

- Sync products before syncing orders
- Keep Xero item IDs in variant metadata
- Update quantities regularly
- Monitor stock levels

### 5. Data Sync Frequency

- **Orders**: Sync immediately or hourly
- **Contacts**: Sync when created/updated
- **Items**: Sync daily or when changed
- **Inventory**: Sync every 4-6 hours

## Troubleshooting

### Connection Issues

**Error: "Failed to connect Xero"**

- Verify Client ID and Client Secret are correct
- Check redirect URI matches exactly (including https)
- Ensure Xero subscription is active
- Check app is not in demo company

**Error: "Token expired"**

- Refresh tokens expire after 60 days
- System auto-refreshes but may fail if not used
- Disconnect and reconnect to get new tokens

### Sync Errors

**Error: "Account code not configured"**

- Set up sales and purchase account codes in channel policy
- Get account codes via `/api/xero/channels/{channel}/accounts`

**Error: "Contact not found"**

- Contact was archived or deleted in Xero
- Clear Xero contact ID from order metadata
- Re-sync will create new contact

**Error: "Validation error"**

- Check required fields are provided
- Ensure account codes exist in Xero
- Verify data format matches Xero requirements

**Error: "Item code already exists"**

- SKU/Code must be unique in Xero
- Update existing item instead
- Use different code

## Advanced Features

### Multi-Organization Support

Connect multiple Xero organizations:

```php
// Each organization is a separate channel
$channel1 = Channel::where('type', 'xero')
    ->where('external_id', 'tenant_1_id')
    ->first();

$channel2 = Channel::where('type', 'xero')
    ->where('external_id', 'tenant_2_id')
    ->first();
```

### Custom Account Mapping Per Channel

```php
$channel->update([
    'policy_json' => [
        'sales_account_code' => '200',
        'purchase_account_code' => '310',
        'bank_account_code' => '090',
        // Custom mappings
        'default_tax_type' => 'OUTPUT2', // Tax rate
    ],
]);
```

### Batch Processing

Process multiple orders at once:

```php
use App\Jobs\Xero\SyncOrdersToXero;

$orders = ChannelOrder::where('financial_status', 'paid')
    ->whereNull('sync_metadata->xero_synced')
    ->get();

foreach ($orders as $order) {
    SyncOrdersToXero::dispatch($order, $xeroChannel);
}
```

### Tracking Categories

Use Xero tracking categories for advanced reporting:

```php
$client = new XeroClient($channel);
$categories = $client->getTrackingCategories();

// Add to invoice line
$lineItem = [
    'Description' => 'Product',
    'Quantity' => 1,
    'UnitAmount' => 29.99,
    'Tracking' => [
        [
            'TrackingCategoryID' => 'xxx',
            'TrackingOptionID' => 'yyy',
        ],
    ],
];
```

## Scheduled Jobs

Set up cron jobs for automation:

```php
// In routes/console.php or App\Console\Kernel

// Sync paid orders every hour
$schedule->call(function () {
    $xeroChannels = Channel::where('type', 'xero')
        ->where('status', 'connected')
        ->get();

    foreach ($xeroChannels as $channel) {
        $orders = ChannelOrder::where('financial_status', 'paid')
            ->whereNull('sync_metadata->xero_synced')
            ->get();

        foreach ($orders as $order) {
            SyncOrdersToXero::dispatch($order, $channel);
        }
    }
})->hourly();

// Sync products daily
$schedule->call(function () {
    $xeroChannels = Channel::where('type', 'xero')
        ->where('status', 'connected')
        ->get();

    foreach ($xeroChannels as $channel) {
        Product::chunk(50, function ($products) use ($channel) {
            foreach ($products as $product) {
                SyncItemsToXero::dispatch($product, $channel);
            }
        });
    }
})->daily();
```

## Security

1. **OAuth Tokens**: Stored encrypted in database
2. **HTTPS Only**: All API calls use HTTPS
3. **Token Refresh**: Automatic refresh prevents exposure
4. **State Parameter**: CSRF protection in OAuth flow
5. **Logging**: Sensitive data excluded from logs
6. **Permissions**: Limit who can connect/disconnect
7. **Connection Revocation**: Connections can be revoked

## Rate Limits

Xero has rate limits:

- **60 API calls per minute** per organization
- **5000 API calls per day** per organization
- **Burst limit**: Up to 60 in quick succession

The client includes automatic retry logic with exponential backoff.

## Support Resources

- [Xero Developer Portal](https://developer.xero.com/)
- [Xero API Documentation](https://developer.xero.com/documentation/)
- [Xero Central](https://central.xero.com/)
- [Xero Community](https://community.xero.com/)

## Compliance

### Requirements

- Valid Xero subscription
- Administrator or adviser access
- Accurate financial data
- Proper account classification
- Tax compliance

### Data Accuracy

- Verify all sync data before finalizing
- Review Xero reports regularly
- Reconcile accounts monthly
- Maintain audit trail

## Common Use Cases

### 1. E-commerce to Accounting

Automatically sync all paid orders:

```php
// When order is marked as paid
if ($order->financial_status === 'paid') {
    SyncOrdersToXero::dispatch($order, $xeroChannel);
}
```

### 2. Inventory Management

Keep Xero inventory in sync:

```php
// When inventory changes
SyncItemsToXero::dispatch($product, $xeroChannel);
```

### 3. Customer Database Sync

Maintain unified customer records:

```php
// When customer is created or updated
SyncContactsToXero::dispatch($contactData, $xeroChannel);
```

### 4. Financial Reporting

Pull data for custom reports:

```php
$client = new XeroClient($xeroChannel);
$invoices = $client->getInvoices([
    'where' => 'Date >= DateTime(2024,1,1)',
]);
```

## Xero vs QuickBooks

### Similarities

- Both use OAuth 2.0
- Both support invoices, contacts, items
- Both have payment tracking
- Both support multi-company/organization

### Differences

| Feature | Xero | QuickBooks |
|---------|------|------------|
| **Terminology** | Contacts, Items, Invoices | Customers, Items, Invoices |
| **Invoices** | ACCREC/ACCPAY types | Invoice vs Sales Receipt |
| **Token Expiry** | 30 min / 60 days | 1 hour / 101 days |
| **API Calls** | 60/min, 5000/day | 500/min, 5000/hour |
| **Organizations** | Tenants | Realm ID |
| **Regions** | Global | US-focused |

## License

This Xero integration is part of the Multichannel Sales & Inventory Manager and follows the same license terms.
