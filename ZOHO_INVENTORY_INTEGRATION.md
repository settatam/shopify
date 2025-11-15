# Zoho Inventory Integration

This document provides comprehensive information about the Zoho Inventory integration for syncing products, inventory levels, and sales orders.

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Setup](#setup)
5. [Configuration](#configuration)
6. [Product Sync](#product-sync)
7. [Inventory Sync](#inventory-sync)
8. [Order Sync](#order-sync)
9. [API Reference](#api-reference)
10. [Troubleshooting](#troubleshooting)

## Overview

Zoho Inventory is a cloud-based inventory management software that helps businesses track inventory, manage warehouses, and process orders. This integration provides bidirectional synchronization between your multichannel sales platform and Zoho Inventory.

### Key Benefits

- **Centralized Inventory**: Manage all inventory from Zoho Inventory
- **Automatic Updates**: Products and inventory sync automatically
- **Order Management**: Create sales orders in Zoho for all channels
- **Multi-Warehouse**: Support for multiple warehouse locations
- **Real-Time Sync**: Keep inventory levels accurate across platforms

## Features

### Implemented Features

1. **OAuth 2.0 Authentication**
   - Secure connection to Zoho Inventory
   - Automatic token refresh
   - Multi-organization support
   - Data center selection (com, eu, in, etc.)

2. **Product Synchronization**
   - Sync products from platform to Zoho
   - Support for variants as separate items
   - Custom fields for variant information
   - Category mapping

3. **Inventory Synchronization**
   - Unidirectional sync to Zoho
   - Safety stock support
   - Inventory adjustments with reasons
   - Stock level tracking

4. **Order Synchronization**
   - Create sales orders in Zoho from channel orders
   - Auto-confirm orders (optional)
   - Customer information sync
   - Shipping address mapping

5. **Warehouse Management**
   - List available warehouses
   - Select default warehouse
   - Multi-warehouse inventory tracking

## Architecture

### Components

```
Backend:
├── app/
│   ├── Services/ZohoInventory/
│   │   └── ZohoInventoryClient.php       # API client
│   ├── Http/Controllers/ZohoInventory/
│   │   └── AuthController.php            # OAuth & settings
│   └── Jobs/ZohoInventory/
│       ├── SyncProductToZoho.php         # Product sync
│       ├── SyncInventoryToZoho.php       # Inventory sync
│       ├── FetchZohoSalesOrders.php      # Fetch orders
│       └── SyncOrderToZoho.php           # Push orders

Frontend:
├── resources/js/Pages/ZohoInventory/
│   └── Connect.vue                        # Connection UI
```

### Data Flow

```
Product Created/Updated
         ↓
SyncProductToZoho Job
         ↓
Zoho Inventory API
         ↓
Item Created/Updated in Zoho


Inventory Change
         ↓
SyncInventoryToZoho Job
         ↓
Calculate Adjustment Needed
         ↓
Zoho Inventory Adjustment


Channel Order Created
         ↓
SyncOrderToZoho Job
         ↓
Zoho Sales Order Created
         ↓
Auto-Confirm (optional)
```

## Setup

### Prerequisites

1. Zoho Inventory account
2. Zoho API credentials (Client ID & Secret)
3. Organization created in Zoho Inventory
4. Laravel 12 application

### Step 1: Create Zoho API Credentials

1. Go to [Zoho API Console](https://api-console.zoho.com/)
2. Click "Add Client"
3. Select "Server-based Applications"
4. Fill in details:
   - **Client Name**: Your App Name
   - **Homepage URL**: Your app URL
   - **Authorized Redirect URIs**: `https://yourdomain.com/api/zoho-inventory/callback`
5. Click "Create"
6. Note down your **Client ID** and **Client Secret**

### Step 2: Configure Environment

Add to your `.env` file:

```env
ZOHO_INVENTORY_CLIENT_ID=your_client_id
ZOHO_INVENTORY_CLIENT_SECRET=your_client_secret
ZOHO_INVENTORY_REDIRECT_URI=https://yourdomain.com/api/zoho-inventory/callback
ZOHO_INVENTORY_DATACENTER=com
```

**Data Centers:**
- `com` - United States (zoho.com)
- `eu` - Europe (zoho.eu)
- `in` - India (zoho.in)
- `com.au` - Australia (zoho.com.au)
- `jp` - Japan (zoho.jp)

### Step 3: Connect Zoho Inventory

1. Navigate to Zoho Inventory integration page
2. Click "Connect to Zoho Inventory"
3. Sign in to Zoho and authorize the application
4. Select your organization
5. Configure sync settings

## Configuration

### Sync Settings

**Sync Products to Zoho Inventory**
- When enabled: Products created/updated in the platform are synced to Zoho
- Product variants are created as separate items in Zoho
- SKU is used as the unique identifier

**Sync Inventory to Zoho Inventory**
- When enabled: Inventory changes are pushed to Zoho
- Uses inventory adjustments in Zoho
- Respects safety stock settings

**Sync Orders to Zoho Inventory**
- When enabled: Orders from other channels create sales orders in Zoho
- Includes customer information and line items
- Maps shipping addresses

**Auto-Confirm Orders**
- When enabled: Sales orders are automatically confirmed in Zoho
- When disabled: Orders remain in draft status for manual review

### Default Warehouse

Select which Zoho warehouse to use for inventory operations. This warehouse will be used when:
- Performing inventory adjustments
- Creating sales orders
- Tracking stock levels

## Product Sync

### How It Works

1. **Product Creation/Update**: When a product is created or updated in your platform
2. **Job Dispatch**: `SyncProductToZoho` job is queued
3. **Variant Processing**:
   - Products with variants: Each variant becomes a separate Zoho item
   - Single products: Created as one item
4. **Mapping Storage**: Zoho item IDs stored in channel mapping for future updates

### Product Data Mapping

| Platform Field | Zoho Field | Notes |
|---------------|-----------|-------|
| Title + Variant Title | Name | Combined for variants |
| SKU | SKU | Unique identifier |
| Description | Description | |
| Price | Rate | Per unit price |
| Category | Category Name | Created if doesn't exist |
| Weight | Weight | With unit |
| Barcode | Custom Field | Stored in custom fields |

### Example

```php
// Trigger product sync
use App\Jobs\ZohoInventory\SyncProductToZoho;

$channel = Channel::where('channel_type', 'zoho_inventory')->first();
$product = Product::find(1);

SyncProductToZoho::dispatch($channel, $product);
```

### Variants Handling

For a product with 3 variants (Small, Medium, Large):

```
Product: "T-Shirt"
Variant 1: "T-Shirt - Small" (SKU: TSHIRT-SM)
Variant 2: "T-Shirt - Medium" (SKU: TSHIRT-MD)
Variant 3: "T-Shirt - Large" (SKU: TSHIRT-LG)
```

Creates in Zoho:
```
Item 1: Name: "T-Shirt - Small", SKU: "TSHIRT-SM"
Item 2: Name: "T-Shirt - Medium", SKU: "TSHIRT-MD"
Item 3: Name: "T-Shirt - Large", SKU: "TSHIRT-LG"
```

## Inventory Sync

### How It Works

1. **Inventory Change**: Stock levels change in your platform
2. **Job Dispatch**: `SyncInventoryToZoho` job is queued
3. **Current Stock Fetch**: Get current Zoho inventory level
4. **Calculate Adjustment**: Determine difference
5. **Apply Adjustment**: Create inventory adjustment in Zoho with reason

### Safety Stock

Safety stock is a buffer quantity that won't be synced to Zoho:

```
Platform Stock: 100 units
Safety Stock: 10 units
Synced to Zoho: 90 units
```

Configure in channel settings:
```php
$channel->settings = [
    'safety_stock' => 10,
];
```

### Adjustment Reasons

- Stock Increase: "Stock increase from multichannel platform"
- Stock Decrease: "Stock decrease from multichannel platform"

### Example

```php
use App\Jobs\ZohoInventory\SyncInventoryToZoho;

$channel = Channel::where('channel_type', 'zoho_inventory')->first();
$variant = ProductVariant::find(1);

SyncInventoryToZoho::dispatch($channel, $variant);
```

## Order Sync

### Syncing Orders TO Zoho

When orders come from other channels (Shopify, eBay, etc.), they can be synced to Zoho as sales orders.

#### Order Data Mapping

| Platform Field | Zoho Field |
|---------------|-----------|
| Customer Name | customer_name |
| Customer Email | email |
| Customer Phone | phone |
| Order Number | reference_number |
| Shipping Address | shipping_address |
| Line Items | line_items |
| Notes | notes |

#### Order Status Mapping

The job handles order status translation:

| Zoho Status | Platform Status |
|------------|----------------|
| draft | pending |
| confirmed | processing |
| approved | processing |
| invoiced | completed |
| closed | completed |
| void | cancelled |

#### Example

```php
use App\Jobs\ZohoInventory\SyncOrderToZoho;

$channel = Channel::where('channel_type', 'zoho_inventory')->first();
$order = ChannelOrder::find(1);

SyncOrderToZoho::dispatch($channel, $order);
```

### Fetching Orders FROM Zoho

Sales orders created directly in Zoho can be imported into your platform.

```php
use App\Jobs\ZohoInventory\FetchZohoSalesOrders;

$channel = Channel::where('channel_type', 'zoho_inventory')->first();

FetchZohoSalesOrders::dispatch($channel);
```

This job:
1. Fetches sales orders from last 30 days
2. Creates new orders in platform
3. Updates existing orders
4. Imports line items

### Auto-Confirm

When enabled, sales orders are automatically confirmed after creation:

```php
// In Zoho, order status changes:
draft → confirmed
```

This makes the order ready for fulfillment in Zoho.

## API Reference

### ZohoInventoryClient Methods

#### Items (Products)

```php
$client = new ZohoInventoryClient($channel);

// Get all items
$items = $client->getItems($page = 1, $perPage = 200);

// Get single item
$item = $client->getItem($itemId);

// Create item
$item = $client->createItem([
    'name' => 'Product Name',
    'sku' => 'PROD-SKU',
    'rate' => 29.99,
]);

// Update item
$item = $client->updateItem($itemId, $data);
```

#### Inventory

```php
// Get item stock
$stock = $client->getItemStock($itemId);

// Adjust inventory
$adjustment = $client->adjustInventory(
    $itemId,
    $quantityAdjustment,
    $reason = 'Stock adjustment'
);
```

#### Sales Orders

```php
// Get sales orders
$orders = $client->getSalesOrders($page = 1, [
    'status' => 'confirmed',
    'date' => '2025-01-01',
]);

// Get single order
$order = $client->getSalesOrder($orderId);

// Create sales order
$order = $client->createSalesOrder([
    'customer_name' => 'John Doe',
    'line_items' => [
        [
            'name' => 'Product',
            'sku' => 'SKU-123',
            'rate' => 29.99,
            'quantity' => 2,
        ],
    ],
]);

// Confirm sales order
$result = $client->confirmSalesOrder($orderId);
```

#### Warehouses

```php
// Get all warehouses
$warehouses = $client->getWarehouses();
```

#### Organizations

```php
// Get organizations (for initial setup)
$orgs = $client->getOrganizations($accessToken);
```

### API Endpoints

#### Authorization

```http
GET /api/zoho-inventory/authorize
```

Initiates OAuth flow. Returns authorization URL.

**Response:**
```json
{
  "success": true,
  "authorization_url": "https://accounts.zoho.com/oauth/v2/auth?..."
}
```

#### OAuth Callback

```http
GET /api/zoho-inventory/callback?code=...&state=...
```

Handles OAuth callback. Exchanges code for access token.

#### Test Connection

```http
POST /api/zoho-inventory/channels/{channel}/test
```

Tests the Zoho Inventory connection.

**Response:**
```json
{
  "success": true,
  "message": "Successfully connected to Zoho Inventory"
}
```

#### Get Organizations

```http
GET /api/zoho-inventory/channels/{channel}/organizations
```

Gets all organizations associated with the account.

**Response:**
```json
{
  "success": true,
  "organizations": [
    {
      "organization_id": "123456",
      "name": "My Company",
      "currency_code": "USD"
    }
  ]
}
```

#### Switch Organization

```http
POST /api/zoho-inventory/channels/{channel}/switch-organization

{
  "organization_id": "123456"
}
```

Switches to a different organization.

#### Get Warehouses

```http
GET /api/zoho-inventory/channels/{channel}/warehouses
```

Gets all warehouses for the organization.

**Response:**
```json
{
  "success": true,
  "warehouses": [
    {
      "warehouse_id": "789",
      "warehouse_name": "Main Warehouse",
      "status": "active"
    }
  ]
}
```

#### Update Settings

```http
POST /api/zoho-inventory/channels/{channel}/settings

{
  "sync_products": true,
  "sync_inventory": true,
  "sync_orders": true,
  "auto_confirm_orders": false,
  "default_warehouse_id": "789"
}
```

Updates integration settings.

#### Disconnect

```http
POST /api/zoho-inventory/channels/{channel}/disconnect
```

Disconnects the Zoho Inventory integration.

## Troubleshooting

### Authentication Issues

**Problem**: OAuth fails with "invalid_client"

**Solutions:**
1. Verify Client ID and Secret in `.env`
2. Check redirect URI matches exactly in Zoho API Console
3. Ensure data center is correct (com, eu, in, etc.)

**Problem**: Token refresh fails

**Solutions:**
1. Check if refresh token is stored correctly
2. Verify token hasn't been revoked in Zoho
3. Re-authenticate by disconnecting and reconnecting

### Product Sync Issues

**Problem**: Products not appearing in Zoho

**Solutions:**
1. Check job queue is running: `php artisan queue:work`
2. Review job logs for errors
3. Verify SKU is unique
4. Check product has at least one variant

**Problem**: Duplicate items in Zoho

**Solutions:**
1. Ensure mapping is being stored correctly
2. Check SKU uniqueness
3. Review channel mapping table

### Inventory Sync Issues

**Problem**: Inventory not updating in Zoho

**Solutions:**
1. Verify "Sync Inventory" setting is enabled
2. Check that product mapping exists
3. Review inventory adjustment logs in Zoho
4. Verify item ID is correct

**Problem**: Inventory always off by same amount

**Solution:**
Check safety stock setting - it may be configured and reducing synced quantity.

### Order Sync Issues

**Problem**: Orders not creating in Zoho

**Solutions:**
1. Verify "Sync Orders" setting is enabled
2. Check order is not from Zoho channel (prevents loop)
3. Review required fields (customer_name, line_items)
4. Check item SKUs exist in Zoho

**Problem**: Orders stuck in draft

**Solution:**
Enable "Auto-Confirm Orders" setting, or manually confirm in Zoho.

### API Rate Limits

Zoho Inventory has API rate limits:
- **Free**: 100 requests per minute
- **Paid**: 150 requests per minute

If hitting rate limits:
1. Implement queue delay between jobs
2. Batch operations where possible
3. Use webhook notifications instead of polling

### Debugging

**Enable detailed logging:**

```php
// In ZohoInventoryClient.php
Log::debug('Zoho API Request', [
    'method' => $method,
    'endpoint' => $endpoint,
    'data' => $data,
]);

Log::debug('Zoho API Response', [
    'response' => $response->json(),
]);
```

**Check job failures:**

```bash
# View failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {id}

# Retry all failed jobs
php artisan queue:retry all
```

**Test API connection:**

```php
use App\Services\ZohoInventory\ZohoInventoryClient;

$channel = Channel::where('channel_type', 'zoho_inventory')->first();
$client = new ZohoInventoryClient($channel);

$result = $client->testConnection();
var_dump($result); // Should return true
```

## Best Practices

### Data Synchronization

1. **Initial Sync**: Use batch jobs for initial product sync
2. **Incremental Updates**: Sync only changed items
3. **Queue Management**: Monitor queue for failed jobs
4. **Safety Stock**: Configure appropriate buffer levels
5. **Webhook Integration**: Use Zoho webhooks for real-time updates (future enhancement)

### Performance

1. **Batch Operations**: Sync multiple items in one API call where possible
2. **Job Throttling**: Delay between jobs to avoid rate limits
3. **Caching**: Cache warehouse and organization data
4. **Selective Sync**: Only sync products that are actively sold

### Security

1. **Token Storage**: Tokens are encrypted in database
2. **Token Refresh**: Automatic refresh before expiration
3. **API Credentials**: Store in `.env`, never in code
4. **HTTPS**: Always use HTTPS for callbacks
5. **State Validation**: OAuth state parameter prevents CSRF

### Error Handling

1. **Retry Logic**: Jobs retry 3 times with backoff
2. **Logging**: All errors logged with context
3. **Notifications**: Alert on repeated failures
4. **Manual Review**: Failed jobs can be reviewed and retried

## Advanced Usage

### Custom Warehouse Mapping

Map different product locations to Zoho warehouses:

```php
$settings = [
    'warehouse_mapping' => [
        'location_1' => 'zoho_warehouse_abc',
        'location_2' => 'zoho_warehouse_xyz',
    ],
];
```

### Custom Field Mapping

Add custom fields to Zoho items:

```php
$itemData = [
    'name' => 'Product',
    'sku' => 'SKU-123',
    'custom_fields' => [
        ['label' => 'Brand', 'value' => 'ACME'],
        ['label' => 'Supplier', 'value' => 'Vendor Inc'],
    ],
];
```

### Bulk Operations

Sync all products at once:

```php
use App\Jobs\ZohoInventory\SyncProductToZoho;

$channel = Channel::where('channel_type', 'zoho_inventory')->first();
$products = Product::where('shop_id', $shop->id)->get();

foreach ($products as $product) {
    SyncProductToZoho::dispatch($channel, $product);
}
```

### Scheduled Syncs

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sync inventory every hour
    $schedule->call(function () {
        $channels = Channel::where('channel_type', 'zoho_inventory')->get();
        foreach ($channels as $channel) {
            if ($channel->settings['sync_inventory'] ?? false) {
                // Dispatch inventory sync jobs
            }
        }
    })->hourly();

    // Fetch orders every 15 minutes
    $schedule->call(function () {
        $channels = Channel::where('channel_type', 'zoho_inventory')->get();
        foreach ($channels as $channel) {
            FetchZohoSalesOrders::dispatch($channel);
        }
    })->everyFifteenMinutes();
}
```

## Future Enhancements

Potential improvements for the integration:

1. **Webhooks**: Real-time notifications from Zoho
2. **Composite Items**: Support for bundle/kit products
3. **Purchase Orders**: Sync purchase orders from Zoho
4. **Shipments**: Track shipment status
5. **Price Lists**: Support for multiple price lists
6. **Tax Management**: Sync tax configurations
7. **Serial Numbers**: Track serial number inventory
8. **Batch Tracking**: Track batch/lot numbers

## Support

For issues with:
- **Zoho API**: [Zoho Inventory API Documentation](https://www.zoho.com/inventory/api/v1/)
- **OAuth**: [Zoho OAuth Documentation](https://www.zoho.com/accounts/protocol/oauth.html)
- **Integration**: Check logs and troubleshooting section above

## References

- [Zoho Inventory API v1 Documentation](https://www.zoho.com/inventory/api/v1/)
- [Zoho OAuth 2.0 Guide](https://www.zoho.com/accounts/protocol/oauth/web-server-applications.html)
- [Zoho API Console](https://api-console.zoho.com/)
- [Zoho Data Centers](https://www.zoho.com/inventory/api/v1/#organization-id)

---

**Last Updated**: November 15, 2025
**Integration Version**: 1.0.0
**Supported Zoho Inventory API**: v1
