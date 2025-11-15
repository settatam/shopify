# QuickBooks Online Integration Guide

## Overview

This guide covers the complete integration with QuickBooks Online, allowing you to automatically sync orders, customers, products, and financial data for seamless accounting and bookkeeping.

## Features

- ✅ OAuth 2.0 authentication with automatic token refresh
- ✅ Order syncing (as invoices or sales receipts)
- ✅ Customer management and synchronization
- ✅ Product/inventory item syncing
- ✅ Payment tracking and recording
- ✅ Multi-company support
- ✅ Configurable chart of accounts mapping
- ✅ Tax code integration
- ✅ Sandbox and Production environment support
- ✅ Real-time sync or scheduled batch processing

## Prerequisites

### 1. QuickBooks Online Account

You must have an active QuickBooks Online subscription:

1. Go to [QuickBooks Online](https://quickbooks.intuit.com/)
2. Sign up or sign in
3. Choose a subscription plan (Simple Start, Essentials, Plus, or Advanced)
4. Set up your company
5. Ensure you have administrator access

**Note**: This integration works with **QuickBooks Online only**. QuickBooks Desktop is not supported.

### 2. Intuit Developer Account and App

1. Go to [Intuit Developer Portal](https://developer.intuit.com/)
2. Sign in with your Intuit account
3. Click **"Create an app"**
4. Select **QuickBooks Online and Payments**
5. Fill in the app details:
   - **App Name**: Your app name (e.g., "Multichannel Sales Manager")
   - **Description**: Brief description
   - **Redirect URI**: `https://yourdomain.com/api/quickbooks/callback`
6. Get your credentials:
   - **Client ID**
   - **Client Secret**

### 3. Environment Setup

Add QuickBooks credentials to `.env`:

```env
# QuickBooks Configuration
QUICKBOOKS_CLIENT_ID=your_client_id_here
QUICKBOOKS_CLIENT_SECRET=your_client_secret_here
QUICKBOOKS_REDIRECT_URI=https://yourdomain.com/api/quickbooks/callback
QUICKBOOKS_SCOPES=com.intuit.quickbooks.accounting
QUICKBOOKS_SANDBOX=true  # Set to false for production
```

## Setup & Connection

### Via Frontend

1. Navigate to **Channels** page
2. Click **Connect QuickBooks**
3. Click **"Connect to QuickBooks"** button
4. You'll be redirected to Intuit
5. Log in to your QuickBooks Online account (if not already)
6. Select the company you want to connect
7. Authorize the requested permissions
8. You'll be redirected back and your company will be connected

The system will:
- Complete OAuth flow and get access/refresh tokens
- Fetch company information
- Cache chart of accounts and tax codes
- Enable automatic syncing

### Via API

```bash
# Initiate OAuth flow
GET /api/quickbooks/authorize
# User will be redirected to Intuit for authorization
# Intuit will redirect back to: /api/quickbooks/callback?code=xxx&state=xxx&realmId=xxx
```

## Initial Configuration

After connecting, configure your QuickBooks channel settings:

### 1. Chart of Accounts Mapping

Map your sales channels to QuickBooks accounts:

```php
// Set income account for sales
$channel->update([
    'policy_json' => [
        'income_account_id' => '123', // Sales/Income account ID
        'expense_account_id' => '456', // Cost of Goods Sold account ID
        'asset_account_id' => '789', // Inventory Asset account ID
        'deposit_account_id' => '101', // Bank/Checking account ID (for sales receipts)
        'payment_method_id' => '1', // Payment method ID (optional)
    ],
]);
```

### 2. Get Available Accounts

```bash
GET /api/quickbooks/channels/{channel}/accounts
```

This returns income and expense accounts from your QuickBooks company.

## Order Syncing

### How It Works

Orders can be synced to QuickBooks as either:

1. **Sales Receipts** - For cash sales (payment received immediately)
2. **Invoices** - For credit sales (payment to be received later)

### Sync an Order

```php
use App\Jobs\QuickBooks\SyncOrdersToQuickBooks;

// Sync as sales receipt (default for paid orders)
SyncOrdersToQuickBooks::dispatch($order, $quickbooksChannel, 'salesreceipt');

// Sync as invoice (for unpaid orders)
SyncOrdersToQuickBooks::dispatch($order, $quickbooksChannel, 'invoice');
```

### What Gets Synced

From each order:
- **Customer** (created if doesn't exist)
- **Line Items** (products/services)
- **Shipping Charges**
- **Taxes**
- **Payment Information** (for sales receipts)
- **Order Number** as document number
- **Order Date** as transaction date
- **Billing & Shipping Addresses**

### Sales Receipt Example

When an order is synced as a sales receipt:

```json
{
  "CustomerRef": {"value": "123"},
  "Line": [
    {
      "Amount": 29.99,
      "Description": "Product Title",
      "DetailType": "SalesItemLineDetail",
      "SalesItemLineDetail": {
        "Qty": 1,
        "UnitPrice": 29.99,
        "ItemRef": {"value": "456"}
      }
    }
  ],
  "TxnDate": "2024-01-15",
  "DocNumber": "ORD-12345",
  "PaymentMethodRef": {"value": "1"},
  "DepositToAccountRef": {"value": "789"}
}
```

### Invoice Example

For unpaid orders:

```json
{
  "CustomerRef": {"value": "123"},
  "Line": [
    {
      "Amount": 29.99,
      "Description": "Product Title",
      "DetailType": "SalesItemLineDetail",
      "SalesItemLineDetail": {
        "Qty": 1,
        "UnitPrice": 29.99
      }
    }
  ],
  "TxnDate": "2024-01-15",
  "DueDate": "2024-02-14",
  "DocNumber": "ORD-12345"
}
```

## Customer Management

### Sync Customers

```php
use App\Jobs\QuickBooks\SyncCustomersToQuickBooks;

$customerData = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '555-1234',
    'billing_address' => [...],
    'shipping_address' => [...],
];

SyncCustomersToQuickBooks::dispatch($customerData, $quickbooksChannel);
```

### Automatic Customer Matching

The system automatically:
1. Searches for existing customers by email
2. Updates existing customer if found
3. Creates new customer if not found

### Customer Data Structure

```php
$customerData = [
    'DisplayName' => 'John Doe',
    'GivenName' => 'John',
    'FamilyName' => 'Doe',
    'PrimaryEmailAddr' => ['Address' => 'john@example.com'],
    'PrimaryPhone' => ['FreeFormNumber' => '555-1234'],
    'BillAddr' => [
        'Line1' => '123 Main St',
        'City' => 'Springfield',
        'CountrySubDivisionCode' => 'IL',
        'PostalCode' => '62701',
        'Country' => 'US',
    ],
    'ShipAddr' => [
        'Line1' => '456 Oak Ave',
        'City' => 'Springfield',
        'CountrySubDivisionCode' => 'IL',
        'PostalCode' => '62702',
        'Country' => 'US',
    ],
];
```

## Product/Item Management

### Sync Products

```php
use App\Jobs\QuickBooks\SyncProductsToQuickBooks;

// Sync product and all its variants
SyncProductsToQuickBooks::dispatch($product, $quickbooksChannel);
```

### Required Account Configuration

Before syncing products, configure these accounts:

1. **Income Account** - Where sales revenue is recorded
2. **Expense Account** - Cost of Goods Sold (COGS)
3. **Asset Account** - Inventory Asset account

### Item Type

Products are synced as **Inventory** items with quantity tracking:

```php
$itemData = [
    'Name' => 'Product Name - Variant',
    'Description' => 'Product description',
    'Type' => 'Inventory',
    'Sku' => 'SKU-12345',
    'TrackQtyOnHand' => true,
    'QtyOnHand' => 100,
    'UnitPrice' => 29.99,
    'PurchaseCost' => 15.00,
    'IncomeAccountRef' => ['value' => '123'],
    'ExpenseAccountRef' => ['value' => '456'],
    'AssetAccountRef' => ['value' => '789'],
];
```

### Automatic Item Matching

The system:
1. Searches for existing items by SKU
2. Updates existing item if found
3. Creates new item if not found
4. Stores QuickBooks Item ID in variant metadata

## API Client Usage

### Initialize Client

```php
use App\Services\QuickBooks\QuickBooksClient;

$client = new QuickBooksClient($quickbooksChannel);
```

### Available Methods

#### Customers

```php
// Get all customers
$customers = $client->getCustomers(100);

// Get specific customer
$customer = $client->getCustomer('123');

// Create customer
$result = $client->createCustomer($customerData);

// Update customer
$result = $client->updateCustomer('123', $customerData);
```

#### Items (Products)

```php
// Get all items
$items = $client->getItems(100);

// Get specific item
$item = $client->getItem('456');

// Create item
$result = $client->createItem($itemData);

// Update item
$result = $client->updateItem('456', $itemData);
```

#### Invoices

```php
// Get all invoices
$invoices = $client->getInvoices(100);

// Get specific invoice
$invoice = $client->getInvoice('789');

// Create invoice
$result = $client->createInvoice($invoiceData);

// Update invoice
$result = $client->updateInvoice('789', $invoiceData);

// Delete/void invoice
$result = $client->deleteInvoice('789');
```

#### Sales Receipts

```php
// Get all sales receipts
$receipts = $client->getSalesReceipts(100);

// Get specific sales receipt
$receipt = $client->getSalesReceipt('101');

// Create sales receipt
$result = $client->createSalesReceipt($receiptData);

// Update sales receipt
$result = $client->updateSalesReceipt('101', $receiptData);
```

#### Payments

```php
// Get all payments
$payments = $client->getPayments(100);

// Get specific payment
$payment = $client->getPayment('202');

// Create payment
$result = $client->createPayment($paymentData);
```

#### Accounts

```php
// Get all accounts
$accounts = $client->getAccounts();

// Get specific account
$account = $client->getAccount('303');

// Get income accounts
$incomeAccounts = $client->getIncomeAccounts();

// Get expense accounts
$expenseAccounts = $client->getExpenseAccounts();
```

#### Tax Codes

```php
// Get all tax codes
$taxCodes = $client->getTaxCodes();

// Get tax rates
$taxRates = $client->getTaxRates();
```

#### Company Info

```php
// Get company information
$companyInfo = $client->getCompanyInfo();

// Get preferences
$preferences = $client->getPreferences();
```

#### Query (SQL-like)

```php
// Custom queries
$result = $client->query("SELECT * FROM Customer WHERE GivenName = 'John'");
$result = $client->query("SELECT * FROM Invoice WHERE TxnDate > '2024-01-01'");
```

## Authentication

QuickBooks uses OAuth 2.0 authorization code flow:

1. **Redirect to Intuit**: User authorizes app
2. **Authorization Code**: Intuit redirects back with code and realm ID
3. **Exchange for Token**: Exchange code for access/refresh tokens
4. **Access Token**: Valid for 3600 seconds (1 hour)
5. **Refresh Token**: Valid for 8726400 seconds (101 days)
6. **Auto-Refresh**: Tokens automatically refreshed when expired

### Token Storage

Tokens are stored in `channels.auth_json`:

```json
{
  "access_token": "xxx",
  "refresh_token": "xxx",
  "expires_at": 1234567890,
  "x_refresh_token_expires_at": 1234567890,
  "realm_id": "123456789",
  "company_name": "My Company",
  "country": "US"
}
```

### Token Refresh

Automatic token refresh happens when:
- Access token is expired (before API call)
- API returns 401 Unauthorized

## Best Practices

### 1. Account Mapping

- **Income Account**: Map to your main sales revenue account
- **COGS Account**: Map to Cost of Goods Sold
- **Asset Account**: Map to Inventory Asset
- **Deposit Account**: Use checking/savings for sales receipts
- Keep mappings consistent across channels

### 2. Document Type Selection

Use **Sales Receipts** when:
- Payment is received at time of sale
- Cash or immediate credit card payment
- No accounts receivable tracking needed

Use **Invoices** when:
- Payment terms are net 30, 60, etc.
- Tracking accounts receivable
- Need payment reminders

### 3. Customer Management

- Always search for existing customers by email first
- Use consistent naming conventions
- Keep customer data synchronized
- Update addresses when changed

### 4. Inventory Tracking

- Sync products before syncing orders
- Keep QuickBooks item IDs in variant metadata
- Update quantities regularly
- Monitor COGS and inventory value

### 5. Data Sync Frequency

- **Orders**: Sync immediately or hourly
- **Customers**: Sync when created/updated
- **Products**: Sync daily or when changed
- **Inventory**: Sync every 4-6 hours

## Troubleshooting

### Connection Issues

**Error: "Failed to connect QuickBooks"**

- Verify Client ID and Client Secret are correct
- Check redirect URI matches exactly (including https)
- Ensure using QuickBooks Online (not Desktop)
- Check QuickBooks Online subscription is active

**Error: "Token expired"**

- Refresh tokens expire after 101 days
- System auto-refreshes but may fail if not used
- Disconnect and reconnect to get new tokens

### Sync Errors

**Error: "Account not configured"**

- Set up income, expense, and asset accounts in channel policy
- Get account IDs via `/api/quickbooks/channels/{channel}/accounts`

**Error: "Customer not found"**

- Customer was deleted in QuickBooks
- Clear QuickBooks customer ID from order metadata
- Re-sync will create new customer

**Error: "SyncToken mismatch"**

- Another process updated the record
- Retry the operation to get latest SyncToken

**Error: "Duplicate document number"**

- Order number already used in QuickBooks
- QuickBooks requires unique document numbers
- Append channel name or use different numbering

### Item Errors

**Error: "Income account required"**

- All items need an income account
- Configure in channel policy

**Error: "Asset/Expense account required for inventory"**

- Inventory items need COGS and asset accounts
- Or change item type to "NonInventory"

## Advanced Features

### Multi-Company Support

Connect multiple QuickBooks companies:

```php
// Each company is a separate channel
$channel1 = Channel::where('type', 'quickbooks')
    ->where('external_id', 'company_1_realm_id')
    ->first();

$channel2 = Channel::where('type', 'quickbooks')
    ->where('external_id', 'company_2_realm_id')
    ->first();
```

### Custom Account Mapping Per Channel

```php
$channel->update([
    'policy_json' => [
        'income_account_id' => '123',
        'expense_account_id' => '456',
        'asset_account_id' => '789',
        'deposit_account_id' => '101',
        'payment_method_id' => '1',
        // Custom mappings
        'shipping_item_id' => '999', // Use specific item for shipping
        'tax_code_id' => '5', // Default tax code
    ],
]);
```

### Batch Processing

Process multiple orders at once:

```php
use App\Jobs\QuickBooks\SyncOrdersToQuickBooks;

$orders = ChannelOrder::where('financial_status', 'paid')
    ->whereNull('sync_metadata->quickbooks_synced')
    ->get();

foreach ($orders as $order) {
    SyncOrdersToQuickBooks::dispatch($order, $quickbooksChannel);
}
```

### Query Reports

Get financial data from QuickBooks:

```php
$client = new QuickBooksClient($channel);

// Get all invoices from last month
$startDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
$endDate = now()->subMonth()->endOfMonth()->format('Y-m-d');

$invoices = $client->query(
    "SELECT * FROM Invoice WHERE TxnDate >= '{$startDate}' AND TxnDate <= '{$endDate}'"
);

// Get total sales
$totalSales = array_reduce(
    $invoices['QueryResponse']['Invoice'] ?? [],
    fn($sum, $invoice) => $sum + $invoice['TotalAmt'],
    0
);
```

## Scheduled Jobs

Set up cron jobs for automation:

```php
// In routes/console.php or App\Console\Kernel

// Sync paid orders every hour
$schedule->call(function () {
    $qbChannels = Channel::where('type', 'quickbooks')
        ->where('status', 'connected')
        ->get();

    foreach ($qbChannels as $channel) {
        $orders = ChannelOrder::where('financial_status', 'paid')
            ->whereNull('sync_metadata->quickbooks_synced')
            ->get();

        foreach ($orders as $order) {
            SyncOrdersToQuickBooks::dispatch($order, $channel);
        }
    }
})->hourly();

// Sync products daily
$schedule->call(function () {
    $qbChannels = Channel::where('type', 'quickbooks')
        ->where('status', 'connected')
        ->get();

    foreach ($qbChannels as $channel) {
        Product::chunk(50, function ($products) use ($channel) {
            foreach ($products as $product) {
                SyncProductsToQuickBooks::dispatch($product, $channel);
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
7. **Token Revocation**: Tokens revoked on disconnect

## Rate Limits

QuickBooks Online has rate limits:

- **500 requests per minute per company**
- **5000 requests per hour per company**
- **Burst limit**: 100 requests in 1 minute

The client includes automatic retry logic with exponential backoff.

## Support Resources

- [Intuit Developer Portal](https://developer.intuit.com/)
- [QuickBooks API Documentation](https://developer.intuit.com/app/developer/qbo/docs/api/accounting/all-entities/account)
- [QuickBooks Community](https://quickbooks.intuit.com/learn-support/)
- [API Explorer](https://developer.intuit.com/app/developer/qbo/docs/api/accounting/all-entities/account)

## Compliance

### Requirements

- Valid QuickBooks Online subscription
- Administrator access to company
- Accurate financial data
- Proper account classification
- Tax compliance

### Data Accuracy

- Verify all sync data before finalizing
- Review QuickBooks reports regularly
- Reconcile accounts monthly
- Maintain audit trail

## Sandbox vs Production

### Sandbox Environment

- URL: `https://sandbox-quickbooks.api.intuit.com/v3`
- Use for testing without affecting live data
- Separate company/realm ID
- Separate credentials (can use same app)
- Data is isolated

### Production Environment

- URL: `https://quickbooks.api.intuit.com/v3`
- Real company data
- Real financial impact
- Use production credentials
- Monitor carefully

### Switching Environments

Update `.env`:

```env
# For Sandbox
QUICKBOOKS_SANDBOX=true

# For Production
QUICKBOOKS_SANDBOX=false
```

## Common Use Cases

### 1. E-commerce to Accounting

Automatically sync all paid orders:

```php
// When order is marked as paid
if ($order->financial_status === 'paid') {
    SyncOrdersToQuickBooks::dispatch($order, $qbChannel, 'salesreceipt');
}
```

### 2. Inventory Management

Keep QuickBooks inventory in sync:

```php
// When inventory changes
SyncProductsToQuickBooks::dispatch($product, $qbChannel);
```

### 3. Customer Database Sync

Maintain unified customer records:

```php
// When customer is created or updated
SyncCustomersToQuickBooks::dispatch($customerData, $qbChannel);
```

### 4. Financial Reporting

Pull data for custom reports:

```php
$client = new QuickBooksClient($qbChannel);
$salesData = $client->query("SELECT * FROM SalesReceipt WHERE TxnDate >= '2024-01-01'");
```

## License

This QuickBooks integration is part of the Multichannel Sales & Inventory Manager and follows the same license terms.
