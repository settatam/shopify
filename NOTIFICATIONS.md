# Notifications Module

## Overview

The **Notifications Module** provides comprehensive event-driven communication capabilities for the Multichannel Sales & Inventory Management platform. Send automated, personalized emails, SMS, push notifications, and in-app messages based on business events like orders, inventory changes, returns, and system alerts.

---

## Features

### 1. **Event-Driven Architecture**
- Trigger notifications automatically based on business events
- 30+ predefined event types across 6 categories
- Support for custom events
- Template-based messaging with variable substitution

### 2. **Multi-Channel Support**
- **Email**: Full HTML and plain text support
- **SMS**: Text message notifications (via Twilio, SNS, etc.)
- **Push**: Mobile and web push notifications (via FCM, APNS)
- **In-App**: Real-time in-app notification center

### 3. **Template Management**
- Visual HTML email editor
- Variable substitution with {variable} syntax
- Template versioning and cloning
- Per-shop customization
- Test mode for template validation

### 4. **User Preferences**
- Granular control over notification types
- Per-event, per-channel preferences
- Opt-in/opt-out management
- Digest delivery options (future)

### 5. **Delivery Tracking**
- Real-time delivery status
- Open tracking (email)
- Click tracking (email links)
- Bounce and failure detection
- Automatic retry with exponential backoff

### 6. **Analytics & Reporting**
- Delivery rates
- Open rates
- Click-through rates
- Failure analysis
- Per-event performance metrics

---

## Database Schema

### Tables

