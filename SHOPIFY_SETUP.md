# Shopmata Multichannel - Shopify App Setup Guide

This guide will walk you through setting up your multichannel sales and inventory management system as a Shopify app.

## Overview

Shopmata Multichannel is a comprehensive inventory management and sales platform that allows you to:
- Manage inventory across multiple sales channels (Shopify, Amazon, eBay, Walmart, etc.)
- Sync products, orders, and inventory automatically
- Track stock across multiple warehouse locations
- Handle pricing variations per channel
- Process orders from multiple marketplaces in one place

## Prerequisites

1. **PHP Requirements:**
   - PHP 8.2 or higher
   - Required PHP extensions:
     - PDO (for database access)
     - pdo_sqlite or pdo_mysql
     - curl
     - mbstring
     - xml
     - json
     - openssl
     - bcmath

2. **Composer** - PHP dependency manager ([Install Composer](https://getcomposer.org/))

3. **Node.js & NPM** - For frontend assets ([Install Node.js](https://nodejs.org/))

4. **Shopify Partner Account** - To create Shopify apps ([Sign up](https://partners.shopify.com/))

## Step 1: Create a Shopify App

1. Go to your [Shopify Partner Dashboard](https://partners.shopify.com/)
2. Click **Apps** in the left sidebar
3. Click **Create app**
4. Choose **Public app** or **Custom app** based on your needs
5. Fill in the app details:
   - **App name**: Shopmata Multichannel (or your preferred name)
   - **App URL**: `https://your-domain.com/app` (where your app will be hosted)
   - **Allowed redirection URL(s)**: `https://your-domain.com/shopify/auth/callback`

6. Once created, note down these credentials:
   - **API key** (also called Client ID)
   - **API secret key** (also called Client Secret)

## Step 2: Configure App Scopes

In your Shopify app settings, configure the following OAuth scopes:

### Required Scopes:
- `read_products` - Read product data
- `write_products` - Create/update products
- `read_orders` - Read order data
- `write_orders` - Create/update orders (for syncing orders back to Shopify)
- `read_inventory` - Read inventory levels
- `write_inventory` - Update inventory levels
- `read_locations` - Read location data

### Optional (but recommended):
- `read_fulfillments` - Track order fulfillment
- `write_fulfillments` - Update fulfillment status
- `read_assigned_fulfillment_orders` - For advanced fulfillment
- `write_assigned_fulfillment_orders` - For advanced fulfillment

## Step 3: Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

## Step 4: Environment Configuration

1. Copy the `.env.example` to `.env` if not already done:
   ```bash
   cp .env.example .env
   ```

2. Update the following environment variables in `.env`:

```env
# Application Settings
APP_NAME="Shopmata Multichannel"
APP_ENV=production  # or 'local' for development
APP_DEBUG=false  # Set to true only in development
APP_URL=https://your-domain.com

# Generate a new key if empty
APP_KEY=base64:your-generated-key-here

# Database Configuration
# For SQLite (recommended for small-medium deployments):
DB_CONNECTION=sqlite

# For MySQL (recommended for production):
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=shopmata
# DB_USERNAME=your_db_user
# DB_PASSWORD=your_db_password

# Shopify App Configuration
SHOPIFY_API_KEY=your_shopify_api_key_from_step_1
SHOPIFY_API_SECRET=your_shopify_api_secret_from_step_1
SHOPIFY_APP_SCOPES="read_products,write_products,read_orders,write_orders,read_inventory,write_inventory,read_locations"
SHOPIFY_APP_URL=https://your-domain.com
SHOPIFY_WEBHOOK_SECRET=your_shopify_api_secret_from_step_1
SHOPIFY_BILLING_ENABLED=false  # Set to true if you want to charge users

# Queue Configuration (for background jobs)
QUEUE_CONNECTION=database  # or 'redis' for production
```

3. Generate application key:
   ```bash
   php artisan key:generate
   ```

## Step 5: Database Setup

1. Create the database file (for SQLite):
   ```bash
   touch database/database.sqlite
   ```

   Or create a MySQL database (for MySQL):
   ```bash
   mysql -u root -p -e "CREATE DATABASE shopmata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

2. Run migrations:
   ```bash
   php artisan migrate
   ```

3. (Optional) Install Laravel Passport for API authentication:
   ```bash
   php artisan passport:install
   ```

## Step 6: Build Frontend Assets

```bash
# Development
npm run dev

# Production
npm run build
```

## Step 7: Configure Web Server

### For Nginx:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /path/to/shopmata/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### For Apache:

The `.htaccess` file in the `public` directory should handle routing automatically.

## Step 8: Set Up SSL Certificate

Shopify requires all apps to use HTTPS. Use Let's Encrypt for free SSL:

```bash
# Install certbot
sudo apt-get install certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d your-domain.com
```

## Step 9: Configure Queue Worker (Production)

For processing background jobs (inventory sync, order processing):

1. Create a supervisor configuration `/etc/supervisor/conf.d/shopmata-worker.conf`:

```ini
[program:shopmata-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/shopmata/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/shopmata/storage/logs/worker.log
stopwaitsecs=3600
```

2. Start the worker:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start shopmata-worker:*
```

## Step 10: Install the App

1. Go to your Shopify Partner Dashboard
2. Select your app
3. Click on **Test on development store** or share the installation URL
4. Installation URL format: `https://your-domain.com/shopify/install`

5. When merchants visit the install URL, they will:
   - Be prompted to enter their Shopify store domain
   - Be redirected to Shopify for OAuth authorization
   - Grant the requested permissions
   - Be redirected back to your app home page

## Testing the Installation

1. Visit: `https://your-domain.com/shopify/install`
2. Enter a test store domain (e.g., `your-test-store.myshopify.com`)
3. Authorize the app
4. You should be redirected to: `https://your-domain.com/app?shop=your-test-store.myshopify.com`

## Webhooks

The app automatically registers these webhooks during installation:
- `products/update` - Syncs product changes from Shopify
- `inventory_levels/update` - Syncs inventory changes
- `app/uninstalled` - Handles app uninstallation

Webhook endpoint: `https://your-domain.com/shopify/webhooks`

## Adding Other Sales Channels

### Amazon
1. Get Amazon SP-API credentials from Amazon Seller Central
2. Update `.env` with Amazon credentials:
```env
AMZ_LWA_CLIENT_ID=your_amazon_client_id
AMZ_LWA_CLIENT_SECRET=your_amazon_client_secret
AMZ_AWS_ACCESS_KEY_ID=your_aws_key
AMZ_AWS_SECRET_ACCESS_KEY=your_aws_secret
AMZ_REGION=us-east-1
```

### eBay
1. Create eBay Developer account and app
2. Update `.env` with eBay credentials:
```env
EBAY_CLIENT_ID=your_ebay_client_id
EBAY_CLIENT_SECRET=your_ebay_client_secret
EBAY_REDIRECT_URI=https://your-domain.com/ebay/callback
EBAY_ENV=production  # or 'sandbox' for testing
```

## Troubleshooting

### Issue: "could not find driver" when running migrations
**Solution**: Install PHP PDO extension for your database:
```bash
# For SQLite
sudo apt-get install php8.2-sqlite3

# For MySQL
sudo apt-get install php8.2-mysql

# Restart PHP-FPM
sudo service php8.2-fpm restart
```

### Issue: HMAC validation failed
**Solution**: Ensure `SHOPIFY_API_SECRET` in `.env` matches your app's API secret key exactly

### Issue: Webhooks not working
**Solution**:
1. Check that webhook endpoint is accessible: `https://your-domain.com/shopify/webhooks`
2. Verify `SHOPIFY_WEBHOOK_SECRET` is set correctly
3. Check logs: `tail -f storage/logs/laravel.log`

### Issue: App loads blank page
**Solution**:
1. Clear cache: `php artisan cache:clear && php artisan config:clear`
2. Check file permissions: `chmod -R 775 storage bootstrap/cache`
3. Rebuild frontend: `npm run build`

## Architecture Overview

### Database Tables

- **shops** - Stores Shopify store connections
- **products** - Master product catalog
- **product_variants** - Product variants/SKUs
- **channels** - Connected sales channels (Amazon, eBay, etc.)
- **channel_listings** - Products listed on each channel
- **channel_listing_variants** - Variant-level channel data
- **locations** - Warehouse/inventory locations
- **stock_items** - Inventory per location per variant
- **channel_orders** - Orders from all channels
- **channel_order_items** - Order line items

### Key Features

1. **Multi-location Inventory**: Track stock across warehouses
2. **Channel-specific Pricing**: Set different prices per channel
3. **Automated Sync**: Jobs sync inventory, prices, and orders
4. **Feed Management**: Track Amazon feed submissions
5. **Order Fulfillment**: Process orders from all channels

## Security Best Practices

1. Always use HTTPS in production
2. Keep `APP_DEBUG=false` in production
3. Regularly update dependencies: `composer update`
4. Use strong database passwords
5. Enable rate limiting on API routes
6. Monitor logs for suspicious activity
7. Keep `.env` file secure and never commit it to version control

## Support & Documentation

- Laravel Documentation: https://laravel.com/docs
- Shopify App Documentation: https://shopify.dev/docs/apps
- Amazon SP-API Documentation: https://developer-docs.amazon.com/sp-api/
- eBay API Documentation: https://developer.ebay.com/

## License

This application is proprietary software. All rights reserved.
