# ShipStation Integration

This document provides comprehensive information about the ShipStation integration for the multichannel sales and inventory management system.

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Setup](#setup)
5. [Authentication](#authentication)
6. [Order Management](#order-management)
7. [Label Generation](#label-generation)
8. [Shipping Rates](#shipping-rates)
9. [Webhooks](#webhooks)
10. [API Reference](#api-reference)
11. [Database Schema](#database-schema)
12. [Troubleshooting](#troubleshooting)

## Overview

The ShipStation integration enables automated shipping label generation, order fulfillment, and tracking management for multichannel orders. ShipStation is a web-based shipping software that connects to multiple carriers and marketplaces.

### Key Benefits

- **Multi-Carrier Support**: Access to 40+ shipping carriers
- **Automated Label Generation**: Create shipping labels automatically for new orders
- **Rate Shopping**: Compare shipping rates across carriers
- **Tracking Updates**: Automatic tracking number updates via webhooks
- **Fulfillment Automation**: Mark orders as fulfilled when shipped
- **Warehouse Management**: Support for multiple warehouse locations

## Features

### Implemented Features

1. **API Key Authentication**
   - Simple API key/secret authentication
   - No OAuth flow required
   - Test connection functionality

2. **Order Synchronization**
   - Create orders in ShipStation from ChannelOrder
   - Automatic order creation on new orders (optional)
   - Update existing orders
   - Map order data to ShipStation format

3. **Shipping Label Generation**
   - Create labels for orders
   - Support for multiple carriers and services
   - Configurable package types and confirmation levels
   - Label PDF storage
   - International shipping forms
   - Test label mode

4. **Shipping Rates**
   - Get real-time shipping rates
   - Compare rates across carriers
   - Filter by service type

5. **Tracking Management**
   - Sync shipments from ShipStation
   - Automatic tracking number updates
   - Webhook notifications for ship events
   - Update channel orders with tracking info

6. **Warehouse Support**
   - Multi-warehouse configuration
   - Default warehouse selection
   - Per-order warehouse assignment

7. **Automation Options**
   - Auto-create orders in ShipStation
   - Auto-generate labels
   - Auto-fulfill orders

8. **Webhook Support**
   - `SHIP_NOTIFY` - Shipment created/updated
   - `ITEM_ORDER_NOTIFY` - Order created/updated
   - `ITEM_SHIP_NOTIFY` - Item shipped notification

## Architecture

### Components

```
app/
├── Services/ShipStation/
│   └── ShipStationClient.php        # Main API client
├── Http/Controllers/ShipStation/
│   ├── SettingsController.php       # Settings and operations
│   └── WebhookController.php        # Webhook event handler
└── Jobs/ShipStation/
    ├── CreateShipStationOrder.php   # Create order in ShipStation
    ├── CreateShipStationLabel.php   # Generate shipping label
    └── SyncShipStationShipments.php # Sync shipment data
```

### Data Flow

```
ChannelOrder → CreateShipStationOrder → ShipStation Order API
ChannelOrder → CreateShipStationLabel → ShipStation Label API → Label PDF
ShipStation Shipment → Webhook → Update ChannelOrder tracking
```

## Setup

### Prerequisites

1. Active ShipStation account
2. ShipStation API credentials
3. At least one carrier configured in ShipStation

### Getting ShipStation API Credentials

1. **Log in to ShipStation**
   - Go to https://ship.shipstation.com/
   - Log in with your credentials

2. **Navigate to API Settings**
   - Click on your name in the top right
   - Select "Account Settings"
   - Click "API Settings" in the left menu

3. **Generate API Keys**
   - Click "Generate API Keys" button
   - Copy the API Key and API Secret
   - **Important**: Save these securely - the secret is only shown once!

### Configuration

ShipStation credentials are stored per-shop in the `shop.settings.shipstation` JSON field:

```json
{
  "shipstation": {
    "api_key": "your_api_key",
    "api_secret": "your_api_secret",
    "default_carrier": "stamps_com",
    "default_service": "usps_priority_mail",
    "default_package": "package",
    "default_confirmation": "none",
    "default_warehouse_id": 12345,
    "auto_create_orders": false,
    "auto_create_labels": false,
    "auto_fulfill_orders": true
  }
}
```

### Installation

No additional packages required - uses Laravel's GuzzleHttp client.

## Authentication

### API Key Authentication

ShipStation uses Basic HTTP Authentication with API key and secret:

```php
$client = new Client([
    'auth' => [$apiKey, $apiSecret],
]);
```

### Testing Connection

```php
POST /api/shipstation/test-connection

Response:
{
  "success": true,
  "message": "Connection successful",
  "stores": [...]
}
```

## Order Management

### Creating Orders in ShipStation

Orders can be created automatically or manually:

#### Automatic Creation

Enable in settings:
```php
'auto_create_orders' => true
```

Then dispatch the job:
```php
CreateShipStationOrder::dispatch($shop, $order);
```

#### Order Data Mapping

```php
ChannelOrder → ShipStation Order
- order_number → orderNumber
- channel_order_id → orderKey
- placed_at → orderDate, paymentDate
- total_price → amountPaid
- customer_email → customerEmail
- shipping_address → shipTo
- billing_address → billTo
- items → items (line items)
```

### Order Status Mapping

```php
'unfulfilled' → 'awaiting_shipment'
'partial' → 'awaiting_shipment'
'fulfilled' → 'shipped'
default → 'awaiting_payment'
```

### Updating Orders

If an order already exists in ShipStation (matched by `orderNumber`), it will be updated instead of creating a duplicate.

## Label Generation

### Creating a Shipping Label

#### Via API

```php
POST /api/shipstation/create-label

{
  "order_id": 123,
  "carrier_code": "stamps_com",
  "service_code": "usps_priority_mail",
  "package_code": "package",
  "confirmation": "delivery",
  "test_label": false
}
```

#### Via Job

```php
CreateShipStationLabel::dispatch($shop, $order, [
    'carrier_code' => 'fedex',
    'service_code' => 'fedex_ground',
    'package_code' => 'package',
    'confirmation' => 'signature',
]);
```

### Label Data

Label data is stored in `order.fulfillment_data.shipstation`:

```php
[
    'shipment_id' => 123456,
    'tracking_number' => '1Z999AA1...',
    'carrier_code' => 'ups',
    'service_code' => 'ups_ground',
    'label_created_at' => '2025-01-01T12:00:00Z',
    'shipping_cost' => 7.50,
    'insurance_cost' => 0.50,
    'label_path' => 'labels/shipstation/order_123_shipment_456.pdf',
    'form_path' => 'labels/shipstation/order_123_shipment_456_form.pdf',
]
```

### Label PDF Storage

Labels are stored in Laravel storage:

```php
Storage::disk('local')->put($labelPath, $labelPdf);

// Access label:
$labelPdf = Storage::disk('local')->get($labelPath);
```

### Package Codes

Available package types:
- `package` - Standard package
- `letter` - Letter
- `large_envelope_or_flat` - Large envelope/flat
- `thick_envelope` - Thick envelope
- `large_package` - Large package
- `flat_rate_box` - Flat rate box
- `flat_rate_envelope` - Flat rate envelope
- `flat_rate_padded_envelope` - Flat rate padded envelope
- `small_flat_rate_box` - Small flat rate box
- `medium_flat_rate_box` - Medium flat rate box
- `large_flat_rate_box` - Large flat rate box

### Confirmation Types

- `none` - No confirmation
- `delivery` - Delivery confirmation
- `signature` - Signature required
- `adult_signature` - Adult signature required
- `direct_signature` - Direct signature required

### Test Labels

Set `test_label: true` to create a test label (no charge). Test labels display "TEST LABEL" watermark.

## Shipping Rates

### Getting Rates

```php
POST /api/shipstation/rates

{
  "order_id": 123,
  "carrier_code": "stamps_com", // optional
  "service_code": null,          // optional
  "package_code": "package",
  "confirmation": "none"
}

Response:
[
  {
    "serviceName": "USPS Priority Mail",
    "serviceCode": "usps_priority_mail",
    "shipmentCost": 7.50,
    "otherCost": 0.00
  },
  {
    "serviceName": "USPS First Class Mail",
    "serviceCode": "usps_first_class_mail",
    "shipmentCost": 3.50,
    "otherCost": 0.00
  }
]
```

### Rate Shopping

To get all available rates, omit `carrier_code` and `service_code`:

```php
$rates = $client->getRates([
    'toCountry' => 'US',
    'toPostalCode' => '90210',
    'toState' => 'CA',
    'weight' => [
        'value' => 16,
        'units' => 'ounces'
    ],
    'confirmation' => 'delivery',
]);
```

## Webhooks

### Webhook Events

ShipStation sends webhooks for the following events:

#### SHIP_NOTIFY

Triggered when a shipment is created or updated.

```json
{
  "resource_url": "https://ssapi.shipstation.com/shipments/123456",
  "resource_type": "SHIP_NOTIFY"
}
```

The system will:
1. Fetch shipment details
2. Find matching order by `orderNumber`
3. Update order with tracking information
4. Set fulfillment status to `fulfilled`

#### ITEM_ORDER_NOTIFY

Triggered when an order is created or updated in ShipStation.

#### ITEM_SHIP_NOTIFY

Triggered when items in an order are marked as shipped.

### Setting Up Webhooks

1. **Configure Webhook URL**
   - In ShipStation, go to Settings → Integrations → Webhooks
   - Add new webhook: `https://your-domain.com/api/shipstation/webhook`

2. **Subscribe to Events**
   - Check `SHIP_NOTIFY`
   - Check `ITEM_ORDER_NOTIFY` (optional)
   - Check `ITEM_SHIP_NOTIFY` (optional)

3. **Test Webhook**
   - ShipStation provides a "Send Test" button
   - Check your logs for webhook received

### Webhook Security

ShipStation webhooks are not signed. For additional security, you can:
- Use HTTPS
- Implement IP whitelist (ShipStation's IPs)
- Verify resource_url domain matches shipstation.com

## API Reference

### ShipStationClient Methods

#### Constructor
```php
public function __construct(Shop $shop)
```

#### Connection Testing
```php
public function testConnection(): array
```

#### Stores
```php
public function getStores(): array
public function getStore(int $storeId): array
```

#### Carriers & Services
```php
public function getCarriers(): array
public function getServices(string $carrierCode): array
```

#### Orders
```php
public function createOrder(array $orderData): array
public function getOrder(string $orderNumber): ?array
public function getOrders(array $params = []): array
public function buildOrderData(ChannelOrder $order): array
```

#### Shipping
```php
public function createLabel(array $labelData): array
public function createLabelFromRate(array $shipmentData): array
public function getRates(array $rateOptions): array
public function voidLabel(int $shipmentId): array
public function markAsShipped(array $shipmentData): array
```

#### Shipments
```php
public function getShipments(array $params = []): array
```

#### Warehouses
```php
public function getWarehouses(): array
public function getWarehouse(int $warehouseId): array
public function createWarehouse(array $warehouseData): array
```

#### Products
```php
public function getProducts(array $params = []): array
public function updateProduct(int $productId, array $productData): array
```

#### Tags
```php
public function getTags(): array
public function createTag(string $tagName): array
```

#### Account
```php
public function getAccount(): array
```

### API Endpoints

#### Settings
```
GET  /api/shipstation/settings           # Get settings
POST /api/shipstation/credentials        # Update credentials
POST /api/shipstation/settings           # Update settings
POST /api/shipstation/test-connection   # Test connection
POST /api/shipstation/disconnect         # Disconnect
```

#### Data Retrieval
```
GET /api/shipstation/carriers            # Get carriers
GET /api/shipstation/services            # Get services for carrier
GET /api/shipstation/warehouses          # Get warehouses
GET /api/shipstation/stores              # Get stores
```

#### Operations
```
POST /api/shipstation/rates              # Get shipping rates
POST /api/shipstation/create-label       # Create shipping label
```

#### Webhooks
```
POST /api/shipstation/webhook            # Webhook receiver
```

## Database Schema

### Shop.settings.shipstation

```php
'shipstation' => [
    'api_key' => 'string',               // ShipStation API key
    'api_secret' => 'string',            // ShipStation API secret
    'default_carrier' => 'string',       // Default carrier code
    'default_service' => 'string',       // Default service code
    'default_package' => 'string',       // Default package type
    'default_confirmation' => 'string',  // Default confirmation type
    'default_warehouse_id' => int,       // Default warehouse ID
    'auto_create_orders' => bool,        // Auto-create orders
    'auto_create_labels' => bool,        // Auto-create labels
    'auto_fulfill_orders' => bool,       // Auto-fulfill on ship
]
```

### ChannelOrder.fulfillment_data

```php
'fulfillment_data' => [
    'shipstation' => [
        'order_id' => int,                   // ShipStation order ID
        'order_number' => 'string',          // Order number
        'order_key' => 'string',             // Order key
        'shipment_id' => int,                // Shipment ID
        'tracking_number' => 'string',       // Tracking number
        'carrier_code' => 'string',          // Carrier code
        'service_code' => 'string',          // Service code
        'ship_date' => 'string',             // Ship date
        'label_created_at' => 'string',      // Label creation timestamp
        'shipping_cost' => float,            // Shipping cost
        'insurance_cost' => float,           // Insurance cost
        'label_path' => 'string',            // Path to label PDF
        'form_path' => 'string',             // Path to form PDF
        'voided' => bool,                    // Label voided
        'void_date' => 'string',             // Void date
        'created_at' => 'string',            // Creation timestamp
        'last_synced' => 'string',           // Last sync timestamp
    ]
]
```

## Troubleshooting

### Common Issues

#### 1. Authentication Fails

**Symptoms**: API requests return 401 Unauthorized

**Solutions**:
- Verify API key and secret are correct
- Check that keys are not expired
- Ensure no extra whitespace in credentials
- Try regenerating API keys in ShipStation

#### 2. Order Not Found

**Symptoms**: "Order not found" when creating label

**Solutions**:
- Ensure order was created in ShipStation first
- Check that `orderNumber` matches exactly
- Verify order hasn't been deleted in ShipStation
- Try creating the order manually first

#### 3. Label Creation Fails

**Symptoms**: Label creation returns error

**Solutions**:
- Verify carrier and service codes are valid
- Check that address is complete and valid
- Ensure weight is specified
- Verify package type is supported by carrier
- Check that warehouse has inventory (if specified)

#### 4. Missing Tracking Updates

**Symptoms**: Tracking numbers not updating from webhooks

**Solutions**:
- Verify webhook URL is publicly accessible
- Check webhook is configured in ShipStation
- Review webhook delivery status in ShipStation
- Ensure `orderNumber` matches local order
- Check logs for webhook processing errors

#### 5. Rate Request Fails

**Symptoms**: Unable to get shipping rates

**Solutions**:
- Verify destination address is valid
- Check that weight is specified
- Ensure carrier supports the destination
- Verify carrier account is active in ShipStation

#### 6. Duplicate Orders

**Symptoms**: Multiple orders created in ShipStation

**Solutions**:
- Ensure `orderNumber` is unique
- Check that update logic uses `orderNumber` lookup
- Review order creation job for duplicates
- Use `updateOrCreate` pattern

#### 7. Label Not Downloading

**Symptoms**: Label PDF not saved to storage

**Solutions**:
- Check storage disk is writable
- Verify storage path exists
- Check labelData is present in response
- Review storage configuration

### Debug Mode

Enable detailed logging:

```php
// In ShipStationClient.php
Log::debug('ShipStation API Request', [
    'method' => $method,
    'endpoint' => $endpoint,
    'options' => $options,
]);

Log::debug('ShipStation API Response', [
    'data' => $data,
]);
```

### Testing

#### Test with Sample Order

```php
$order = ChannelOrder::first();
$shop = $order->shop;

// Test order creation
CreateShipStationOrder::dispatchSync($shop, $order);

// Test label creation
CreateShipStationLabel::dispatchSync($shop, $order, [
    'test_label' => true,
    'carrier_code' => 'stamps_com',
    'service_code' => 'usps_priority_mail',
]);
```

#### Test Webhook

Use ShipStation's "Send Test" button in webhook settings to send a test payload to your webhook URL.

### Logs to Check

- `storage/logs/laravel.log` - General application logs
- Search for "ShipStation" to find integration-specific logs
- Check for job failures in queue workers
- Review webhook delivery status in ShipStation dashboard

## Best Practices

### 1. Order Creation

- Create orders in ShipStation as soon as they're placed
- Use unique order numbers
- Include all relevant customer and item data
- Add internal notes for context

### 2. Label Generation

- Validate addresses before creating labels
- Use test labels during development
- Store label PDFs securely
- Handle label voids appropriately

### 3. Rate Shopping

- Get rates before creating labels
- Cache rates temporarily to avoid API limits
- Show multiple options to users
- Factor in delivery time

### 4. Error Handling

- Wrap API calls in try-catch blocks
- Log all errors with context
- Retry failed jobs with backoff
- Alert on repeated failures

### 5. Performance

- Use queued jobs for all operations
- Batch process orders when possible
- Implement webhook processing
- Avoid polling - use webhooks

### 6. Data Integrity

- Store ShipStation IDs for reference
- Keep raw shipment data
- Validate data before sending
- Handle missing data gracefully

## Supported Carriers

ShipStation supports 40+ carriers including:

- **USPS** (via Stamps.com or Endicia)
- **FedEx**
- **UPS**
- **DHL Express**
- **DHL eCommerce**
- **Canada Post**
- **Australia Post**
- **Royal Mail**
- **USPS International**
- **OnTrac**
- **Newgistics**
- **And many more...**

Carrier availability depends on your ShipStation account setup.

## Future Enhancements

Potential improvements for this integration:

1. **Return Labels**: Generate return shipping labels
2. **Batch Printing**: Print multiple labels at once
3. **Address Validation**: Validate addresses before creating labels
4. **Insurance**: Automated insurance for high-value shipments
5. **Customs Forms**: Better international shipping support
6. **Branded Tracking**: Custom tracking pages
7. **Automation Rules**: More complex automation workflows
8. **Multi-Package**: Support for orders with multiple packages
9. **Carrier Accounts**: Direct carrier account integration
10. **Analytics**: Shipping cost and performance analytics

## Support

For issues or questions:

1. Check this documentation
2. Review Laravel logs
3. Check ShipStation API documentation
4. Contact ShipStation support for ShipStation-specific issues
5. File an issue in the project repository

## Resources

- [ShipStation API Documentation](https://www.shipstation.com/docs/api/)
- [ShipStation Help Center](https://help.shipstation.com/)
- [ShipStation Webhooks Guide](https://www.shipstation.com/docs/api/webhooks/)
- [Supported Carriers](https://www.shipstation.com/carriers/)

---

**Last Updated**: November 15, 2025
**Integration Version**: 1.0.0
**ShipStation API**: v3
