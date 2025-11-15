# Walmart Marketplace Integration Guide

## Overview

This guide covers the complete integration with Walmart Marketplace, allowing you to list products, manage inventory, process orders, and sync data between your Shopify store and Walmart.

## Features

- ✅ OAuth 2.0 Client Credentials authentication
- ✅ Product listing and updates via XML feeds
- ✅ Real-time inventory synchronization
- ✅ Price updates
- ✅ Order management (fetch, acknowledge, ship)
- ✅ Category/taxonomy support
- ✅ Sandbox and Production environment support
- ✅ Feed status tracking
- ✅ Error handling and retry logic

## Prerequisites

### 1. Walmart Marketplace Seller Account

You must have an approved Walmart Marketplace seller account:

1. Go to [Walmart Seller Center](https://seller.walmart.com/)
2. Sign up or sign in
3. Complete seller onboarding process
4. Get approved as a Walmart Marketplace seller

### 2. API Credentials

Once approved:

1. Go to [Walmart Developer Portal](https://developer.walmart.com/)
2. Sign in with your Walmart Seller account
3. Navigate to **API Credentials**
4. Generate or copy your:
   - **Client ID**
   - **Client Secret**

### 3. Environment Setup

Add Walmart credentials to `.env`:

```env
# Walmart Configuration
WALMART_CLIENT_ID=your_client_id_here
WALMART_CLIENT_SECRET=your_client_secret_here
WALMART_SANDBOX=true  # Set to false for production
```

## Setup & Connection

### Via Frontend

1. Navigate to **Channels** page
2. Click **Connect Walmart**
3. Enter your API credentials:
   - Client ID
   - Client Secret
   - Choose environment (Sandbox/Production)
4. Click **Connect Walmart**

The system will:
- Validate your credentials
- Create a connected channel
- Fetch Walmart category taxonomy
- Enable product syncing

### Via API

```bash
POST /api/walmart/connect
Content-Type: application/json

{
  "name": "Walmart US",
  "client_id": "your_client_id",
  "client_secret": "your_client_secret"
}
```

## Product Listing

### How It Works

Walmart uses an XML-based feed system for product listings:

1. Products are submitted as XML feeds
2. Walmart processes the feed asynchronously
3. You can check feed status to see results
4. Products appear on Walmart after approval

### Listing a Product

```php
use App\Jobs\Walmart\SyncProductToWalmart;

// Dispatch job to sync product
SyncProductToWalmart::dispatch($product, $walmartChannel);
```

### Required Product Data

For Walmart listings, ensure your products have:

- **SKU** (unique identifier)
- **Title** (max 75 characters for best results)
- **Description**
- **Price**
- **GTIN/UPC** (required for most categories)
- **Brand**
- **Images** (at least one high-quality image)
- **Category** (must match Walmart taxonomy)

### Product Feed XML Example

```xml
<?xml version="1.0" encoding="UTF-8"?>
<ItemFeed xmlns="http://walmart.com/">
    <ItemFeedHeader>
        <version>1.4</version>
    </ItemFeedHeader>
    <Item>
        <sku>SKU12345</sku>
        <productId>
            <productIdType>GTIN</productIdType>
            <productIdValue>012345678905</productIdValue>
        </productId>
        <productName>Product Title</productName>
        <shortDescription>Product description</shortDescription>
        <price>29.99</price>
        <brand>Brand Name</brand>
        <mainImageUrl>https://example.com/image.jpg</mainImageUrl>
    </Item>
</ItemFeed>
```

## Inventory Management

### Sync Inventory to Walmart

```php
use App\Jobs\Walmart\SyncInventoryToWalmart;

// Sync inventory for a variant
SyncInventoryToWalmart::dispatch($variant, $walmartChannel);
```

### Inventory Calculation

The system calculates available inventory based on:

1. **Channel Policy**: Which warehouses to include
2. **Stock Levels**: Total quantity in included locations
3. **Reserved Quantities**: Subtract reserved stock
4. **Safety Stock**: Minimum buffer quantity

```php
Available = (Total - Reserved - Safety Stock)
```

### Inventory Feed XML Example

```xml
<?xml version="1.0" encoding="UTF-8"?>
<InventoryFeed xmlns="http://walmart.com/">
    <InventoryHeader>
        <version>1.4</version>
    </InventoryHeader>
    <inventory>
        <sku>SKU12345</sku>
        <quantity>
            <unit>EACH</unit>
            <amount>100</amount>
        </quantity>
    </inventory>
</InventoryFeed>
```

## Price Management

### Update Prices

```php
$walmartClient = new \App\Services\Walmart\WalmartClient($channel);

// Update price
$walmartClient->updatePrice(
    sku: 'SKU12345',
    price: 29.99,
    compareAtPrice: 39.99  // Optional strikethrough price
);
```

### Price Feed XML Example

```xml
<?xml version="1.0" encoding="UTF-8"?>
<PriceFeed xmlns="http://walmart.com/">
    <PriceHeader>
        <version>1.5</version>
    </PriceHeader>
    <Price>
        <itemIdentifier>
            <sku>SKU12345</sku>
        </itemIdentifier>
        <pricingList>
            <pricing>
                <currentPrice>
                    <value currency="USD" amount="29.99"/>
                </currentPrice>
                <comparisonPrice>
                    <value currency="USD" amount="39.99"/>
                </comparisonPrice>
            </pricing>
        </pricingList>
    </Price>
</PriceFeed>
```

## Order Management

### Fetch Orders

```php
use App\Jobs\Walmart\FetchWalmartOrders;

// Fetch orders from last 7 days
FetchWalmartOrders::dispatch($walmartChannel);

// Fetch orders from specific date
FetchWalmartOrders::dispatch($walmartChannel, '2024-01-01T00:00:00Z');
```

### Order Workflow

1. **Fetch Orders**: Pull new orders from Walmart
2. **Acknowledge**: Confirm receipt of order
3. **Process**: Pick, pack items
4. **Ship**: Send shipment notification with tracking
5. **Complete**: Order marked as fulfilled

### Acknowledge an Order

```php
$walmartClient = new \App\Services\Walmart\WalmartClient($channel);

$walmartClient->acknowledgeOrder('WM1234567890');
```

### Ship an Order

```php
$shipmentData = [
    'orderLineNumber' => '1',
    'shipDateTime' => now()->toIso8601String(),
    'shipMethod' => 'Standard',
    'trackingInfo' => [
        'shipDateTime' => now()->toIso8601String(),
        'carrierName' => [
            'carrier' => 'USPS',
        ],
        'methodCode' => 'Standard',
        'trackingNumber' => '1Z999AA10123456784',
        'trackingURL' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=1Z999AA10123456784',
    ],
];

$walmartClient->shipOrder('WM1234567890', $shipmentData);
```

### Cancel an Order Line

```php
$cancellationData = [
    'cancellationReason' => 'Out of stock',
];

$walmartClient->cancelOrderLine(
    purchaseOrderId: 'WM1234567890',
    lineNumber: '1',
    cancellationData: $cancellationData
);
```

## Feed Management

### Check Feed Status

After submitting products, inventory, or prices, check feed status:

```php
$walmartClient = new \App\Services\Walmart\WalmartClient($channel);

$status = $walmartClient->getFeedStatus($feedId);

// Feed statuses:
// - RECEIVED: Feed received, processing starting
// - INPROGRESS: Feed being processed
// - PROCESSED: Feed processed successfully
// - ERROR: Feed had errors
```

### Feed Status Response Example

```json
{
  "feedId": "ABC123",
  "feedStatus": "PROCESSED",
  "itemsReceived": 10,
  "itemsSucceeded": 9,
  "itemsFailed": 1,
  "itemDetails": {
    "itemIngestionStatus": [
      {
        "martId": "0",
        "sku": "SKU12345",
        "ingestionStatus": "SUCCESS"
      },
      {
        "martId": "0",
        "sku": "SKU67890",
        "ingestionStatus": "DATA_ERROR",
        "ingestionErrors": {
          "ingestionError": {
            "code": "INVALID_PRICE",
            "description": "Price cannot be negative"
          }
        }
      }
    ]
  }
}
```

## Category Taxonomy

### Fetch Categories

```php
$walmartClient = new \App\Services\Walmart\WalmartClient($channel);

$taxonomy = $walmartClient->getTaxonomy();
```

### Get Category Attributes

```php
$attributes = $walmartClient->getCategoryAttributes('Electronics');
```

### Category Selection

When listing products, you must select the appropriate Walmart category. The AI mapping feature can help automatically suggest the best category based on your product data.

## API Client Usage

### Initialize Client

```php
use App\Services\Walmart\WalmartClient;

$client = new WalmartClient($walmartChannel);
```

### Available Methods

```php
// Items
$items = $client->getItems(['limit' => 50]);
$item = $client->getItem('SKU12345');
$result = $client->bulkItemSetup($itemsArray);

// Inventory
$result = $client->updateInventory('SKU12345', 100);

// Prices
$result = $client->updatePrice('SKU12345', 29.99, 39.99);

// Orders
$orders = $client->getOrders(['limit' => 200]);
$order = $client->getOrder('WM1234567890');
$result = $client->acknowledgeOrder('WM1234567890');
$result = $client->shipOrder('WM1234567890', $shipmentData);
$result = $client->cancelOrderLine('WM1234567890', '1', $cancellationData);

// Feeds
$status = $client->getFeedStatus($feedId);

// Taxonomy
$taxonomy = $client->getTaxonomy();
$attributes = $client->getCategoryAttributes($categoryId);
```

## Authentication

Walmart uses OAuth 2.0 Client Credentials flow:

1. **Client Credentials**: Provided by Walmart
2. **Token Request**: Exchange credentials for access token
3. **Access Token**: Valid for 15 minutes
4. **Token Caching**: Tokens cached for 14 minutes to minimize requests
5. **Auto-Refresh**: Tokens automatically refreshed when expired

### Token Headers

All API requests include:

```
WM_SEC.ACCESS_TOKEN: {access_token}
WM_SVC.NAME: Walmart Marketplace
WM_QOS.CORRELATION_ID: {unique_id}
```

## Best Practices

### 1. Product Data Quality

- **Titles**: Keep under 75 characters for better visibility
- **Images**: Use high-resolution images (1000x1000px minimum)
- **GTINs**: Always provide valid UPC/EAN codes
- **Descriptions**: Detailed, keyword-rich descriptions
- **Attributes**: Fill all relevant category attributes

### 2. Feed Processing

- Check feed status after submission
- Retry failed items after fixing errors
- Don't submit duplicate feeds for same SKU
- Wait for previous feed to process before resubmitting

### 3. Inventory Management

- Sync inventory regularly (hourly recommended)
- Use safety stock to prevent overselling
- Monitor reserved quantities
- Set up low-stock alerts

### 4. Order Processing

- Fetch orders at least hourly
- Acknowledge orders within 24 hours
- Provide tracking numbers when shipping
- Keep customers informed of order status

### 5. Rate Limiting

Walmart has rate limits:
- **5000 requests/day** (default)
- **10 requests/second** (burst)

The client includes automatic retry logic with exponential backoff.

## Troubleshooting

### Connection Issues

**Error: "Failed to get Walmart access token"**

- Verify Client ID and Client Secret are correct
- Check if using correct environment (Sandbox vs Production)
- Ensure Walmart account is active and approved
- Check Walmart API status page

### Feed Errors

**Error: "INVALID_PRICE"**
- Price must be positive
- Price format: numeric with up to 2 decimal places

**Error: "MISSING_REQUIRED_FIELD"**
- Check category requirements
- Ensure all required attributes are provided
- Validate GTIN format

**Error: "INVALID_UPC"**
- UPC must be valid 12-digit code
- Use UPC check digit calculator to verify
- Consider using GTIN-exemption if no UPC available

### Order Issues

**Orders not appearing**
- Check date range in fetch request
- Verify order status filter
- Ensure channel is connected
- Check Walmart Seller Center for orders

## Sandbox vs Production

### Sandbox Environment

- URL: `https://sandbox.walmartapis.com`
- Use for testing without affecting live data
- Limited product catalog
- Test orders don't charge real money
- Separate credentials from production

### Production Environment

- URL: `https://marketplace.walmartapis.com`
- Real orders and real money
- Full product catalog access
- Use production credentials
- Monitor carefully

### Switching Environments

Update `.env`:

```env
# For Sandbox
WALMART_SANDBOX=true

# For Production
WALMART_SANDBOX=false
```

## Scheduled Jobs

Set up cron jobs for automation:

```php
// In routes/console.php or App\Console\Kernel

// Fetch orders hourly
$schedule->job(new FetchWalmartOrders($walmartChannel))->hourly();

// Sync inventory every 4 hours
$schedule->call(function () {
    $walmartChannels = Channel::where('type', 'walmart')
        ->where('status', 'connected')
        ->get();

    foreach ($walmartChannels as $channel) {
        foreach (ProductVariant::all() as $variant) {
            SyncInventoryToWalmart::dispatch($variant, $channel);
        }
    }
})->everyFourHours();
```

## Security

1. **Credentials**: Never commit API credentials to version control
2. **HTTPS**: Always use HTTPS for API calls
3. **Token Storage**: Tokens cached securely, auto-expire
4. **Logging**: Sensitive data excluded from logs
5. **Permissions**: Limit who can connect/disconnect channels

## Support Resources

- [Walmart Developer Portal](https://developer.walmart.com/)
- [Walmart Seller Help](https://sellerhelp.walmart.com/)
- [API Documentation](https://developer.walmart.com/api/us/mp)
- [Seller Center](https://seller.walmart.com/)

## Compliance

### Requirements

- Valid business information
- Tax documentation (W-9 or W-8BEN)
- Bank account for payments
- Compliance with Walmart seller policies
- Product safety certifications (if applicable)

### Prohibited Items

Do not list:
- Recalled products
- Counterfeit goods
- Illegal items
- Items violating intellectual property
- Dangerous goods (without proper certification)

## Performance Optimization

### Batch Operations

Instead of syncing products one at a time:

```php
// Batch product sync
$items = $products->map(function ($product) {
    return [
        'sku' => $product->sku,
        'productName' => $product->title,
        // ... other fields
    ];
})->toArray();

$walmartClient->bulkItemSetup($items);
```

### Caching

- Taxonomy cached for 24 hours
- Access tokens cached for 14 minutes
- Feed statuses cached for 5 minutes

### Queue Processing

All Walmart sync jobs use Laravel queues:

```bash
# Process queue worker
php artisan queue:work --queue=default --tries=3
```

## License

This Walmart integration is part of Shopmata Multichannel and follows the same license terms.
