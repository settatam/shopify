# Real-Time Sales Dashboard

This document provides comprehensive information about the real-time sales dashboard with WebSocket integration for tracking sales across all channels and POS transactions.

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Setup](#setup)
5. [WebSocket Configuration](#websocket-configuration)
6. [Dashboard Features](#dashboard-features)
7. [Filters and Analytics](#filters-and-analytics)
8. [Real-Time Updates](#real-time-updates)
9. [API Reference](#api-reference)
10. [Broadcasting Events](#broadcasting-events)
11. [Troubleshooting](#troubleshooting)

## Overview

The Sales Dashboard provides real-time visibility into your business performance across all sales channels including online marketplaces (Shopify, eBay, Amazon, Walmart, Etsy, BigCommerce, Square) and in-person POS transactions. The dashboard uses Laravel Reverb for WebSocket connections to push live sales updates to connected clients.

### Key Benefits

- **Real-Time Updates**: See new sales instantly without refreshing the page
- **Unified View**: Track sales from all channels and POS in one place
- **Powerful Filters**: Filter by date range, marketplace, shipping status, payment status, and more
- **Visual Analytics**: Charts and graphs for trend analysis
- **Performance Metrics**: Key stats like total revenue, order count, and average order value

## Features

### Implemented Features

1. **Real-Time Dashboard**
   - Live connection indicator
   - Instant new sale notifications
   - Auto-updating stats and charts
   - Browser notifications for new sales

2. **Analytics & Stats**
   - Total orders count
   - Total revenue
   - Average order value
   - Channel vs POS breakdown
   - Sales by source/marketplace

3. **Filtering**
   - Date range selection
   - Marketplace/channel filter
   - Shipping/fulfillment status
   - Payment status
   - Order status
   - Payment method (for POS)

4. **Visualizations**
   - Line chart showing revenue and order trends
   - Automatic grouping (hourly, daily, weekly, monthly)
   - Sales breakdown by source
   - Recent sales table

5. **WebSocket Integration**
   - Laravel Reverb WebSocket server
   - Pusher protocol compatibility
   - Automatic reconnection
   - Shop-specific channels

## Architecture

### Components

```
Backend:
├── app/
│   ├── Events/
│   │   ├── NewSaleEvent.php              # Broadcast new sales
│   │   └── SaleUpdatedEvent.php          # Broadcast sale updates
│   ├── Http/Controllers/Dashboard/
│   │   └── SalesDashboardController.php  # Analytics API
│   └── Observers/
│       └── ChannelOrderObserver.php      # Auto-broadcast channel orders

Frontend:
├── resources/js/Pages/Dashboard/
│   └── SalesDashboard.vue                # Dashboard component

WebSocket:
├── config/
│   ├── broadcasting.php                  # Broadcasting config
│   └── reverb.php                        # Reverb server config
```

### Data Flow

```
New Sale Occurs (POS or Channel Order)
         ↓
Event Dispatched (NewSaleEvent)
         ↓
Reverb WebSocket Server
         ↓
Connected Dashboard Clients
         ↓
UI Updates in Real-Time
```

## Setup

### Prerequisites

1. Laravel 12 application
2. Node.js and npm installed
3. Redis (optional, for scaling)
4. Port 8080 available for WebSocket server

### Installation Steps

#### 1. Install Dependencies

Backend dependencies are already installed via Composer:
```bash
# Laravel Reverb is already installed
composer require laravel/reverb
```

Frontend dependencies:
```bash
npm install chart.js laravel-echo pusher-js
```

#### 2. Configure Environment

Add to your `.env` file:
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=app-id
REVERB_APP_KEY=app-key
REVERB_APP_SECRET=app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**For Production:**
```env
REVERB_HOST=yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https
```

#### 3. Start Reverb Server

In development:
```bash
php artisan reverb:start
```

In production (with supervisor or systemd):
```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

#### 4. Queue Workers

Make sure queue workers are running to process jobs:
```bash
php artisan queue:work
```

## WebSocket Configuration

### Broadcasting Channels

The dashboard uses shop-specific channels to ensure data isolation:

**Channel Format**: `sales.{shop_id}`

Example: `sales.1` for shop with ID 1

### Events

**NewSaleEvent**
- Broadcast when: New sale created (POS or channel order)
- Event name: `new.sale`
- Channel: `sales.{shop_id}`
- Data: Complete sale information

**SaleUpdatedEvent**
- Broadcast when: Sale status/tracking updated
- Event name: `sale.updated`
- Channel: `sales.{shop_id}`
- Data: Sale ID, type, and updated fields

### Connection Testing

Test WebSocket connection:
```bash
# Check if Reverb is running
curl http://localhost:8080

# Monitor connections
php artisan reverb:connections
```

## Dashboard Features

### Stats Cards

1. **Total Orders**
   - Count of all orders in selected date range
   - Includes both channel orders and POS transactions
   - Filters apply

2. **Total Revenue**
   - Sum of all sales revenue
   - Formatted as currency ($)
   - Updates in real-time

3. **Average Order Value**
   - Total revenue ÷ total orders
   - Helpful for understanding customer behavior

4. **Channel / POS Split**
   - Shows breakdown between online channels and POS
   - Helps identify sales channel performance

### Sales Trend Chart

- **Dual-axis line chart**
  - Left axis: Revenue ($)
  - Right axis: Order count
- **Automatic grouping**:
  - 1 day or less: Hourly
  - Up to 31 days: Daily
  - Up to 365 days: Weekly
  - Over 1 year: Monthly
- **Interactive tooltips**
- **Smooth curves** with tension

### Sales by Source Table

Shows breakdown by marketplace/channel:
- Source name (Shopify, eBay, Amazon, etc.)
- Order count
- Total revenue
- Percentage of total revenue
- Visual progress bar

### Recent Sales Table

Last 10 sales with:
- Order number
- Source (with colored badge)
- Customer name
- Amount
- Status (with colored badge)
- Relative time (e.g., "5 minutes ago")

## Filters and Analytics

### Available Filters

#### Date Range
- **Start Date**: Beginning of date range
- **End Date**: End of date range
- **Default**: Last 30 days

#### Marketplace
- All Channels (default)
- Shopify
- eBay
- Amazon
- Walmart
- Etsy
- BigCommerce
- Square POS

#### Shipping Status
- All Statuses (default)
- Unfulfilled
- Partially Fulfilled
- Fulfilled
- Shipped
- Delivered

#### Payment Status
- All Statuses (default)
- Pending
- Paid
- Refunded
- Partially Refunded

#### Order Status
- All Statuses (default)
- Pending
- Processing
- Completed
- Cancelled

#### Payment Method (POS only)
- All Methods (default)
- Cash
- Check
- Card (Dejavoo)

### Filter Behavior

- Filters are applied immediately on change
- Chart and stats update automatically
- Filters persist during the session
- Combine multiple filters for detailed analysis

## Real-Time Updates

### How It Works

1. **Sale Created**:
   - POS transaction created → `NewSaleEvent` dispatched
   - Channel order imported → `ChannelOrderObserver` dispatches event
   - Dejavoo card payment → Event dispatched on success

2. **WebSocket Broadcast**:
   - Event sent to Reverb server
   - Reverb pushes to all connected clients on `sales.{shop_id}` channel

3. **Dashboard Updates**:
   - Stats increment (orders, revenue)
   - New sale appears at top of recent sales
   - Chart data updated
   - Browser notification shown (if enabled)

### Live Connection Indicator

- **Green "Live"**: WebSocket connected
- **Gray "Offline"**: WebSocket disconnected

### Browser Notifications

The dashboard requests notification permission on load. When granted:
- Shows OS-level notification for each new sale
- Includes order number and amount
- Works even when browser tab is not active

**Enable Notifications:**
```javascript
// Automatically requested on dashboard load
// User must grant permission in browser
```

## API Reference

### Dashboard Data Endpoint

```
GET /api/dashboard/sales
```

**Authentication**: Required (Sanctum token)

**Query Parameters:**
| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| start_date | date | Start of date range | 30 days ago |
| end_date | date | End of date range | Today |
| channel_type | string | Filter by channel | all |
| fulfillment_status | string | Filter by shipping status | all |
| payment_status | string | Filter by payment status | all |
| order_status | string | Filter by order status | all |
| payment_method | string | Filter by payment method | all |

**Response:**
```json
{
  "stats": {
    "total_orders": 150,
    "total_revenue": 12450.50,
    "avg_order_value": 83.00,
    "channel_orders": 120,
    "pos_transactions": 30
  },
  "chart_data": [
    {
      "period": "Nov 15, 2025",
      "orders": 25,
      "revenue": 2100.00
    }
  ],
  "recent_sales": [
    {
      "id": 123,
      "type": "channel_order",
      "source": "shopify",
      "order_number": "ORD-12345",
      "total": 99.99,
      "currency": "USD",
      "customer_name": "John Doe",
      "customer_email": "john@example.com",
      "status": "completed",
      "fulfillment_status": "fulfilled",
      "payment_status": "paid",
      "items_count": 3,
      "created_at": "2025-11-15T14:30:00Z",
      "created_at_human": "5 minutes ago"
    }
  ],
  "breakdown": [
    {
      "source": "Shopify",
      "count": 50,
      "revenue": 4500.00
    }
  ]
}
```

## Broadcasting Events

### NewSaleEvent

**Purpose**: Broadcast when a new sale is created

**Dispatched From**:
- `POSController::createTransaction()` - After POS sale
- `DejavooPaymentController::processPayment()` - After card payment
- `ChannelOrderObserver::created()` - When channel order imported

**Event Data:**
```php
[
    'sale' => [
        'id' => 123,
        'type' => 'channel_order', // or 'pos_transaction'
        'source' => 'shopify',     // or 'POS'
        'order_number' => 'ORD-12345',
        'total' => 99.99,
        'currency' => 'USD',
        'customer_name' => 'John Doe',
        'customer_email' => 'john@example.com',
        'status' => 'completed',
        'fulfillment_status' => 'fulfilled',
        'payment_status' => 'paid',
        'items_count' => 3,
        'created_at' => '2025-11-15T14:30:00Z'
    ],
    'timestamp' => '2025-11-15T14:30:52Z'
]
```

**Frontend Listener:**
```javascript
echo.channel(`sales.${shopId}`)
    .listen('.new.sale', (e) => {
        console.log('New sale:', e.sale);
        // Update dashboard...
    });
```

### SaleUpdatedEvent

**Purpose**: Broadcast when sale is updated (tracking, status, etc.)

**Event Data:**
```php
[
    'sale_id' => 123,
    'sale_type' => 'channel_order',
    'updates' => [
        'status' => 'shipped',
        'tracking_number' => 'TRACK123'
    ],
    'timestamp' => '2025-11-15T14:35:00Z'
]
```

## Troubleshooting

### WebSocket Not Connecting

**Problem**: Dashboard shows "Offline", no real-time updates

**Solutions:**

1. **Check Reverb is running**:
   ```bash
   php artisan reverb:start
   ```

2. **Verify environment variables**:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

3. **Check port availability**:
   ```bash
   netstat -an | grep 8080
   ```

4. **Check browser console** for connection errors

5. **Verify .env matches vite config**:
   ```env
   VITE_REVERB_HOST="${REVERB_HOST}"
   VITE_REVERB_PORT="${REVERB_PORT}"
   ```

6. **Rebuild frontend assets**:
   ```bash
   npm run build
   ```

### No Data Showing

**Problem**: Dashboard loads but shows zero stats

**Solutions:**

1. **Check date range** - Ensure you have sales in selected dates

2. **Verify filters** - Try "All" on all filter dropdowns

3. **Check database**:
   ```sql
   SELECT COUNT(*) FROM channel_orders;
   SELECT COUNT(*) FROM pos_transactions WHERE status != 'voided';
   ```

4. **Check API response**:
   ```bash
   curl -H "Authorization: Bearer TOKEN" \
        http://localhost/api/dashboard/sales
   ```

### Events Not Broadcasting

**Problem**: Sales created but dashboard doesn't update

**Solutions:**

1. **Verify BROADCAST_CONNECTION**:
   ```env
   BROADCAST_CONNECTION=reverb
   ```

2. **Check queue is running**:
   ```bash
   php artisan queue:work
   ```

3. **Test event manually**:
   ```php
   use App\Events\NewSaleEvent;

   NewSaleEvent::fromPosTransaction($transaction)->dispatch();
   ```

4. **Check Reverb logs**:
   ```bash
   php artisan reverb:start --debug
   ```

5. **Verify shop ID in channel**:
   ```javascript
   console.log('Listening on:', `sales.${shopId}`);
   ```

### Chart Not Rendering

**Problem**: Stats show but chart is blank

**Solutions:**

1. **Check console** for Chart.js errors

2. **Verify chart data format**:
   ```javascript
   console.log('Chart data:', chartData.value);
   ```

3. **Ensure Chart.js is imported**:
   ```javascript
   import { Chart, registerables } from 'chart.js';
   Chart.register(...registerables);
   ```

4. **Check canvas element exists**:
   ```javascript
   console.log('Canvas:', chartCanvas.value);
   ```

### Performance Issues

**Problem**: Dashboard slow with many sales

**Solutions:**

1. **Limit date range** - Shorter ranges load faster

2. **Add database indexes**:
   ```sql
   CREATE INDEX idx_channel_orders_shop_created
   ON channel_orders(shop_id, created_at);

   CREATE INDEX idx_pos_transactions_shop_created
   ON pos_transactions(shop_id, created_at);
   ```

3. **Use Redis for scaling**:
   ```env
   REVERB_SCALING_ENABLED=true
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   ```

4. **Implement caching** for breakdown data

## Best Practices

### WebSocket Server

1. **Use Supervisor** in production to keep Reverb running:
   ```ini
   [program:reverb]
   command=php artisan reverb:start
   autostart=true
   autorestart=true
   ```

2. **Enable TLS** in production for secure WebSocket (wss://):
   ```env
   REVERB_SCHEME=https
   REVERB_PORT=443
   ```

3. **Monitor connections**:
   ```bash
   php artisan reverb:connections
   ```

### Dashboard Usage

1. **Use appropriate date ranges** - Smaller ranges = faster performance

2. **Combine filters** for detailed analysis

3. **Enable browser notifications** for real-time alerts

4. **Refresh periodically** if WebSocket disconnects

### Development

1. **Run Reverb in debug mode** during development:
   ```bash
   php artisan reverb:start --debug
   ```

2. **Use separate terminals** for Reverb and queue workers

3. **Clear config cache** after changing .env:
   ```bash
   php artisan config:clear
   ```

## Security

### Channel Authorization

Currently using public channels (`Channel` instead of `PrivateChannel`). For production, consider private channels:

```php
// In routes/channels.php
Broadcast::channel('sales.{shopId}', function ($user, $shopId) {
    return $user->shop_id === (int) $shopId;
});
```

Then update events to use `PrivateChannel`:
```php
return [
    new PrivateChannel('sales.' . $this->shopId),
];
```

### API Protection

- Dashboard API uses Sanctum authentication
- Requires valid user token
- Shop isolation enforced in controller

### Data Privacy

- Only shop-specific data is broadcast
- No sensitive payment information in events
- PCI-compliant for card data (stored separately)

## Production Deployment

### Checklist

- [ ] Configure Reverb with TLS/SSL
- [ ] Set up Supervisor for Reverb process
- [ ] Configure queue workers
- [ ] Set proper CORS headers
- [ ] Use production Reverb credentials
- [ ] Enable Redis scaling if needed
- [ ] Monitor WebSocket connections
- [ ] Set up logging and error tracking
- [ ] Test WebSocket through firewall
- [ ] Configure load balancer for WebSocket support

### Example Nginx Configuration

```nginx
location /reverb {
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    proxy_pass http://localhost:8080;

    proxy_connect_timeout 7d;
    proxy_send_timeout 7d;
    proxy_read_timeout 7d;
}
```

---

**Last Updated**: November 15, 2025
**Dashboard Version**: 1.0.0
**Laravel Reverb Version**: 1.6.1
**Chart.js Version**: ^4.0.0
