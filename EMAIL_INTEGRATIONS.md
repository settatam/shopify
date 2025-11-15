# Email Integrations: SendGrid & Mailchimp

This document describes the SendGrid and Mailchimp integrations for transactional emails and marketing campaigns.

## Table of Contents

- [Overview](#overview)
- [SendGrid Integration](#sendgrid-integration)
- [Mailchimp Integration](#mailchimp-integration)
- [Database Schema](#database-schema)
- [API Endpoints](#api-endpoints)
- [Usage Examples](#usage-examples)
- [Best Practices](#best-practices)

---

## Overview

The platform supports two powerful email integrations:

1. **SendGrid** - For reliable transactional email delivery
   - Order confirmations
   - Password resets
   - Notification emails
   - System alerts

2. **Mailchimp** - For marketing automation and campaigns
   - Email newsletters
   - Marketing campaigns
   - Subscriber management
   - Campaign analytics

### Key Features

- **Encrypted API Keys** - All credentials stored encrypted in database
- **Multi-Provider Support** - Use SendGrid for transactional, Mailchimp for marketing
- **Automatic Sync** - Subscribers auto-synced with Mailchimp audiences
- **Campaign Management** - Create, schedule, and track marketing campaigns
- **Detailed Analytics** - Track opens, clicks, bounces, and unsubscribes
- **Shop-Scoped** - Each shop has its own configuration

---

## SendGrid Integration

SendGrid provides reliable transactional email delivery with excellent deliverability rates.

### Configuration

#### 1. Get SendGrid API Key

1. Sign up at [SendGrid.com](https://sendgrid.com)
2. Navigate to Settings → API Keys
3. Create a new API key with "Mail Send" permissions
4. Copy the API key (you won't be able to see it again)

#### 2. Configure in Application

```bash
POST /api/sendgrid/configure
```

**Request:**
```json
{
  "api_key": "SG.xxx...",
  "from_email": "noreply@yourstore.com",
  "from_name": "Your Store Name",
  "reply_to": "support@yourstore.com",
  "is_primary": true
}
```

**Response:**
```json
{
  "message": "SendGrid configured successfully",
  "settings": {
    "is_active": true,
    "is_primary": true,
    "from_email": "noreply@yourstore.com",
    "from_name": "Your Store Name",
    "reply_to": "support@yourstore.com"
  }
}
```

### Sending Emails

#### Via Service Class

```php
use App\Services\SendGridService;

$sendGridService = app(SendGridService::class);

$success = $sendGridService->send(
    shop: $shop,
    to: 'customer@example.com',
    subject: 'Order Confirmation',
    htmlContent: '<h1>Thank you for your order!</h1>',
    textContent: 'Thank you for your order!'
);
```

#### Bulk Email

```php
$recipients = [
    'customer1@example.com' => 'John Doe',
    'customer2@example.com' => 'Jane Smith',
];

$result = $sendGridService->sendBulk(
    shop: $shop,
    recipients: $recipients,
    subject: 'Weekly Newsletter',
    htmlContent: $htmlContent,
    textContent: $textContent
);
```

### Testing Configuration

```bash
POST /api/sendgrid/test
```

**Request:**
```json
{
  "test_email": "your@email.com"
}
```

### Statistics

```bash
GET /api/sendgrid/statistics?start_date=2025-01-01&end_date=2025-01-31
```

**Response:**
```json
{
  "statistics": [
    {
      "date": "2025-01-15",
      "stats": {
        "blocks": 0,
        "bounce_drops": 0,
        "bounces": 2,
        "clicks": 145,
        "deferred": 0,
        "delivered": 998,
        "invalid_emails": 0,
        "opens": 432,
        "processed": 1000,
        "requests": 1000,
        "spam_report_drops": 0,
        "spam_reports": 1,
        "unique_clicks": 98,
        "unique_opens": 287,
        "unsubscribe_drops": 0,
        "unsubscribes": 3
      }
    }
  ]
}
```

---

## Mailchimp Integration

Mailchimp provides marketing automation, subscriber management, and campaign analytics.

### Configuration

#### 1. Get Mailchimp API Key

1. Sign up at [Mailchimp.com](https://mailchimp.com)
2. Navigate to Account → Extras → API Keys
3. Create a new API key
4. Note the server prefix (e.g., `us1`, `us2`) from your account URL

#### 2. Create an Audience

1. In Mailchimp, go to Audience → All contacts
2. Create a new audience if you don't have one
3. Copy the Audience ID from Settings

#### 3. Configure in Application

```bash
POST /api/mailchimp/configure
```

**Request:**
```json
{
  "api_key": "xxxxx-us1",
  "default_audience_id": "abc123def4",
  "from_email": "marketing@yourstore.com",
  "from_name": "Your Store",
  "reply_to": "support@yourstore.com"
}
```

### Managing Subscribers

#### Add Subscriber

```bash
POST /api/mailchimp/subscribers
```

**Request:**
```json
{
  "email": "customer@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "status": "subscribed",
  "tags": ["customer", "purchased"],
  "merge_fields": {
    "PHONE": "+1234567890",
    "BIRTHDAY": "01/15"
  }
}
```

#### Unsubscribe

```bash
POST /api/mailchimp/subscribers/unsubscribe
```

**Request:**
```json
{
  "email": "customer@example.com"
}
```

#### Sync Subscribers

Sync all subscribers from Mailchimp to local database:

```bash
POST /api/mailchimp/subscribers/sync
```

**Response:**
```json
{
  "message": "Synced 1,234 subscribers",
  "count": 1234
}
```

#### Get Subscribers

```bash
GET /api/mailchimp/subscribers?status=subscribed&search=john
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "email": "john@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "status": "subscribed",
      "tags": ["customer"],
      "subscribed_at": "2025-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 1234
  }
}
```

### Campaign Management

#### Create Campaign

```bash
POST /api/mailchimp/campaigns
```

**Request:**
```json
{
  "name": "Weekly Newsletter - Jan 15",
  "subject": "This Week's Hot Deals!",
  "preview_text": "Don't miss out on our biggest sale yet",
  "html_content": "<html>...</html>",
  "audience_id": "abc123def4"
}
```

**Response:**
```json
{
  "message": "Campaign created successfully",
  "campaign": {
    "id": 1,
    "provider_campaign_id": "abc123",
    "name": "Weekly Newsletter - Jan 15",
    "subject": "This Week's Hot Deals!",
    "status": "draft",
    "created_at": "2025-01-15T10:00:00Z"
  }
}
```

#### Send Campaign

```bash
POST /api/mailchimp/campaigns/{campaign}/send
```

**Response:**
```json
{
  "message": "Campaign sent successfully",
  "campaign": {
    "id": 1,
    "status": "sent",
    "sent_at": "2025-01-15T11:00:00Z"
  }
}
```

#### Schedule Campaign

```bash
POST /api/mailchimp/campaigns/{campaign}/schedule
```

**Request:**
```json
{
  "scheduled_at": "2025-01-20T10:00:00Z"
}
```

#### Get Campaign Statistics

```bash
GET /api/mailchimp/campaigns/{campaign}/statistics
```

**Response:**
```json
{
  "statistics": {
    "emails_sent": 1000,
    "opens": {
      "opens_total": 432,
      "unique_opens": 287,
      "open_rate": 28.7
    },
    "clicks": {
      "clicks_total": 145,
      "unique_subscriber_clicks": 98,
      "click_rate": 9.8
    },
    "bounces": {
      "hard_bounces": 1,
      "soft_bounces": 1
    },
    "unsubscribed": 3
  },
  "campaign": {
    "id": 1,
    "emails_sent": 1000,
    "opens": 432,
    "unique_opens": 287,
    "clicks": 145,
    "unique_clicks": 98,
    "open_rate": 28.70,
    "click_rate": 9.80
  }
}
```

---

## Database Schema

### email_provider_settings

Stores configuration for email providers (SendGrid, Mailchimp, etc.).

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| shop_id | bigint | FK to shops |
| provider | enum | sendgrid, mailchimp, ses, mailgun, postmark |
| is_active | boolean | Provider is enabled |
| is_primary | boolean | Primary provider for transactional emails |
| api_key | text | Encrypted API key |
| api_secret | text | Encrypted API secret (if needed) |
| server_prefix | string | Mailchimp server (us1, us2, etc.) |
| from_email | string | Default from email |
| from_name | string | Default from name |
| reply_to | string | Default reply-to email |
| default_audience_id | string | Mailchimp default audience/list ID |
| audience_ids | json | Multiple audience IDs |
| double_optin | boolean | Mailchimp double opt-in |
| emails_sent_today | integer | Counter |
| emails_sent_month | integer | Counter |
| total_emails_sent | integer | Counter |
| last_sync_at | timestamp | Last sync with provider |
| last_email_sent_at | timestamp | Last email sent |

### mailchimp_subscribers

Stores subscribers synced from Mailchimp.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| shop_id | bigint | FK to shops |
| user_id | bigint | FK to users (if linked) |
| mailchimp_id | string | Subscriber hash from Mailchimp |
| email | string | Email address |
| audience_id | string | Mailchimp audience ID |
| first_name | string | First name |
| last_name | string | Last name |
| phone | string | Phone number |
| status | enum | subscribed, unsubscribed, pending, cleaned, transactional |
| tags | json | Tags array |
| merge_fields | json | Custom fields |
| email_marketing | boolean | Opted into email |
| sms_marketing | boolean | Opted into SMS |
| subscribed_at | timestamp | When subscribed |
| unsubscribed_at | timestamp | When unsubscribed |
| last_synced_at | timestamp | Last sync |
| sync_status | string | pending, synced, error |
| sync_error | text | Error message if sync failed |

### email_campaigns

Stores marketing campaigns.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| shop_id | bigint | FK to shops |
| created_by | bigint | FK to users |
| provider | enum | mailchimp, sendgrid, custom |
| provider_campaign_id | string | External campaign ID |
| name | string | Campaign name |
| subject | string | Email subject |
| preview_text | text | Preview text |
| content_html | text | HTML content |
| content_text | text | Plain text content |
| audience_id | string | Target audience/list ID |
| segment_criteria | json | Filtering criteria |
| recipient_count | integer | Number of recipients |
| from_email | string | From email |
| from_name | string | From name |
| reply_to | string | Reply-to email |
| status | enum | draft, scheduled, sending, sent, cancelled, failed |
| scheduled_at | timestamp | When to send |
| sent_at | timestamp | When sent |
| emails_sent | integer | Emails sent |
| opens | integer | Total opens |
| unique_opens | integer | Unique opens |
| clicks | integer | Total clicks |
| unique_clicks | integer | Unique clicks |
| bounces | integer | Bounces |
| unsubscribes | integer | Unsubscribes |
| open_rate | decimal | Open rate % |
| click_rate | decimal | Click rate % |

---

## API Endpoints

### SendGrid

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/sendgrid/configuration | Get current configuration |
| POST | /api/sendgrid/configure | Configure SendGrid |
| POST | /api/sendgrid/test | Send test email |
| POST | /api/sendgrid/send-test-email | Send custom test email |
| PUT | /api/sendgrid/settings | Update settings |
| GET | /api/sendgrid/statistics | Get email statistics |
| DELETE | /api/sendgrid/disconnect | Disconnect SendGrid |

### Mailchimp

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/mailchimp/configuration | Get current configuration |
| POST | /api/mailchimp/configure | Configure Mailchimp |
| DELETE | /api/mailchimp/disconnect | Disconnect Mailchimp |
| GET | /api/mailchimp/audiences | Get all audiences |
| GET | /api/mailchimp/audiences/count | Get subscriber count |
| GET | /api/mailchimp/subscribers | Get all subscribers |
| POST | /api/mailchimp/subscribers | Add subscriber |
| POST | /api/mailchimp/subscribers/unsubscribe | Unsubscribe email |
| POST | /api/mailchimp/subscribers/sync | Sync from Mailchimp |
| GET | /api/mailchimp/campaigns | Get all campaigns |
| POST | /api/mailchimp/campaigns | Create campaign |
| GET | /api/mailchimp/campaigns/{id} | Get single campaign |
| POST | /api/mailchimp/campaigns/{id}/send | Send campaign |
| POST | /api/mailchimp/campaigns/{id}/schedule | Schedule campaign |
| GET | /api/mailchimp/campaigns/{id}/statistics | Get campaign stats |

---

## Usage Examples

### Sending Order Confirmation via SendGrid

```php
use App\Services\SendGridService;

class OrderController extends Controller
{
    public function placeOrder(Request $request, SendGridService $sendGrid)
    {
        $order = Order::create([...]);

        // Send confirmation email
        $sendGrid->send(
            shop: auth()->user()->shop,
            to: $order->customer_email,
            subject: "Order #{$order->number} Confirmed",
            htmlContent: view('emails.order-confirmation', ['order' => $order])->render(),
            customArgs: [
                'order_id' => $order->id,
                'order_number' => $order->number,
            ]
        );

        return response()->json(['order' => $order]);
    }
}
```

### Auto-Subscribe Customers to Mailchimp

```php
use App\Services\MailchimpService;

class CustomerController extends Controller
{
    public function register(Request $request, MailchimpService $mailchimp)
    {
        $user = User::create([...]);

        // Auto-subscribe to marketing list
        if ($request->boolean('email_marketing')) {
            $mailchimp->addSubscriber(
                shop: auth()->user()->shop,
                email: $user->email,
                firstName: $user->first_name,
                lastName: $user->last_name,
                status: 'subscribed',
                tags: ['customer', 'new-signup']
            );
        }

        return response()->json(['user' => $user]);
    }
}
```

### Creating and Sending a Newsletter

```php
use App\Services\MailchimpService;

class NewsletterController extends Controller
{
    public function sendNewsletter(Request $request, MailchimpService $mailchimp)
    {
        $shop = auth()->user()->shop;

        // Create campaign
        $campaign = $mailchimp->createCampaign(
            shop: $shop,
            creator: auth()->user(),
            name: 'Weekly Newsletter - ' . now()->format('M d'),
            subject: $request->subject,
            htmlContent: $request->html_content,
            previewText: $request->preview_text
        );

        // Send immediately or schedule
        if ($request->has('send_now') && $request->send_now) {
            $mailchimp->sendCampaign($campaign);
        } else {
            $mailchimp->scheduleCampaign($campaign, new DateTime($request->scheduled_at));
        }

        return response()->json(['campaign' => $campaign]);
    }
}
```

---

## Best Practices

### SendGrid

1. **Use Templates** - Create reusable email templates in SendGrid
2. **Track Everything** - Enable click and open tracking
3. **Warm Up IP** - If using dedicated IP, warm it up gradually
4. **Monitor Reputation** - Check your sender reputation regularly
5. **List Hygiene** - Remove bounced and invalid emails
6. **SPF & DKIM** - Configure proper email authentication
7. **Rate Limiting** - Respect SendGrid rate limits (varies by plan)

### Mailchimp

1. **Double Opt-In** - Use double opt-in to ensure quality subscribers
2. **Segment Lists** - Create segments for targeted campaigns
3. **A/B Testing** - Test subject lines and content
4. **Clean Lists** - Regularly remove unengaged subscribers
5. **Automation** - Set up welcome series and drip campaigns
6. **Personalization** - Use merge fields for personalized emails
7. **Mobile Optimize** - Ensure emails look good on mobile devices
8. **Compliance** - Follow CAN-SPAM and GDPR requirements

### General

1. **Encrypt Keys** - API keys are automatically encrypted
2. **Test First** - Always test emails before sending to customers
3. **Monitor Metrics** - Track open rates, click rates, and bounces
4. **Backup Plan** - Have a fallback provider configured
5. **Permissions** - Use role-based access for campaign management
6. **Automation** - Automate subscriber sync and campaign reporting
7. **Error Handling** - Log all email send failures for debugging

---

## Troubleshooting

### SendGrid Not Sending

1. Check API key is valid: `POST /api/sendgrid/test`
2. Verify from email is authenticated in SendGrid
3. Check SendGrid dashboard for blocked emails
4. Review error logs for detailed error messages
5. Ensure sender authentication (SPF/DKIM) is configured

### Mailchimp Sync Issues

1. Verify API key and server prefix are correct
2. Check audience ID exists in Mailchimp
3. Review sync_error column for specific errors
4. Ensure Mailchimp account is in good standing
5. Check for rate limiting (3000 requests per 10 seconds)

### Subscriber Not Receiving Emails

1. Check subscriber status is "subscribed"
2. Verify email is not in unsubscribe list
3. Check campaign segment criteria
4. Review Mailchimp campaign reports for bounces
5. Ensure subscriber hasn't marked as spam

---

## Security Considerations

1. **API Keys** - Stored encrypted using Laravel's Crypt
2. **HTTPS Only** - All API communication over HTTPS
3. **Shop Scoping** - Users can only access their shop's data
4. **Permission Checks** - Authorization on all endpoints
5. **Rate Limiting** - Implement rate limiting on API endpoints
6. **Audit Trail** - Log all configuration changes
7. **Webhook Verification** - Verify webhook signatures (future enhancement)

---

## Summary

The email integrations provide:

- **SendGrid** for reliable transactional email delivery
- **Mailchimp** for marketing automation and campaigns
- **Encrypted** API key storage
- **Automatic** subscriber synchronization
- **Campaign** creation and scheduling
- **Detailed** analytics and reporting
- **Shop-scoped** configuration
- **Permission-based** access control

Use SendGrid for transactional emails (orders, passwords, notifications) and Mailchimp for marketing campaigns (newsletters, promotions, announcements).
