# Square POS Integration

This document provides comprehensive information about the Square POS integration for the multichannel sales and inventory management system.

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Setup](#setup)
5. [Authentication](#authentication)
6. [Catalog Sync](#catalog-sync)
7. [Inventory Sync](#inventory-sync)
8. [Order Processing](#order-processing)
9. [Webhooks](#webhooks)
10. [API Reference](#api-reference)
11. [Database Schema](#database-schema)
12. [Troubleshooting](#troubleshooting)

## Overview

The Square POS integration allows users to sell inventory items through Square's point-of-sale system while maintaining synchronized inventory levels and product catalogs. This integration is perfect for businesses that sell both online and in physical retail locations.

### Key Benefits

- **Unified Inventory**: Sell through Square POS and automatically update local inventory
- **Catalog Sync**: Push products and prices to Square catalog
- **Order Import**: Import all Square sales into your system
- **Real-time Updates**: Webhook support for instant notifications
- **Multi-location**: Support for multiple Square locations

## Features

### Implemented Features

1. **OAuth 2.0 Authentication**
   - Secure authorization flow
   - Automatic token refresh
   - 30-day access token validity
   - Support for sandbox and production environments

2. **Product Catalog Management**
   - Create items in Square catalog
   - Create variations for different product options
   - Update existing catalog items
   - SKU-based mapping
   - Price synchronization

3. **Inventory Synchronization**
   - Push inventory counts to Square
   - Respect safety stock settings
   - Location-based inventory
   - Automatic inventory adjustment on Square sales

4. **Order Processing**
   - Fetch completed orders from Square
   - Import line items and payment details
   - Automatic local inventory deduction
   - Customer information extraction
   - 7-day default order history

5. **Webhook Support**
   - Real-time payment notifications
   - Inventory update events
   - Order creation/update events
   - Catalog change notifications
   - Signature verification for security

## Architecture

### Components

```
app/
├── Services/Square/
│   └── SquareClient.php          # Main API client
├── Http/Controllers/Square/
│   ├── AuthController.php         # OAuth and channel management
│   └── WebhookController.php      # Webhook event handler
└── Jobs/Square/
    ├── SyncCatalogToSquare.php    # Product sync job
    ├── SyncInventoryToSquare.php  # Inventory sync job
    └── FetchSquareOrders.php      # Order import job
```

### Data Flow

```
Local Product → SyncCatalogToSquare → Square Catalog API → Square Catalog Item
Local Variant → SyncInventoryToSquare → Square Inventory API → Square Inventory Count
Square POS Sale → Webhook/FetchOrders → ChannelOrder → Local Inventory Decrease
```

## Setup

### Prerequisites

1. Square developer account
2. Square application credentials
3. Active Square location

### Environment Configuration

Add the following to your `.env` file:

```env
# Square Configuration
SQUARE_APPLICATION_ID=your_application_id
SQUARE_APPLICATION_SECRET=your_application_secret
SQUARE_ACCESS_TOKEN=your_access_token_if_using_personal_token
SQUARE_REDIRECT_URI=http://your-app.com/api/square/callback
SQUARE_SANDBOX=true  # Set to false for production
SQUARE_WEBHOOK_SIGNATURE_KEY=your_webhook_signature_key
```

### Getting Square Credentials

1. **Create a Square Application**
   - Go to https://developer.squareup.com/
   - Sign in and navigate to "Applications"
   - Click "New Application"
   - Fill in application details

2. **Get Application Credentials**
   - Open your application
   - Copy "Application ID" (use as `SQUARE_APPLICATION_ID`)
   - Copy "Application Secret" (use as `SQUARE_APPLICATION_SECRET`)
   - Set redirect URL to `http://your-domain.com/api/square/callback`

3. **Set Up Webhooks** (Optional but Recommended)
   - In your Square application settings, go to "Webhooks"
   - Add webhook URL: `http://your-domain.com/api/square/webhook`
   - Subscribe to events:
     - `payment.created`
     - `payment.updated`
     - `order.created`
     - `order.updated`
     - `inventory.count.updated`
     - `catalog.version.updated`
   - Copy the signature key (use as `SQUARE_WEBHOOK_SIGNATURE_KEY`)

### Installation

The Square PHP SDK is not required as we use direct HTTP requests via GuzzleHttp, which is already included in Laravel.

## Authentication

### OAuth 2.0 Flow

The Square integration uses OAuth 2.0 for secure authentication:

1. **User Initiates Connection**
   ```php
   GET /api/square/authorize
   ```
   - Redirects to Square authorization page
   - User logs in and grants permissions

2. **Square Redirects Back**
   ```php
   GET /api/square/callback?code=AUTH_CODE&state=CSRF_STATE
   ```
   - Exchanges code for access token
   - Fetches merchant information and locations
   - Creates/updates Channel record

3. **Token Storage**
   ```php
   'auth_json' => [
       'access_token' => '...',
       'refresh_token' => '...',
       'expires_at' => timestamp,
       'merchant_id' => '...',
       'location_id' => '...',
       'location_name' => '...',
       'currency' => 'USD',
       'country' => 'US',
   ]
   ```

### Required Permissions

The integration requests the following Square scopes:

- `MERCHANT_PROFILE_READ` - Read business information
- `ITEMS_READ` - Read catalog items
- `ITEMS_WRITE` - Create/update catalog items
- `INVENTORY_READ` - Read inventory counts
- `INVENTORY_WRITE` - Update inventory counts
- `ORDERS_READ` - Read orders and sales
- `ORDERS_WRITE` - Create orders (future use)
- `PAYMENTS_READ` - Read payment details

### Token Refresh

Square access tokens expire after 30 days. The `SquareClient` automatically refreshes tokens when needed:

```php
protected function refreshAccessToken(): array
{
    // Checks if token is expired
    // Requests new token using refresh_token
    // Updates channel with new credentials
}
```

## Catalog Sync

### Syncing Products to Square

Products are synced as Square catalog items with variations:

```php
// Dispatch catalog sync job
SyncCatalogToSquare::dispatch($channel, $product);
```

### Square Catalog Structure

```
ITEM (Product)
├── name: Product Title
├── description: Product Description
└── variations: []
    ├── ITEM_VARIATION (Product Variant)
    │   ├── name: Variant Title
    │   ├── sku: SKU
    │   ├── price_money: { amount: cents, currency: USD }
    │   └── track_inventory: true
    └── ...
```

### Creating a Catalog Item

```php
$catalogItem = $client->buildCatalogItem([
    'id' => $product->id,
    'sku' => $variant->sku,
    'title' => $product->title,
    'description' => $product->description,
    'variants' => [
        [
            'sku' => 'SKU-001',
            'title' => 'Size: M',
            'price' => 29.99,
        ]
    ],
]);

$response = $client->createCatalogObject($catalogItem);
```

### Metadata Storage

After syncing, Square IDs are stored in the product variant's `sync_metadata`:

```php
'sync_metadata' => [
    'square_item_id' => 'ABC123...',      // Catalog item ID
    'square_variation_id' => 'XYZ789...', // Variation ID
]
```

### Update vs. Create

The sync job checks if a Square item already exists:

```php
$existingItemId = $variant->sync_metadata['square_item_id'] ?? null;

if ($existingItemId) {
    // Update existing item
    $response = $client->updateCatalogObject($existingItemId, $catalogItem);
} else {
    // Create new item
    $response = $client->createCatalogObject($catalogItem);
}
```

## Inventory Sync

### Syncing Inventory to Square

```php
// Dispatch inventory sync job
SyncInventoryToSquare::dispatch($channel, $variant);
```

### Inventory Calculation

The job calculates available inventory based on channel policy:

```php
// Get total quantity from included locations
$totalQuantity = 0;
foreach ($stockItems as $stockItem) {
    if (in_array($stockItem->location_id, $includedLocations)) {
        $totalQuantity += $stockItem->quantity;
    }
}

// Subtract reserved quantity
$reserved = $variant->stock_items()
    ->whereIn('location_id', $includedLocations)
    ->sum('reserved_quantity');

// Apply safety stock
$safetyStock = $policy['safety_stock'] ?? 0;

// Calculate available
$available = max(0, $totalQuantity - $reserved - $safetyStock);
```

### Updating Square Inventory

```php
$response = $client->updateInventory(
    $variationId,      // Square variation ID
    $locationId,       // Square location ID
    (int) $available   // New quantity
);
```

### Inventory Adjustment Types

Square supports different adjustment types:
- `PHYSICAL_COUNT` - Set absolute quantity (used by this integration)
- `ADJUSTMENT` - Relative change (+/-)
- `TRANSFER` - Move between locations

## Order Processing

### Fetching Orders from Square

```php
// Fetch orders (default: last 7 days)
FetchSquareOrders::dispatch($channel);

// Fetch orders from specific date
FetchSquareOrders::dispatch($channel, '2025-01-01T00:00:00Z');
```

### Order Search Query

```php
$query = [
    'location_ids' => [$locationId],
    'query' => [
        'filter' => [
            'state_filter' => [
                'states' => ['COMPLETED'],
            ],
            'date_time_filter' => [
                'created_at' => [
                    'start_at' => $beginTime,
                ],
            ],
        ],
        'sort' => [
            'sort_field' => 'CREATED_AT',
            'sort_order' => 'DESC',
        ],
    ],
    'limit' => 100,
];
```

### Processing Orders

Each order is processed as follows:

1. **Create ChannelOrder**
   ```php
   $order = ChannelOrder::updateOrCreate([
       'channel_id' => $channel->id,
       'channel_order_id' => $orderId,
   ], [
       'shop_id' => $channel->shop_id,
       'order_number' => $referenceId,
       'financial_status' => 'paid',
       'fulfillment_status' => 'unfulfilled',
       'total_price' => $totalPrice,
       'customer_name' => $customerName,
       'placed_at' => $createdAt,
       'raw_data' => $orderData,
   ]);
   ```

2. **Process Line Items**
   ```php
   foreach ($lineItems as $lineItem) {
       // Find variant by Square variation ID
       $variant = ProductVariant::whereJsonContains(
           'sync_metadata->square_variation_id',
           $catalogObjectId
       )->first();

       // Create order item
       ChannelOrderItem::updateOrCreate([...]);
   }
   ```

3. **Update Local Inventory** (Critical!)
   ```php
   foreach ($order->items as $item) {
       if ($item->product_variant_id) {
           $stockItem = $variant->stockItems()->first();
           $newQuantity = max(0, $stockItem->quantity - $item->quantity);
           $stockItem->update(['quantity' => $newQuantity]);
       }
   }
   ```

### Customer Name Extraction

The job tries multiple methods to extract customer information:

1. From fulfillment shipment recipient
2. From tender customer ID
3. Default to "Walk-in Customer"

```php
protected function extractCustomerName(array $orderData): string
{
    // Try fulfillments
    foreach ($fulfillments as $fulfillment) {
        if (!empty($recipient['display_name'])) {
            return $recipient['display_name'];
        }
    }

    // Try tenders
    foreach ($tenders as $tender) {
        if (!empty($tender['customer_id'])) {
            return 'Customer ' . substr($tender['customer_id'], 0, 8);
        }
    }

    return 'Walk-in Customer';
}
```

## Webhooks

### Webhook Events

The integration handles the following Square webhook events:

#### payment.created / payment.updated
Triggered when a payment is completed or updated.

```php
protected function handlePaymentCreated(array $data): void
{
    $locationId = $payment['location_id'];
    $channel = Channel::where('type', 'square')
        ->whereJsonContains('auth_json->location_id', $locationId)
        ->first();

    if ($channel) {
        FetchSquareOrders::dispatch($channel);
    }
}
```

#### order.created / order.updated
Triggered when an order is created or updated.

```php
protected function handleOrderEvent(array $data): void
{
    // Similar to payment events
    // Fetches latest orders
}
```

#### inventory.count.updated
Triggered when inventory changes in Square.

```php
protected function handleInventoryUpdated(array $data): void
{
    foreach ($inventoryCount as $count) {
        // Find variant by catalog_object_id
        // Update local stock if different
        $stockItem->update(['quantity' => $newQuantity]);
    }
}
```

#### catalog.version.updated
Triggered when catalog is modified in Square.

```php
protected function handleCatalogUpdated(array $data): void
{
    // Log the event
    // Optionally trigger re-sync
}
```

### Webhook Security

Webhooks are secured using HMAC SHA-256 signature verification:

```php
protected function verifySignature(Request $request): bool
{
    $signature = $request->header('X-Square-Signature');
    $body = $request->getContent();
    $url = $request->url();

    $stringToSign = $url . $body;
    $expectedSignature = base64_encode(
        hash_hmac('sha256', $stringToSign, $signatureKey, true)
    );

    return hash_equals($expectedSignature, $signature);
}
```

### Setting Up Webhooks

1. Get webhook signature key from Square Developer Dashboard
2. Add to `.env`: `SQUARE_WEBHOOK_SIGNATURE_KEY=your_key`
3. Configure webhook URL: `https://your-domain.com/api/square/webhook`
4. Subscribe to events in Square Dashboard

## API Reference

### SquareClient Methods

#### Constructor
```php
public function __construct(Channel $channel)
```

#### Authentication
```php
public function isTokenExpired(): bool
public function refreshAccessToken(): array
```

#### Locations
```php
public function getLocations(): array
public function getLocation(string $locationId): array
public function getConfiguredLocation(): ?string
```

#### Catalog
```php
public function buildCatalogItem(array $productData): array
public function createCatalogObject(array $catalogObject): array
public function updateCatalogObject(string $objectId, array $catalogObject): array
public function searchCatalogObjects(array $query): array
```

#### Inventory
```php
public function updateInventory(
    string $catalogObjectId,
    string $locationId,
    int $quantity
): array
```

#### Orders
```php
public function searchOrders(array $query): array
public function getOrder(string $orderId): array
```

#### Payments
```php
public function createPayment(array $paymentData): array
public function getPayment(string $paymentId): array
```

#### Testing
```php
public function testConnection(): array
```

### API Endpoints

#### Authentication
```
GET  /api/square/authorize                    # Start OAuth flow
GET  /api/square/callback                     # OAuth callback
POST /api/square/channels/{channel}/disconnect # Disconnect account
POST /api/square/channels/{channel}/test      # Test connection
```

#### Sync Operations
```
POST /api/square/channels/{channel}/sync-catalog    # Sync products
POST /api/square/channels/{channel}/sync-inventory  # Sync inventory
POST /api/square/channels/{channel}/fetch-orders    # Fetch orders
```

#### Location Management
```
GET  /api/square/channels/{channel}/locations # Get available locations
```

#### Webhooks
```
POST /api/square/webhook                      # Webhook receiver
```

## Database Schema

### Channel Table

```php
'auth_json' => [
    'access_token' => 'string',      // Square OAuth access token
    'refresh_token' => 'string',     // OAuth refresh token
    'expires_at' => int,             // Token expiration timestamp
    'merchant_id' => 'string',       // Square merchant ID
    'location_id' => 'string',       // Active Square location ID
    'location_name' => 'string',     // Location display name
    'currency' => 'string',          // Currency code (USD, etc)
    'country' => 'string',           // Country code (US, etc)
    'connected_at' => 'string',      // ISO 8601 connection timestamp
]
```

### ProductVariant sync_metadata

```php
'sync_metadata' => [
    'square_item_id' => 'string',        // Catalog item ID
    'square_variation_id' => 'string',   // Catalog variation ID
]
```

### ChannelOrder

Standard ChannelOrder structure with Square-specific raw_data:

```php
'raw_data' => [
    'id' => 'string',                    // Square order ID
    'reference_id' => 'string',          // Order reference/number
    'state' => 'COMPLETED',              // Order state
    'total_money' => [...],              // Total amount
    'line_items' => [...],               // Order line items
    'tenders' => [...],                  // Payment methods
    'fulfillments' => [...],             // Fulfillment details
]
```

## Troubleshooting

### Common Issues

#### 1. OAuth Flow Fails

**Symptoms**: Redirect to Square fails or callback returns error

**Solutions**:
- Verify `SQUARE_APPLICATION_ID` and `SQUARE_APPLICATION_SECRET` are correct
- Check that redirect URI in `.env` matches the one configured in Square Dashboard
- Ensure you're using the correct environment (sandbox vs production)
- Check that CSRF state matches (clear browser cookies if needed)

#### 2. Token Expired

**Symptoms**: API requests return 401 Unauthorized

**Solutions**:
- The client automatically refreshes tokens
- If refresh fails, disconnect and reconnect the channel
- Verify `SQUARE_APPLICATION_SECRET` is correct
- Check that `refresh_token` is stored in channel auth_json

#### 3. Catalog Sync Fails

**Symptoms**: Products don't appear in Square catalog

**Solutions**:
- Verify product has at least one variant with a SKU
- Check that prices are valid (positive numbers)
- Ensure `ITEMS_WRITE` permission is granted
- Review logs for specific Square API errors
- Verify location_id is set in channel auth_json

#### 4. Inventory Not Syncing

**Symptoms**: Square shows wrong inventory counts

**Solutions**:
- Check that variant has `square_variation_id` in sync_metadata
- Verify channel policy includes the correct locations
- Ensure `INVENTORY_WRITE` permission is granted
- Check safety stock and reserved quantities
- Manually trigger inventory sync via API endpoint

#### 5. Orders Not Importing

**Symptoms**: Square sales don't appear in system

**Solutions**:
- Verify orders are in COMPLETED state in Square
- Check date range (default is last 7 days)
- Ensure `ORDERS_READ` permission is granted
- Set up webhooks for real-time order import
- Manually trigger order fetch via API endpoint

#### 6. Webhook Not Working

**Symptoms**: Real-time updates not happening

**Solutions**:
- Verify webhook URL is publicly accessible (use ngrok for local testing)
- Check `SQUARE_WEBHOOK_SIGNATURE_KEY` is configured correctly
- Review webhook signature verification logic
- Check Square Developer Dashboard for webhook delivery status
- Ensure webhook URL uses HTTPS (required for production)

#### 7. Inventory Decreases Twice

**Symptoms**: Selling through Square decreases inventory by 2x the quantity

**Solutions**:
- This usually happens if webhooks trigger a fetch AND a scheduled job runs
- Ensure `updateOrCreate` is used (not `create`) for idempotency
- Check that the same order isn't processed twice
- Review order processing logic in `FetchSquareOrders`

### Debug Mode

Enable detailed logging:

```php
// In SquareClient.php
Log::debug('Square API Request', [
    'method' => $method,
    'uri' => $uri,
    'options' => $options,
]);

Log::debug('Square API Response', [
    'status' => $response->getStatusCode(),
    'body' => $responseData,
]);
```

### Testing with Square Sandbox

Use Square's sandbox environment for testing:

1. Set `SQUARE_SANDBOX=true` in `.env`
2. Use sandbox application credentials
3. Create test products and inventory in Square sandbox
4. Use Square's testing tools to simulate payments

### Logs to Check

Monitor these log channels:
- `storage/logs/laravel.log` - General application logs
- Search for "Square" to find integration-specific logs
- Check for job failures in queue workers
- Review webhook delivery attempts in Square Dashboard

## Best Practices

### 1. Sync Frequency

- **Catalog**: Sync when products are created/updated
- **Inventory**: Sync in real-time or every 15 minutes
- **Orders**: Use webhooks for real-time + scheduled fetch as backup

### 2. Error Handling

- Always wrap Square API calls in try-catch blocks
- Log all errors with context
- Retry failed jobs with exponential backoff
- Alert on repeated failures

### 3. Performance

- Use queued jobs for all sync operations
- Batch catalog syncs when possible
- Avoid syncing unchanged data
- Use webhooks instead of polling

### 4. Data Integrity

- Use `updateOrCreate` for idempotency
- Store Square IDs in sync_metadata
- Keep raw Square data in raw_data column
- Validate data before sending to Square

### 5. Security

- Never log access tokens
- Verify webhook signatures
- Use HTTPS for all API calls
- Rotate tokens regularly (automatic with OAuth)

## Future Enhancements

Potential improvements for this integration:

1. **Customer Sync**: Sync customer information to/from Square
2. **Multiple Locations**: Support for multiple Square locations per shop
3. **Custom Attributes**: Map custom attributes to Square item variations
4. **Loyalty Integration**: Integrate with Square Loyalty
5. **Gift Cards**: Support for Square gift cards
6. **Discounts**: Sync discounts and promotions
7. **Taxes**: Automatic tax calculation using Square Tax API
8. **Terminal API**: Direct integration with Square terminals
9. **Reporting**: Advanced sales analytics from Square data
10. **Batch Operations**: Bulk catalog and inventory updates

## Support

For issues or questions:

1. Check this documentation
2. Review Laravel logs
3. Check Square Developer Dashboard
4. Contact Square Support for Square-specific issues
5. File an issue in the project repository

## Resources

- [Square API Documentation](https://developer.squareup.com/docs)
- [Square OAuth Guide](https://developer.squareup.com/docs/oauth-api/overview)
- [Square Catalog API](https://developer.squareup.com/docs/catalog-api/what-it-does)
- [Square Inventory API](https://developer.squareup.com/docs/inventory-api/what-it-does)
- [Square Orders API](https://developer.squareup.com/docs/orders-api/what-it-does)
- [Square Webhooks](https://developer.squareup.com/docs/webhooks/overview)

---

**Last Updated**: November 15, 2025
**Integration Version**: 1.0.0
**Square API Version**: 2024-10-17