#### `notification_templates`
Stores notification message templates.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| shop_id | bigint | Shop owner |
| event_type | string | Event identifier (order.placed, etc.) |
| name | string | Human-readable template name |
| subject | string | Email subject with variables |
| body_html | text | HTML email body |
| body_text | text | Plain text version |
| available_variables | json | List of available template variables |
| from_name | string | Override default sender name |
| from_email | string | Override default sender email |
| reply_to | string | Reply-to email address |
| is_active | boolean | Enable/disable template |
| is_system | boolean | System templates (can't be deleted) |
| category | enum | transactional, marketing, system |
| settings | json | Additional settings |

#### `notification_preferences`
User preferences for each notification type.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | User |
| shop_id | bigint | Shop |
| event_type | string | Event identifier |
| email_enabled | boolean | Receive via email |
| sms_enabled | boolean | Receive via SMS |
| push_enabled | boolean | Receive push notifications |
| in_app_enabled | boolean | Show in-app |
| settings | json | Additional preferences |

#### `notification_logs`
History of all sent notifications.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| shop_id | bigint | Shop |
| user_id | bigint | Recipient user |
| notification_template_id | bigint | Template used |
| event_type | string | Event that triggered notification |
| channel | enum | email, sms, push, in_app |
| recipient_email | string | Email address |
| recipient_phone | string | Phone number |
| subject | string | Actual subject sent |
| body | text | Actual body sent |
| variables | json | Variables used |
| metadata | json | Related entity IDs |
| status | enum | queued, sending, sent, delivered, opened, clicked, bounced, failed, rejected, unsubscribed |
| error_message | text | Error details if failed |
| provider | string | Email/SMS provider |
| provider_message_id | string | Provider's tracking ID |
| queued_at | timestamp | When queued |
| sent_at | timestamp | When sent |
| delivered_at | timestamp | When delivered |
| opened_at | timestamp | When opened (email) |
| clicked_at | timestamp | When link clicked (email) |
| bounced_at | timestamp | When bounced |
| failed_at | timestamp | When failed |
| retry_count | integer | Number of retry attempts |
| next_retry_at | timestamp | When to retry |

#### `notification_events`
Defines all available notification event types.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| event_type | string | Unique event identifier |
| name | string | Human-readable name |
| description | text | Event description |
| category | enum | orders, inventory, returns, channels, system, marketing |
| available_variables | json | Variables available for this event |
| is_system | boolean | System event |
| enabled_by_default | boolean | Default preference for new users |
| settings | json | Event-specific settings |

---

## Event Types

### Orders Category

| Event Type | Description | Variables |
|------------|-------------|-----------|
| order.placed | New order created | customer_name, order_number, order_total, order_date, product_name |
| order.paid | Payment confirmed | customer_name, order_number, order_total, payment_method |
| order.shipped | Order shipped | customer_name, order_number, tracking_number, tracking_url, carrier |
| order.delivered | Order delivered | customer_name, order_number, delivery_date |
| order.cancelled | Order cancelled | customer_name, order_number, cancellation_reason |
| order.refunded | Order refunded | customer_name, order_number, refund_amount |

### Inventory Category

| Event Type | Description | Variables |
|------------|-------------|-----------|
| inventory.low_stock | Stock below threshold | product_name, product_sku, quantity, threshold, shop_name |
| inventory.out_of_stock | Product out of stock | product_name, product_sku, shop_name |
| inventory.restocked | Product restocked | product_name, product_sku, new_quantity, shop_name |
| inventory.sync_failed | Inventory sync error | channel_name, error_message, shop_name |

### Returns Category

| Event Type | Description | Variables |
|------------|-------------|-----------|
| return.created | Return request submitted | customer_name, rma_number, order_number, return_reason |
| return.approved | Return request approved | customer_name, rma_number, return_label_url |
| return.rejected | Return request rejected | customer_name, rma_number, rejection_reason |
| return.received | Return item received | customer_name, rma_number, received_date |
| return.refund_processed | Refund processed | customer_name, rma_number, refund_amount, refund_method |

### Channels Category

| Event Type | Description | Variables |
|------------|-------------|-----------|
| channel.connected | Channel successfully connected | channel_name, channel_type, shop_name |
| channel.disconnected | Channel disconnected | channel_name, channel_type, reason |
| channel.sync_error | Channel sync failed | channel_name, error_message, products_affected |
| channel.listing_created | New listing published | product_name, channel_name, listing_url |

### System Category

| Event Type | Description | Variables |
|------------|-------------|-----------|
| system.welcome | New user welcome | user_name, shop_name, shop_url |
| system.password_reset | Password reset requested | user_name, reset_link, expiry_time |
| system.invoice | Monthly invoice generated | shop_name, invoice_number, amount_due, due_date |
| system.trial_ending | Trial period ending soon | shop_name, days_remaining, upgrade_url |
| system.subscription_renewed | Subscription renewed | shop_name, plan_name, next_billing_date |

### Marketing Category

| Event Type | Description | Variables |
|------------|-------------|-----------|
| marketing.abandoned_cart | Cart abandoned | customer_name, cart_total, cart_url, products |
| marketing.product_recommendation | Product recommendation | customer_name, recommended_products |
| marketing.sale_announcement | Sale or promotion | shop_name, sale_title, discount_amount, sale_url |
| marketing.newsletter | Newsletter/update | shop_name, newsletter_content |

---

## API Endpoints

### Templates

#### Get All Templates
```http
GET /api/notifications/templates
```

**Response:**
```json
{
  "templates": [
    {
      "id": 1,
      "shop_id": 1,
      "event_type": "order.placed",
      "name": "Order Confirmation",
      "subject": "Order {order_number} Confirmed!",
      "body_html": "<p>Hi {customer_name},...</p>",
      "is_active": true,
      "category": "transactional",
      "created_at": "2025-11-15T10:00:00Z"
    }
  ]
}
```

#### Create Template
```http
POST /api/notifications/templates
```

**Request:**
```json
{
  "event_type": "order.placed",
  "name": "Order Confirmation",
  "subject": "Order {order_number} Confirmed!",
  "body_html": "<p>Hi {customer_name}, your order {order_number} has been placed...</p>",
  "body_text": "Hi {customer_name}, your order {order_number} has been placed...",
  "category": "transactional",
  "from_name": "My Store",
  "from_email": "orders@mystore.com",
  "reply_to": "support@mystore.com"
}
```

#### Update Template
```http
PUT /api/notifications/templates/{template}
```

#### Delete Template
```http
DELETE /api/notifications/templates/{template}
```

#### Test Template
```http
POST /api/notifications/templates/{template}/test
```

**Request:**
```json
{
  "email": "test@example.com",
  "variables": {
    "customer_name": "John Doe",
    "order_number": "ORD-12345",
    "order_total": "$99.99"
  }
}
```

### Preferences

#### Get User Preferences
```http
GET /api/notifications/preferences
```

**Response:**
```json
{
  "preferences": {
    "order.placed": {
      "name": "Order Placed",
      "description": "When a new order is created",
      "category": "orders",
      "email": true,
      "sms": false,
      "push": true,
      "in_app": true
    },
    "inventory.low_stock": {
      "name": "Low Stock Alert",
      "description": "When inventory falls below threshold",
      "category": "inventory",
      "email": true,
      "sms": true,
      "push": false,
      "in_app": true
    }
  }
}
```

#### Update Preferences
```http
PUT /api/notifications/preferences
```

**Request:**
```json
{
  "preferences": {
    "order.placed": {
      "email": true,
      "sms": false,
      "push": true,
      "in_app": true
    },
    "inventory.low_stock": {
      "email": true,
      "sms": true,
      "push": false,
      "in_app": true
    }
  }
}
```

### Logs

#### Get Notification Logs
```http
GET /api/notifications/logs?status=sent&event_type=order.placed&start_date=2025-01-01&end_date=2025-01-31
```

**Query Parameters:**
- `event_type`: Filter by event type
- `status`: Filter by status (queued, sent, delivered, failed, etc.)
- `channel`: Filter by channel (email, sms, push, in_app)
- `user_id`: Filter by user
- `start_date`: Filter by date range
- `end_date`: Filter by date range

**Response:**
```json
{
  "data": [
    {
      "id": 123,
      "event_type": "order.placed",
      "channel": "email",
      "recipient_email": "customer@example.com",
      "subject": "Order ORD-12345 Confirmed!",
      "status": "delivered",
      "sent_at": "2025-01-15T10:30:00Z",
      "delivered_at": "2025-01-15T10:30:05Z",
      "opened_at": "2025-01-15T11:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 1250
  }
}
```

#### Retry Failed Notification
```http
POST /api/notifications/logs/{log}/retry
```

#### Retry All Failed
```http
POST /api/notifications/logs/retry-all
```

### Statistics

#### Get Notification Statistics
```http
GET /api/notifications/statistics?event_type=order.placed
```

**Response:**
```json
{
  "statistics": {
    "total": 10000,
    "sent": 9850,
    "failed": 150,
    "queued": 0,
    "delivery_rate": 98.5,
    "open_rate": 45.2,
    "click_rate": 12.8
  }
}
```

### Events

#### Get All Available Events
```http
GET /api/notifications/events
```

**Response:**
```json
{
  "events": [
    {
      "event_type": "order.placed",
      "name": "Order Placed",
      "description": "Triggered when a new order is created",
      "category": "orders",
      "available_variables": {
        "customer_name": "Customer's full name",
        "order_number": "Order reference number",
        "order_total": "Total order amount"
      },
      "enabled_by_default": true
    }
  ]
}
```

---

## Programmatic Usage

### Sending Notifications

```php
use App\Services\NotificationService;
use App\Models\User;

$notificationService = app(NotificationService::class);

// Send single notification
$user = User::find(1);
$notificationService->send(
    eventType: 'order.placed',
    user: $user,
    variables: [
        'customer_name' => 'John Doe',
        'order_number' => 'ORD-12345',
        'order_total' => '$99.99',
        'order_date' => now()->format('M d, Y'),
    ],
    metadata: [
        'order_id' => 12345,
    ],
    channel: 'email'
);

// Send to multiple users
$users = User::where('shop_id', 1)->get();
$notificationService->sendBulk(
    eventType: 'system.maintenance',
    users: $users,
    variables: [
        'maintenance_start': '2025-01-20 02:00 AM',
        'maintenance_end': '2025-01-20 06:00 AM',
    ]
);

// Send to all shop owners
$notificationService->sendToShopUsers(
    shop: $shop,
    eventType: 'system.invoice',
    variables: [
        'invoice_number': 'INV-2025-01',
        'amount_due': '$299.00',
        'due_date': 'January 30, 2025',
    ],
    roles: ['owner', 'admin']
);
```

### Managing Preferences

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

// Enable notification for user
$notificationService->enableForUser($user, 'order.placed', 'email');

// Disable notification
$notificationService->disableForUser($user, 'inventory.low_stock', 'sms');

// Update all preferences
$notificationService->updatePreferences($user, [
    'order.placed' => [
        'email' => true,
        'sms' => false,
        'push' => true,
        'in_app' => true,
    ],
    'inventory.low_stock' => [
        'email' => true,
        'sms' => true,
        'push' => false,
        'in_app' => true,
    ],
]);

// Get user preferences
$preferences = $notificationService->getUserPreferences($user);
```

### Creating Custom Templates

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

$template = $notificationService->createTemplate(
    shop: $shop,
    eventType: 'order.placed',
    name: 'Order Confirmation Email',
    subject: 'Thank you for your order {order_number}!',
    bodyHtml: '
        <html>
        <body>
            <h1>Order Confirmed!</h1>
            <p>Hi {customer_name},</p>
            <p>Your order <strong>{order_number}</strong> has been confirmed.</p>
            <p>Total: {order_total}</p>
            <p>Order Date: {order_date}</p>
            <p>Thank you for shopping with us!</p>
        </body>
        </html>
    ',
    bodyText: 'Hi {customer_name}, Your order {order_number} has been confirmed. Total: {order_total}',
    settings: [
        'category' => 'transactional',
        'from_name' => 'My Store',
        'from_email' => 'orders@mystore.com',
        'reply_to' => 'support@mystore.com',
    ]
);
```

### Retry Logic

```php
use App\Services\NotificationService;
use App\Models\NotificationLog;

$notificationService = app(NotificationService::class);

// Retry specific notification
$log = NotificationLog::find(123);
$notificationService->retry($log);

// Retry all failed for shop
$retriedCount = $notificationService->retryFailed($shop, maxRetries: 3);
```

---

## Template Variables

### Common Variables (Available in Most Templates)

| Variable | Description | Example |
|----------|-------------|---------|
| {shop_name} | Shop name | "My Awesome Store" |
| {shop_url} | Shop website URL | "https://mystore.com" |
| {support_email} | Support email | "support@mystore.com" |
| {support_phone} | Support phone | "1-800-123-4567" |

### Order Variables

| Variable | Description | Example |
|----------|-------------|---------|
| {customer_name} | Customer full name | "John Doe" |
| {customer_email} | Customer email | "john@example.com" |
| {order_number} | Order reference | "ORD-12345" |
| {order_total} | Total amount | "$99.99" |
| {order_date} | Order date | "January 15, 2025" |
| {tracking_number} | Shipping tracking | "1Z999AA10123456784" |
| {tracking_url} | Tracking link | "https://..." |

### Product Variables

| Variable | Description | Example |
|----------|-------------|---------|
| {product_name} | Product name | "Wireless Headphones" |
| {product_sku} | Product SKU | "SKU-123" |
| {quantity} | Quantity | "2" |
| {price} | Unit price | "$49.99" |

### Return Variables

| Variable | Description | Example |
|----------|-------------|---------|
| {rma_number} | Return authorization | "RMA-12345" |
| {return_reason} | Reason for return | "Defective item" |
| {refund_amount} | Refund amount | "$99.99" |
| {refund_method} | Refund method | "Original payment method" |
| {return_label_url} | Return shipping label | "https://..." |

---

## Email Tracking

### Open Tracking

The system automatically inserts a 1x1 transparent tracking pixel into email bodies:

```html
<img src="https://app.com/api/notifications/track/open/123" width="1" height="1" alt="" />
```

When the email is opened and images are loaded, the pixel is requested and the notification log is updated with `opened_at` timestamp.

### Click Tracking

All links in email bodies are automatically wrapped with tracking URLs:

**Original:**
```html
<a href="https://mystore.com/order/12345">View Order</a>
```

**Tracked:**
```html
<a href="https://app.com/api/notifications/track/click/123?url=https%3A%2F%2Fmystore.com%2Forder%2F12345">View Order</a>
```

When clicked, the system:
1. Records the click with `clicked_at` timestamp
2. Redirects user to the original URL

---

## Email Providers

### Supported Providers

The system supports multiple email providers via Laravel Mail configuration:

1. **SMTP** - Any SMTP server
2. **SendGrid** - API integration
3. **Mailgun** - API integration
4. **Amazon SES** - API integration
5. **Postmark** - API integration

### Configuration

Configure in `.env`:

```env
# SMTP Example
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@mystore.com
MAIL_FROM_NAME="My Store"

# SendGrid Example
MAIL_MAILER=sendgrid
SENDGRID_API_KEY=your-sendgrid-api-key

# Mailgun Example
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.mystore.com
MAILGUN_SECRET=your-mailgun-secret

# Amazon SES Example
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
```

### Webhooks (Provider-Specific)

For delivery confirmation, bounces, and opens, configure webhooks:

**SendGrid:**
```
Webhook URL: https://app.com/api/notifications/webhook?provider=sendgrid
Events: delivered, bounce, open, click, unsubscribe
```

**Mailgun:**
```
Webhook URL: https://app.com/api/notifications/webhook?provider=mailgun
Events: delivered, permanent_fail, temporary_fail, opened, clicked, unsubscribed
```

**Postmark:**
```
Webhook URL: https://app.com/api/notifications/webhook?provider=postmark
Events: Delivery, Bounce, Open, Click, Unsubscribe
```

---

## Queue Configuration

Notifications are processed asynchronously using Laravel queues.

### Queue Setup

1. **Configure Queue Driver** (`.env`):
```env
QUEUE_CONNECTION=redis  # Or database, sqs, beanstalkd
```

2. **Run Queue Worker**:
```bash
php artisan queue:work --queue=notifications
```

3. **Supervisor Configuration** (`/etc/supervisor/conf.d/notifications.conf`):
```ini
[program:notifications-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=notifications --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/notifications-worker.log
```

### Queue Monitoring

Monitor queue health:

```bash
# Check queue size
php artisan queue:monitor notifications

# Failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## Scheduled Tasks

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Cleanup old notification logs (keep 90 days)
    $schedule->job(new CleanupOldNotificationsJob(90))
        ->daily()
        ->at('03:00');

    // Retry failed notifications
    $schedule->call(function () {
        foreach (Shop::all() as $shop) {
            app(NotificationService::class)->retryFailed($shop);
        }
    })->everyThirtyMinutes();
}
```

---

## Best Practices

### 1. Template Design

- **Keep it Simple**: Use clean, responsive HTML
- **Test Across Clients**: Test in Gmail, Outlook, Apple Mail, etc.
- **Plain Text Fallback**: Always provide plain text version
- **Personalization**: Use customer's name and relevant details
- **Clear CTA**: Include clear call-to-action buttons
- **Unsubscribe Link**: Always include unsubscribe option

### 2. Variable Naming

- Use descriptive names: `{customer_name}` not `{name}`
- Be consistent across templates
- Document all available variables
- Provide default values for optional variables

### 3. Error Handling

- Set appropriate retry limits (3-5 retries)
- Monitor failed notifications daily
- Alert on high failure rates (>5%)
- Log errors with context for debugging

### 4. Performance

- Use queue workers (4-8 workers recommended)
- Batch notifications when possible
- Set reasonable timeouts (120 seconds)
- Monitor queue length and processing time

### 5. Compliance

- **GDPR**: Respect user preferences, provide opt-out
- **CAN-SPAM**: Include physical address, clear sender info
- **CASL** (Canada): Get explicit consent for marketing emails
- **Unsubscribe**: Honor unsubscribe requests immediately

### 6. Testing

- Test all templates before enabling
- Use test mode for new templates
- Monitor delivery rates for first 100 sends
- A/B test subject lines and content

---

## Troubleshooting

### Notifications Not Sending

1. **Check Queue Worker**: Ensure queue worker is running
2. **Check Template**: Verify template is active
3. **Check Preferences**: Ensure user has notifications enabled
4. **Check Email Config**: Verify MAIL_* settings in .env
5. **Check Logs**: Review `storage/logs/laravel.log`

### Low Delivery Rate

1. **Verify Email Authentication**: Set up SPF, DKIM, DMARC
2. **Check Sender Reputation**: Use reputable email provider
3. **Avoid Spam Triggers**: Check content for spam keywords
4. **Monitor Bounce Rate**: Clean up invalid email addresses
5. **Warm Up Domain**: Gradually increase sending volume

### Low Open Rate

1. **Improve Subject Lines**: A/B test different approaches
2. **Optimize Send Time**: Test different times of day
3. **Segment Audience**: Personalize content for segments
4. **Mobile Optimization**: Ensure mobile-friendly design
5. **Clean List**: Remove inactive subscribers

### Webhooks Not Working

1. **Verify URL**: Check webhook URL is accessible
2. **Check SSL**: Ensure valid SSL certificate
3. **Verify Secret**: Check webhook secret matches
4. **Check Firewall**: Ensure provider IPs not blocked
5. **Review Logs**: Check webhook payload in logs

---

## Security Considerations

### Data Protection
- All credentials encrypted at rest
- Sensitive data redacted from logs
- HTTPS required for all endpoints
- Rate limiting on public endpoints

### Authentication
- API endpoints require authentication (except tracking)
- Shop-scoped queries prevent cross-tenant access
- User permissions checked via policies

### Email Security
- SPF records configured
- DKIM signing enabled
- DMARC policy set
- Bounce handling implemented

---

## Future Enhancements

### Planned Features (Q1 2026)
- Visual drag-and-drop email builder
- SMS notifications via Twilio
- Push notifications via FCM
- WhatsApp Business integration
- Advanced segmentation and targeting
- A/B testing built-in
- Scheduled sending (send later)
- Email digest mode (daily/weekly summaries)

### Under Consideration
- Multi-language support
- Custom event creation UI
- Template marketplace
- Advanced analytics dashboard
- AI-powered subject line optimization
- Dynamic content blocks
- AMP for Email support

---

## Support

For issues or questions:
- **Documentation**: [https://docs.multichannel.app/notifications](https://docs.multichannel.app/notifications)
- **Support Email**: [support@multichannel.app](mailto:support@multichannel.app)
- **API Reference**: [https://api.multichannel.app/docs](https://api.multichannel.app/docs)

---

**Last Updated**: November 15, 2025
**Version**: 1.0.0
