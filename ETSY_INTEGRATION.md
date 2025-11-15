# Etsy Integration Guide

## Overview

This guide covers the complete integration with Etsy, allowing you to list products, manage inventory, process orders, and sync data between your shop and Etsy.

## Features

- ✅ OAuth 2.0 authentication with automatic token refresh
- ✅ Product listing creation and updates
- ✅ Real-time inventory synchronization
- ✅ Price and quantity updates
- ✅ Order management (fetch receipts and transactions)
- ✅ Image upload support (up to 10 images per listing)
- ✅ Category/taxonomy support
- ✅ Listing variations and options
- ✅ Error handling and retry logic
- ✅ Multi-shop support

## Prerequisites

### 1. Etsy Shop

You must have an active Etsy shop:

1. Go to [Etsy](https://www.etsy.com/)
2. Sign up or sign in
3. Create your shop if you haven't already
4. Ensure your shop is active and in good standing

### 2. Etsy Developer Account and App

1. Go to [Etsy Developers](https://www.etsy.com/developers/)
2. Sign in with your Etsy account
3. Click **"Create a new app"**
4. Fill in the app details:
   - **App Name**: Your app name (e.g., "My Multichannel Manager")
   - **App Description**: Brief description of your integration
   - **Redirect URI**: `https://yourdomain.com/api/etsy/callback`
   - **Scopes**: Select required permissions (see below)
5. Submit and get your **Client ID** (Keystring)

### 3. Required OAuth Scopes

When creating your Etsy app, select these scopes:

- `listings_r` - Read your listings
- `listings_w` - Write/update your listings
- `transactions_r` - Read your orders
- `shops_r` - Read your shop information

### 4. Environment Setup

Add Etsy credentials to `.env`:

```env
# Etsy Configuration
ETSY_CLIENT_ID=your_client_id_here
ETSY_REDIRECT_URI=https://yourdomain.com/api/etsy/callback
ETSY_SCOPES="listings_r listings_w transactions_r shops_r"
```

**Note**: Etsy uses OAuth 2.0 with PKCE. You don't need a client secret for public apps, but the client ID is required.

## Setup & Connection

### Via Frontend

1. Navigate to **Channels** page
2. Click **Connect Etsy**
3. Click **"Connect to Etsy"** button
4. You'll be redirected to Etsy
5. Log in to your Etsy account (if not already)
6. Authorize the requested permissions
7. You'll be redirected back and your shop will be connected

The system will:
- Complete OAuth flow and get access token
- Fetch your shop information
- Store refresh token for automatic renewal
- Fetch Etsy category taxonomy
- Enable product syncing

### Via API

```bash
# Initiate OAuth flow
GET /api/etsy/authorize
# User will be redirected to Etsy for authorization
# Etsy will redirect back to: /api/etsy/callback?code=xxx&state=xxx
```

## Product Listing

### How It Works

Etsy listings are created directly via API (not feeds like Walmart):

1. Product data is sent to Etsy API
2. Listing is created immediately
3. Images are uploaded separately
4. Listing goes live or draft based on settings

### Listing a Product

```php
use App\Jobs\Etsy\SyncProductToEtsy;

// Dispatch job to sync product
SyncProductToEtsy::dispatch($product, $etsyChannel);
```

### Required Product Data

For Etsy listings, ensure your products have:

- **SKU** (unique identifier)
- **Title** (max 140 characters)
- **Description**
- **Price**
- **Quantity**
- **Category** (must match Etsy taxonomy)
- **Images** (at least one, up to 10)
- **Who Made** (i_did, someone_else, collective)
- **When Made** (e.g., 2020_2024, made_to_order)

### Creating a Listing Example

```php
use App\Services\Etsy\EtsyClient;

$client = new EtsyClient($etsyChannel);
$shopId = $etsyChannel->auth_json['shop_id'];

$listingData = [
    'quantity' => 10,
    'title' => 'Handmade Ceramic Mug',
    'description' => 'Beautiful handcrafted ceramic mug...',
    'price' => 29.99,
    'who_made' => 'i_did',
    'when_made' => '2020_2024',
    'taxonomy_id' => 1234, // Etsy category ID
    'shipping_profile_id' => 12345678, // Your shipping profile
    'return_policy_id' => 12345678, // Your return policy
    'materials' => ['ceramic', 'glaze'],
    'tags' => ['mug', 'handmade', 'ceramic', 'coffee'],
    'processing_min' => 1,
    'processing_max' => 3,
    'is_taxable' => true,
    'should_auto_renew' => true,
    'type' => 'physical',
];

$response = $client->createListing($shopId, $listingData);
$listingId = $response['listing_id'];
```

## Inventory Management

### Sync Inventory to Etsy

```php
use App\Jobs\Etsy\SyncInventoryToEtsy;

// Sync inventory for a variant
SyncInventoryToEtsy::dispatch($variant, $etsyChannel);
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

### Update Quantity Example

```php
use App\Services\Etsy\EtsyClient;

$client = new EtsyClient($etsyChannel);
$listingId = 123456789;
$quantity = 50;

$response = $client->updateListingQuantity($listingId, $quantity);
```

## Price Management

### Update Prices

```php
use App\Services\Etsy\EtsyClient;

$client = new EtsyClient($etsyChannel);
$listingId = 123456789;
$price = 34.99;

$response = $client->updateListingPrice($listingId, $price);
```

**Note**: Etsy prices are in the shop's currency. Make sure to convert if needed.

## Image Management

### Upload Images

```php
use App\Services\Etsy\EtsyClient;

$client = new EtsyClient($etsyChannel);
$shopId = 12345678;
$listingId = 123456789;
$imageUrl = 'https://yourdomain.com/images/product.jpg';

$response = $client->uploadListingImage($shopId, $listingId, $imageUrl);
```

### Image Requirements

- **Format**: JPG, PNG, GIF
- **Size**: Minimum 2000px on the shortest side (recommended)
- **Max File Size**: 10MB per image
- **Max Images**: 10 per listing
- **First Image**: Becomes the primary listing image

## Order Management

### Fetch Orders

```php
use App\Jobs\Etsy\FetchEtsyOrders;

// Fetch orders from last 30 days
FetchEtsyOrders::dispatch($etsyChannel);

// Fetch orders from specific timestamp
FetchEtsyOrders::dispatch($etsyChannel, now()->subDays(7)->timestamp);
```

### Order Structure

Etsy orders consist of:

- **Receipt**: The order container (one per transaction)
- **Transactions**: Line items within the receipt
- **Buyer**: Customer information
- **Shipping**: Address and tracking info

### Get Receipt Example

```php
use App\Services\Etsy\EtsyClient;

$client = new EtsyClient($etsyChannel);
$shopId = 12345678;
$receiptId = 987654321;

$receipt = $client->getReceipt($shopId, $receiptId);
$transactions = $client->getReceiptTransactions($shopId, $receiptId);
```

### Mark Order as Shipped

```php
use App\Services\Etsy\EtsyClient;

$client = new EtsyClient($etsyChannel);
$shopId = 12345678;
$receiptId = 987654321;

$shipmentData = [
    'tracking_code' => '1Z999AA10123456784',
    'carrier_name' => 'USPS',
    'send_bcc' => true, // Send shipping notification
];

$response = $client->createReceiptShipment($shopId, $receiptId, $shipmentData);
```

## Category Taxonomy

### Fetch Categories

```php
use App\Services\Etsy\EtsyClient;

$client = new EtsyClient($etsyChannel);
$taxonomy = $client->getSellerTaxonomy();

foreach ($taxonomy['results'] as $node) {
    echo $node['id'] . ': ' . $node['name'] . "\n";
}
```

### Get Category Attributes

```php
$taxonomyId = 1234;
$properties = $client->getTaxonomyNodeProperties($taxonomyId);

foreach ($properties['results'] as $property) {
    echo $property['name'] . ' (Required: ' . ($property['is_required'] ? 'Yes' : 'No') . ")\n";
}
```

### Category Selection

When listing products, select the most specific category that matches your product. The AI mapping feature can help suggest appropriate categories.

## Listing Variations

### With Variations

If your product has variations (e.g., size, color):

```php
$inventoryData = [
    'products' => [
        [
            'sku' => 'MUG-RED-SM',
            'offerings' => [
                [
                    'price' => 29.99,
                    'quantity' => 10,
                    'is_enabled' => true,
                ],
            ],
            'property_values' => [
                ['property_id' => 200, 'values' => ['Red']], // Color
                ['property_id' => 100, 'values' => ['Small']], // Size
            ],
        ],
        [
            'sku' => 'MUG-RED-LG',
            'offerings' => [
                [
                    'price' => 34.99,
                    'quantity' => 15,
                    'is_enabled' => true,
                ],
            ],
            'property_values' => [
                ['property_id' => 200, 'values' => ['Red']],
                ['property_id' => 100, 'values' => ['Large']],
            ],
        ],
    ],
];

$client->updateListingInventory($listingId, $inventoryData);
```

## API Client Usage

### Initialize Client

```php
use App\Services\Etsy\EtsyClient;

$client = new EtsyClient($etsyChannel);
```

### Available Methods

```php
// Shop
$shop = $client->getShop($shopId);
$shops = $client->getUserShops($userId);

// Listings
$listings = $client->getShopListings($shopId, ['limit' => 50]);
$listing = $client->getListing($listingId);
$result = $client->createListing($shopId, $listingData);
$result = $client->updateListing($listingId, $listingData);
$result = $client->deleteListing($listingId);

// Inventory
$inventory = $client->getListingInventory($listingId);
$result = $client->updateListingInventory($listingId, $inventoryData);
$result = $client->updateListingQuantity($listingId, 100);
$result = $client->updateListingPrice($listingId, 29.99);

// Images
$result = $client->uploadListingImage($shopId, $listingId, $imageUrl);

// Orders
$receipts = $client->getShopReceipts($shopId, ['limit' => 100]);
$receipt = $client->getReceipt($shopId, $receiptId);
$transactions = $client->getReceiptTransactions($shopId, $receiptId);
$result = $client->createReceiptShipment($shopId, $receiptId, $shipmentData);

// Taxonomy
$taxonomy = $client->getSellerTaxonomy();
$properties = $client->getTaxonomyNodeProperties($taxonomyId);
$carriers = $client->getShippingCarriers('US');

// Helper
$listingData = $client->buildListingData($productData);
```

## Authentication

Etsy uses OAuth 2.0 authorization code flow:

1. **Redirect to Etsy**: User authorizes app
2. **Authorization Code**: Etsy redirects back with code
3. **Exchange for Token**: Exchange code for access/refresh tokens
4. **Access Token**: Valid for 3600 seconds (1 hour)
5. **Refresh Token**: Used to get new access token
6. **Auto-Refresh**: Tokens automatically refreshed when expired

### Token Storage

Tokens are stored in `channels.auth_json`:

```json
{
  "access_token": "xxx",
  "refresh_token": "xxx",
  "expires_at": 1234567890,
  "token_type": "Bearer",
  "shop_id": 12345678,
  "shop_name": "MyEtsyShop",
  "user_id": 98765432
}
```

## Best Practices

### 1. Product Data Quality

- **Titles**: Clear, descriptive, keyword-rich (max 140 chars)
- **Images**: High-resolution (2000px+), well-lit, multiple angles
- **Descriptions**: Detailed, include materials, dimensions, care instructions
- **Tags**: Use all 13 tag slots with relevant keywords
- **Categories**: Choose the most specific category available

### 2. Listing Optimization

- Use the `buildListingData()` helper for consistent formatting
- Always provide processing times (min and max)
- Set shipping and return policies before listing
- Use variations for products with multiple options
- Enable auto-renew for continuous listings

### 3. Inventory Management

- Sync inventory regularly (hourly recommended)
- Use safety stock to prevent overselling
- Monitor reserved quantities
- Update quantities immediately after sales

### 4. Order Processing

- Fetch orders at least hourly
- Mark orders as shipped promptly with tracking
- Provide accurate carrier information
- Keep customers informed via Etsy messaging

### 5. Rate Limiting

Etsy has rate limits:
- **10 requests/second** (burst)
- **10,000 requests/day** (default)

The client includes automatic retry logic with exponential backoff.

## Troubleshooting

### Connection Issues

**Error: "Failed to connect Etsy shop"**

- Verify your Etsy app is approved and active
- Check redirect URI matches exactly (including https)
- Ensure you selected all required scopes
- Try disconnecting and reconnecting
- Check Etsy API status page

### Listing Errors

**Error: "Invalid taxonomy_id"**

- Taxonomy ID must be from Etsy's current taxonomy
- Fetch latest taxonomy via `getSellerTaxonomy()`
- Use the most specific (leaf) category

**Error: "Missing required property"**

- Different categories require different attributes
- Fetch category attributes via `getTaxonomyNodeProperties()`
- Ensure all required properties are provided

**Error: "Title too long"**

- Etsy titles max 140 characters
- Use `substr($title, 0, 140)` to truncate

### Image Upload Errors

**Error: "Invalid image URL"**

- Image must be publicly accessible via HTTPS
- File size must be under 10MB
- Format must be JPG, PNG, or GIF
- Ensure URL doesn't require authentication

### Order Issues

**Orders not appearing**

- Check date range in fetch request
- Verify shop ID is correct
- Ensure orders exist in Etsy Seller Dashboard
- Check channel connection status

## Shipping Profiles

### Create Shipping Profile (via Etsy)

Shipping profiles must be created in your Etsy shop settings:

1. Go to [Etsy Shop Manager](https://www.etsy.com/your/shops/me)
2. Click **Settings** → **Shipping settings**
3. Create shipping profiles
4. Note the profile IDs for use in listings

### Get Shipping Carriers

```php
$carriers = $client->getShippingCarriers('US');
```

Supported carriers include:
- USPS
- FedEx
- UPS
- DHL
- Canada Post
- And more

## Return Policies

### Create Return Policy (via Etsy)

Like shipping profiles, return policies are created in Etsy:

1. Go to **Shop Manager** → **Settings**
2. Click **Policies**
3. Create return policy
4. Note the policy ID for use in listings

## Advanced Features

### Personalization

Enable personalization on listings:

```php
$listingData = [
    // ... other fields
    'is_personalizable' => true,
    'personalization_is_required' => false,
    'personalization_char_count_max' => 50,
    'personalization_instructions' => 'Please enter your custom text here',
];
```

### Digital Products

For digital downloads:

```php
$listingData = [
    // ... other fields
    'type' => 'download', // instead of 'physical'
    // No shipping required for downloads
];
```

### Production Partners

If using production partners:

```php
$listingData = [
    // ... other fields
    'production_partner_ids' => [12345678],
];
```

## Scheduled Jobs

Set up cron jobs for automation:

```php
// In routes/console.php or App\Console\Kernel

// Fetch orders hourly
$schedule->job(new FetchEtsyOrders($etsyChannel))->hourly();

// Sync inventory every 2 hours
$schedule->call(function () {
    $etsyChannels = Channel::where('type', 'etsy')
        ->where('status', 'connected')
        ->get();

    foreach ($etsyChannels as $channel) {
        foreach (ProductVariant::all() as $variant) {
            SyncInventoryToEtsy::dispatch($variant, $channel);
        }
    }
})->everyTwoHours();
```

## Security

1. **OAuth Tokens**: Stored encrypted in database
2. **HTTPS Only**: All API calls use HTTPS
3. **Token Refresh**: Automatic refresh prevents exposure
4. **State Parameter**: CSRF protection in OAuth flow
5. **Logging**: Sensitive data excluded from logs
6. **Permissions**: Limit who can connect/disconnect channels

## Support Resources

- [Etsy Developer Portal](https://www.etsy.com/developers/)
- [Etsy API Documentation](https://developers.etsy.com/)
- [Etsy Seller Handbook](https://www.etsy.com/seller-handbook)
- [Etsy Forums](https://community.etsy.com/)
- [Etsy Support](https://help.etsy.com/hc/en-us/requests/new)

## Compliance

### Requirements

- Valid shop in good standing
- Compliance with [Etsy Seller Policy](https://www.etsy.com/legal/sellers/)
- Accurate product descriptions
- Appropriate categorization
- Honest shipping times

### Prohibited Items

Do not list:
- Prohibited items per Etsy policy
- Items that violate intellectual property
- Recalled or unsafe products
- Items prohibited by law
- Mass-manufactured items (Etsy is for handmade/vintage)

## Performance Optimization

### Batch Operations

While Etsy doesn't have bulk endpoints like Walmart, you can:

```php
// Queue multiple product syncs
foreach ($products as $product) {
    SyncProductToEtsy::dispatch($product, $etsyChannel);
}

// Laravel queues will process them efficiently
```

### Caching

- Taxonomy cached for 24 hours
- Access tokens cached until expiry
- Shop info cached for 1 hour

### Queue Processing

All Etsy sync jobs use Laravel queues:

```bash
# Process queue worker
php artisan queue:work --queue=default --tries=3
```

## Differences from Other Marketplaces

### vs Walmart

- **Authentication**: OAuth 2.0 vs Client Credentials
- **Listing Method**: Direct API vs XML feeds
- **Processing**: Synchronous vs Feed status checks
- **Categories**: More granular taxonomy
- **Handmade Focus**: Products should be handmade or vintage

### vs Amazon

- **Scale**: Smaller, niche marketplace
- **Fees**: Lower fees, no monthly subscription
- **Branding**: More focus on artisan/handmade
- **Personalization**: Built-in personalization support

### vs eBay

- **Format**: Fixed-price only (no auctions)
- **Renewals**: Automatic listing renewals
- **Categories**: Curated for handmade/vintage
- **Community**: Strong seller community

## License

This Etsy integration is part of the Multichannel Sales & Inventory Manager and follows the same license terms.
