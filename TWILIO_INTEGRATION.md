# Twilio Notifications Integration Guide

## Overview

This guide covers the Twilio integration that enables SMS and WhatsApp notifications for your multichannel sales platform. Each shop can configure their own Twilio account for sending customer notifications.

## Features

- ✅ Shop-specific Twilio credentials (multi-tenant)
- ✅ SMS notifications
- ✅ WhatsApp Business notifications
- ✅ Customizable message templates
- ✅ Template variables for personalization
- ✅ Notification history and tracking
- ✅ Delivery status monitoring
- ✅ Cost tracking per notification
- ✅ Automatic retry on failure
- ✅ Background job processing

## Prerequisites

### 1. Twilio Account

Each shop needs their own Twilio account:

1. Go to [Twilio](https://www.twilio.com/try-twilio)
2. Sign up for a new account (starts with free trial credit)
3. Verify your email and phone number
4. Get your Account SID and Auth Token from the Console
5. Purchase a phone number for SMS
6. (Optional) Set up WhatsApp Business

### 2. Phone Number

**For SMS:**
- Purchase a phone number from Twilio Console
- Choose a number with SMS capabilities
- Note: Trial accounts can only send to verified numbers

**For WhatsApp:**
- Request WhatsApp Business access in Twilio Console
- Get your WhatsApp-enabled number
- Complete WhatsApp Business profile setup
- Format: `whatsapp:+1234567890`

## Setup

### Via Frontend

1. Navigate to **Settings** → **Notifications** (or `/twilio/settings`)
2. Click on **Credentials** tab
3. Enter your Twilio credentials:
   - **Account SID**: From Twilio Console (starts with AC)
   - **Auth Token**: From Twilio Console
   - **From Phone Number**: Your Twilio phone number (E.164 format)
   - **WhatsApp Number**: (Optional) Your WhatsApp-enabled number
4. Click **Save Credentials**
5. Click **Test Connection** to verify

### Via API

```php
use App\Models\Shop;

$shop = Shop::find(1);

$settings = $shop->settings ?? [];
$settings['twilio'] = [
    'account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'auth_token' => 'your_auth_token',
    'from_number' => '+1234567890',
    'whatsapp_number' => 'whatsapp:+1234567890', // optional
];

$shop->update(['settings' => $settings]);
```

## Sending Notifications

### Method 1: Using Jobs (Recommended)

```php
use App\Jobs\Twilio\SendNotification;
use App\Models\Shop;

$shop = Shop::find(1);

// Send SMS
SendNotification::dispatch(
    $shop,
    '+1234567890',  // to
    'Your order has been shipped!',  // message
    'sms'  // channel
);

// Send WhatsApp
SendNotification::dispatch(
    $shop,
    '+1234567890',
    'Your order has been shipped!',
    'whatsapp'
);
```

### Method 2: Using Templates

```php
use App\Jobs\Twilio\SendTemplatedNotification;

SendTemplatedNotification::dispatch(
    $shop,
    '+1234567890',  // to
    'order_shipped',  // template key
    [
        'customer_name' => 'John Doe',
        'order_number' => 'ORD-12345',
        'tracking_number' => '1Z999AA10123456784',
    ],  // variables
    'sms',  // channel
    'order',  // related type
    12345  // related ID
);
```

### Method 3: Direct Client Usage

```php
use App\Services\Twilio\TwilioClient;

$client = new TwilioClient($shop);

// Send SMS
$result = $client->sendSMS('+1234567890', 'Hello from your shop!');

// Send WhatsApp
$result = $client->sendWhatsApp('+1234567890', 'Hello via WhatsApp!');

// Check if configured
if ($client->isConfigured()) {
    // SMS is configured
}

if ($client->isWhatsAppConfigured()) {
    // WhatsApp is configured
}
```

## Message Templates

### Default Templates

The system includes these default templates:

1. **order_placed** - Sent when order is created
2. **order_shipped** - Sent when order ships
3. **order_delivered** - Sent when order is delivered
4. **low_inventory** - Alert for low stock
5. **order_cancelled** - Sent when order is cancelled

### Customizing Templates

```php
$shop = Shop::find(1);

$settings = $shop->settings ?? [];
$settings['notification_templates'] = [
    'order_placed' => [
        'name' => 'Order Placed',
        'message' => 'Hi {customer_name}, your order #{order_number} has been placed. Total: {total}. Thank you!',
        'enabled' => true,
    ],
    'order_shipped' => [
        'name' => 'Order Shipped',
        'message' => 'Hi {customer_name}, your order #{order_number} has shipped! Track: {tracking_number}',
        'enabled' => true,
    ],
    // Add custom templates
    'payment_received' => [
        'name' => 'Payment Received',
        'message' => 'Payment of {amount} received for order #{order_number}. Thank you!',
        'enabled' => true,
    ],
];

$shop->update(['settings' => $settings]);
```

### Available Variables

- `{customer_name}` - Customer's name
- `{order_number}` - Order number/ID
- `{total}` - Order total amount
- `{tracking_number}` - Shipment tracking number
- `{product_name}` - Product name
- `{sku}` - Product SKU
- `{quantity}` - Quantity
- `{shop_name}` - Your shop name
- Custom variables as needed

## Automatic Notifications

### Order Events

```php
// When order is placed
use App\Jobs\Twilio\SendTemplatedNotification;

$order = ChannelOrder::find(1);
$shop = $order->shop;

if ($order->customer_phone) {
    SendTemplatedNotification::dispatch(
        $shop,
        $order->customer_phone,
        'order_placed',
        [
            'customer_name' => $order->customer_name,
            'order_number' => $order->order_number,
            'total' => $order->currency . ' ' . $order->total_price,
        ],
        'sms',
        'order',
        $order->id
    );
}
```

### Inventory Alerts

```php
// When inventory is low
use App\Jobs\Twilio\SendNotification;

$variant = ProductVariant::find(1);
$shop = $variant->product->shop;

// Get admin phone from shop settings
$adminPhone = $shop->settings['admin_phone'] ?? null;

if ($adminPhone && $variant->total_quantity < 10) {
    SendNotification::dispatch(
        $shop,
        $adminPhone,
        "ALERT: {$variant->product->title} (SKU: {$variant->sku}) is low on stock. Only {$variant->total_quantity} remaining.",
        'sms',
        null,
        null,
        'product',
        $variant->id
    );
}
```

## Notification History

### Viewing History

```php
use App\Models\NotificationLog;

// Get all notifications for a shop
$logs = NotificationLog::forShop($shop->id)
    ->orderBy('created_at', 'desc')
    ->get();

// Get failed notifications
$failed = NotificationLog::forShop($shop->id)
    ->status('failed')
    ->get();

// Get statistics
$stats = [
    'total' => NotificationLog::forShop($shop->id)->count(),
    'sent' => NotificationLog::forShop($shop->id)->status('sent')->count(),
    'delivered' => NotificationLog::forShop($shop->id)->status('delivered')->count(),
    'failed' => NotificationLog::forShop($shop->id)->status('failed')->count(),
];
```

### Log Fields

Each notification log includes:

- `shop_id` - Shop that sent the notification
- `channel` - sms or whatsapp
- `to` - Recipient phone number
- `from` - Sender phone number
- `message` - Message content
- `template_key` - Template used (if any)
- `template_variables` - Variables passed to template
- `message_sid` - Twilio message ID
- `status` - queued, sent, delivered, failed
- `error_code` - Error code if failed
- `error_message` - Error message if failed
- `price` - Cost of sending
- `price_unit` - Currency (e.g., USD)
- `related_type` - Related model (order, product, etc.)
- `related_id` - Related model ID
- `sent_at` - When sent
- `delivered_at` - When delivered
- `failed_at` - When failed

## Phone Number Formatting

### E.164 Format

All phone numbers must be in E.164 format:

- Format: `+[country code][number]`
- Example: `+12025551234` (US)
- Example: `+442012345678` (UK)

### Automatic Formatting

```php
use App\Services\Twilio\TwilioClient;

// Format phone number
$formatted = TwilioClient::formatPhoneNumber('(202) 555-1234');
// Returns: +12025551234

// With country code
$formatted = TwilioClient::formatPhoneNumber('2025551234', '+1');
// Returns: +12025551234

// Validate format
$isValid = TwilioClient::validatePhoneNumber('+12025551234');
// Returns: true
```

## WhatsApp Specific

### Setup Requirements

1. Apply for WhatsApp Business access in Twilio
2. Complete business verification
3. Set up message templates (pre-approved by WhatsApp)
4. Wait for approval (can take several days)

### Sending WhatsApp Messages

```php
// Number format
$to = 'whatsapp:+1234567890';

// Or let the client format it
$client = new TwilioClient($shop);
$result = $client->sendWhatsApp('+1234567890', 'Message');
// Automatically adds whatsapp: prefix
```

### WhatsApp vs SMS

| Feature | SMS | WhatsApp |
|---------|-----|----------|
| **Approval** | Instant | Requires approval |
| **Templates** | No restriction | Pre-approved templates |
| **Media** | MMS (extra cost) | Free media |
| **Rich Content** | Limited | Supported |
| **Read Receipts** | No | Yes |
| **International** | Expensive | Free (data rates) |

## Error Handling

### Retry Logic

Jobs automatically retry on failure:

```php
// In SendNotification job
public $tries = 3;
public $backoff = [30, 60, 120]; // Retry after 30s, 60s, 120s
```

### Handling Failures

```php
// In job's failed method
public function failed(\Throwable $exception): void
{
    // Log is marked as failed
    // Notification sent to admin (optional)
    Log::error("Notification failed permanently", [
        'shop_id' => $this->shop->id,
        'error' => $exception->getMessage(),
    ]);
}
```

## Cost Management

### Viewing Costs

```php
// Get total cost for a shop
$totalCost = NotificationLog::forShop($shop->id)
    ->whereNotNull('price')
    ->sum('price');

// Get cost by channel
$smsCost = NotificationLog::forShop($shop->id)
    ->channel('sms')
    ->sum('price');

$whatsappCost = NotificationLog::forShop($shop->id)
    ->channel('whatsapp')
    ->sum('price');
```

### Monitoring Balance

```php
$client = new TwilioClient($shop);
$balance = $client->getBalance();

// Returns:
[
    'balance' => '-5.00',
    'currency' => 'USD',
    'account_status' => 'active',
]
```

## Best Practices

### 1. Opt-In/Opt-Out

- Always get customer consent before sending notifications
- Provide opt-out instructions in messages
- Store consent status in customer records
- Honor opt-out requests immediately

Example:
```
Your order #12345 has shipped! Track: http://track.me/123. Reply STOP to unsubscribe.
```

### 2. Message Content

- Keep messages concise and relevant
- Include shop name for brand recognition
- Provide actionable information
- Use clear call-to-actions

### 3. Timing

- Don't send during late night/early morning
- Consider customer timezone
- Respect frequency limits
- Use queues for bulk sending

### 4. Testing

- Always test with your own number first
- Test both SMS and WhatsApp
- Verify template variables work correctly
- Check message length (160 chars for SMS)

### 5. Compliance

- Follow TCPA regulations (US)
- Comply with GDPR (EU)
- Include business identification
- Provide opt-out mechanism
- Don't send marketing to non-consenting users

## Troubleshooting

### Connection Failed

**Error: "Invalid credentials"**
- Verify Account SID starts with "AC"
- Check Auth Token is correct
- Ensure account is active

**Error: "Phone number not configured"**
- Verify phone number in E.164 format
- Check number is purchased in Twilio
- Ensure SMS capability is enabled

### Sending Failed

**Error: "To number is not a valid phone number"**
- Format must be E.164: +[country][number]
- No spaces, dashes, or parentheses
- Use `formatPhoneNumber()` helper

**Error: "The number +X is unverified"**
- Trial accounts can only send to verified numbers
- Verify number in Twilio Console
- Or upgrade to paid account

**Error: "Permission denied"**
- WhatsApp requires pre-approved templates
- Submit templates for approval in Twilio
- Wait for WhatsApp approval

### Cost Issues

**Unexpected costs:**
- Check notification history for volume
- Review failed messages (still charged)
- Monitor international rates
- Consider WhatsApp for lower costs

## Advanced Features

### Custom Notifications

```php
// Create custom notification type
$settings = $shop->settings ?? [];
$settings['notification_templates']['custom_promo'] = [
    'name' => 'Promotional Offer',
    'message' => 'Hi {customer_name}! Get {discount}% off your next order. Use code: {promo_code}',
    'enabled' => true,
];
$shop->update(['settings' => $settings]);

// Send it
SendTemplatedNotification::dispatch(
    $shop,
    $customer->phone,
    'custom_promo',
    [
        'customer_name' => $customer->name,
        'discount' => '20',
        'promo_code' => 'SAVE20',
    ]
);
```

### Media Messages (MMS)

```php
$client = new TwilioClient($shop);
$client->sendSMS(
    '+1234567890',
    'Check out your order!',
    [
        'mediaUrl' => ['https://yoursite.com/order-image.jpg'],
    ]
);
```

### Webhooks

Add status callbacks to track delivery:

```php
$client->sendSMS(
    '+1234567890',
    'Message',
    [
        'statusCallback' => 'https://yoursite.com/api/twilio/status',
    ]
);
```

## Multi-Shop Example

```php
// Each shop has independent Twilio settings

// Shop 1 (US-based)
$shop1 = Shop::find(1);
$shop1->update([
    'settings' => [
        'twilio' => [
            'account_sid' => 'AC111...',
            'auth_token' => 'token1',
            'from_number' => '+1234567890',
        ],
    ],
]);

// Shop 2 (UK-based)
$shop2 = Shop::find(2);
$shop2->update([
    'settings' => [
        'twilio' => [
            'account_sid' => 'AC222...',
            'auth_token' => 'token2',
            'from_number' => '+442012345678',
        ],
    ],
]);

// Each sends from their own number
SendNotification::dispatch($shop1, $customer1Phone, 'Message from Shop 1');
SendNotification::dispatch($shop2, $customer2Phone, 'Message from Shop 2');
```

## API Reference

### TwilioClient Methods

```php
$client = new TwilioClient($shop);

// Configuration checks
$client->isConfigured(): bool
$client->isWhatsAppConfigured(): bool

// Send messages
$client->sendSMS(string $to, string $message, array $options = []): array
$client->sendWhatsApp(string $to, string $message, array $options = []): array

// Status and info
$client->getMessageStatus(string $messageSid): array
$client->getBalance(): array
$client->testConnection(): array
$client->getPhoneNumbers(): array

// Helpers
TwilioClient::validatePhoneNumber(string $phoneNumber): bool
TwilioClient::formatPhoneNumber(string $phoneNumber, string $countryCode = '+1'): string
```

## License

This Twilio integration is part of the Multichannel Sales & Inventory Manager and follows the same license terms.
