# Pricing Intelligence & Automated Pricing System

This document provides comprehensive information about the Pricing Intelligence & Automated Pricing system that enables competitive price monitoring and fully automated dynamic pricing.

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Setup](#setup)
5. [Competitor Tracking](#competitor-tracking)
6. [Pricing Rules](#pricing-rules)
7. [Automated Pricing](#automated-pricing)
8. [Price Monitoring](#price-monitoring)
9. [Analytics & Insights](#analytics--insights)
10. [API Usage](#api-usage)
11. [Best Practices](#best-practices)
12. [Troubleshooting](#troubleshooting)

## Overview

The Pricing Intelligence system provides:
- **Competitor Price Tracking**: Monitor competitor prices across multiple marketplaces
- **Automated Price Scraping**: Automatically fetch competitor prices on schedule
- **Pricing Rules Engine**: Define sophisticated pricing strategies
- **Fully Automated Pricing**: Automatically adjust prices based on rules
- **Price History & Analytics**: Track price changes and performance
- **Competitive Analysis**: Understand market positioning
- **Price Suggestions**: AI-driven price recommendations with approval workflow

### Key Benefits

- **Stay Competitive**: Always know where you stand vs competitors
- **Maximize Profits**: Optimize prices based on market conditions
- **Save Time**: Automate pricing decisions across thousands of products
- **Data-Driven**: Make pricing decisions based on real market data
- **Flexible Rules**: Create custom pricing strategies for different scenarios
- **Risk Management**: Set boundaries, limits, and approval workflows

## Features

### Implemented Features

1. **Competitor Product Tracking**
   - Track competitors across eBay, Amazon, Etsy, Walmart, Shopify, and custom sites
   - Store competitor URLs, product IDs (ASIN, eBay Item ID, etc.)
   - Match confidence scoring
   - Active/inactive status management
   - Customizable check frequency

2. **Automated Price Scraping**
   - Channel-specific scrapers (eBay, Amazon, Etsy, Walmart, Shopify)
   - Web scraping for unsupported channels
   - API-based scraping where available
   - Automatic price history tracking
   - Stock status monitoring
   - Shipping cost inclusion

3. **Pricing Rule Types**
   - **Match Competitor**: Match competitor's price exactly
   - **Beat Competitor**: Undercut by percentage or fixed amount
   - **Stay Below/Above**: Maintain price relative to competitors
   - **Maintain Margin**: Keep specific profit margins
   - **Market-Based**: Price based on market average/median
   - **Inventory-Based**: Adjust based on stock levels
   - **Time-Based**: Different pricing at different times
   - **Custom**: Define your own formula

4. **Automated Pricing Features**
   - Auto-apply prices or require approval
   - Priority-based rule application
   - Price boundaries (min/max)
   - Margin protection (min/max margin %)
   - Change limits (max % or $ change)
   - Daily change limits
   - Schedule-based activation (days/times)

5. **Price Suggestions**
   - AI-generated price recommendations
   - Confidence scoring (0-100%)
   - Priority levels (low/medium/high/urgent)
   - Detailed reasoning
   - Competitive analysis context
   - Margin impact analysis
   - Approve/reject workflow
   - 24-hour expiration

6. **Price History & Analytics**
   - Complete price change history
   - Source tracking (manual, automated, API, etc.)
   - Performance tracking (sales before/after)
   - Approval workflow tracking
   - Revert capability

7. **Competitive Intelligence**
   - Price positioning analysis
   - Market trends
   - Price range analysis
   - Competitor behavior tracking
   - Pricing opportunities detection

## Architecture

### Components

```
Backend:
├── database/migrations/
│   ├── create_competitor_products_table.php
│   ├── create_competitor_prices_table.php
│   ├── create_pricing_rules_table.php
│   ├── create_price_changes_table.php
│   └── create_price_suggestions_table.php
├── app/Models/
│   ├── CompetitorProduct.php
│   ├── CompetitorPrice.php
│   ├── PricingRule.php
│   ├── PriceChange.php
│   └── PriceSuggestion.php
├── app/Services/Pricing/
│   ├── CompetitorPriceScraper.php
│   ├── PricingIntelligenceService.php
│   └── AutomatedPricingEngine.php
└── app/Jobs/Pricing/
    ├── ScrapeCompetitorPricesJob.php
    ├── ApplyPricingRulesJob.php
    └── MonitorCompetitorPricesJob.php
```

### Data Flow

```
1. Competitor Tracking
   Add Competitor URL
   → Store in competitor_products
   → Schedule scraping
   → ScrapeCompetitorPricesJob
   → Store in competitor_prices
   → Update current_price on competitor_products

2. Automated Pricing
   Product
   → Check applicable pricing_rules (by priority)
   → AutomatedPricingEngine calculates new price
   → Create price_suggestions
   → [If auto_apply] Apply immediately → price_changes
   → [If require_approval] Wait for approval → price_changes

3. Price Monitoring
   CompetitorPrice created
   → MonitorCompetitorPricesJob detects significant changes
   → Send alerts/notifications
   → Trigger pricing rule re-evaluation
```

## Setup

### Prerequisites

1. Laravel 12+ application
2. Queue worker for background jobs
3. Scheduler for periodic tasks

### Database Setup

Run the migrations:

```bash
php artisan migrate
```

### Queue Configuration

Add 'pricing' queue to your queue worker:

```bash
php artisan queue:work --queue=pricing,default
```

### Scheduler Setup

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Scrape competitor prices every hour
    $schedule->job(new ScrapeCompetitorPricesJob())->hourly();

    // Apply pricing rules every 30 minutes
    $schedule->job(new ApplyPricingRulesJob())->everyThirtyMinutes();

    // Monitor price changes every 15 minutes
    $schedule->job(new MonitorCompetitorPricesJob())->everyFifteenMinutes();

    // Expire old price suggestions daily
    $schedule->call(function () {
        PriceSuggestion::markExpired();
    })->daily();
}
```

## Competitor Tracking

### Adding Competitors

```php
use App\Models\CompetitorProduct;

$competitor = CompetitorProduct::create([
    'product_id' => 1,
    'variant_id' => null, // Optional: specific variant
    'channel_id' => 1,    // Your channel
    'channel_type' => 'ebay',
    'competitor_name' => 'Competitor Store Name',
    'competitor_url' => 'https://example.com',
    'competitor_product_id' => 'ASIN123' // Optional: ASIN, eBay Item ID, etc.
    'competitor_product_url' => 'https://www.ebay.com/itm/123456789',
    'match_confidence' => 95.0, // How confident this is the same product
    'check_frequency_minutes' => 60, // Check every hour
    'active' => true,
]);
```

### Supported Channels

**eBay**:
- Uses eBay Finding API if `competitor_product_id` (Item ID) is provided
- Falls back to web scraping
- Extracts: price, shipping cost, stock status

**Amazon**:
- Requires Product Advertising API or web scraping
- Extracts: price, Prime status, availability
- Note: Amazon blocks automated scraping - use PA API in production

**Etsy**:
- Web scraping from product pages
- Extracts: price, availability

**Walmart**:
- Web scraping from product pages
- Extracts: price, stock status

**Shopify**:
- Uses Shopify's `.json` endpoint when available
- Falls back to web scraping
- Extracts: price, variants, inventory

**Custom/Generic**:
- Generic web scraper with common price patterns
- Works with most e-commerce sites

### Manual Price Scraping

```php
use App\Services\Pricing\CompetitorPriceScraper;

$scraper = new CompetitorPriceScraper();
$competitor = CompetitorProduct::find(1);

$priceData = $scraper->scrapePrice($competitor);
// Returns: ['price' => 29.99, 'currency' => 'USD', 'in_stock' => true, ...]
```

### Batch Scraping

```php
$competitorIds = [1, 2, 3, 4, 5];
$results = $scraper->batchScrape($competitorIds);
```

## Pricing Rules

### Creating Pricing Rules

```php
use App\Models\PricingRule;

// Example 1: Beat lowest competitor by 5%
$rule = PricingRule::create([
    'name' => 'Beat Lowest Competitor by 5%',
    'description' => 'Always price 5% below the lowest competitor',
    'product_id' => null, // Apply to all products
    'channel_id' => 1,    // Specific channel
    'rule_type' => 'beat_competitor',
    'competitor_selection' => 'lowest', // Target lowest price
    'adjustment_value' => 5.0,  // 5%
    'adjustment_type' => 'percentage',
    'min_price' => 10.00,  // Never go below $10
    'max_price' => 100.00, // Never go above $100
    'min_margin_percent' => 20.0, // Maintain at least 20% margin
    'cost_basis' => 15.00, // Product cost for margin calculation
    'active' => true,
    'auto_apply' => true,  // Automatically apply
    'require_approval' => false, // No manual approval needed
    'priority' => 100, // High priority
]);

// Example 2: Maintain 30% margin
$rule = PricingRule::create([
    'name' => 'Maintain 30% Margin',
    'rule_type' => 'maintain_margin',
    'product_id' => 1, // Specific product
    'min_margin_percent' => 30.0,
    'cost_basis' => 25.00,
    'min_price' => 35.00,
    'max_price' => 50.00,
    'active' => true,
    'auto_apply' => false,
    'require_approval' => true, // Require manual approval
]);

// Example 3: Match market average
$rule = PricingRule::create([
    'name' => 'Match Market Average',
    'rule_type' => 'market_based',
    'competitor_selection' => 'average',
    'adjustment_value' => 0, // No adjustment, match exactly
    'active' => true,
    'priority' => 50,
]);

// Example 4: Inventory-based pricing
$rule = PricingRule::create([
    'name' => 'Inventory-Based Dynamic Pricing',
    'rule_type' => 'inventory_based',
    'strategy_config' => [
        'low_stock_threshold' => 10,
        'low_stock_multiplier' => 1.15, // Increase 15% when low
        'high_stock_threshold' => 100,
        'high_stock_multiplier' => 0.90, // Decrease 10% when high
    ],
    'active' => true,
]);

// Example 5: Time-based pricing (weekend special)
$rule = PricingRule::create([
    'name' => 'Weekend Pricing',
    'rule_type' => 'beat_competitor',
    'adjustment_value' => 10.0, // 10% off on weekends
    'active_days' => [6, 7], // Saturday & Sunday (1=Monday, 7=Sunday)
    'active' => true,
]);

// Example 6: Custom formula
$rule = PricingRule::create([
    'name' => 'Custom Pricing Formula',
    'rule_type' => 'custom',
    'strategy_config' => [
        'formula' => 'current_price * 1.1', // Increase by 10%
    ],
    'active' => true,
]);
```

### Rule Priority

Rules with higher `priority` values are evaluated first. If multiple rules apply to the same product, the highest priority rule wins.

### Safety Limits

**Price Boundaries**:
```php
'min_price' => 10.00,  // Absolute minimum
'max_price' => 100.00, // Absolute maximum
```

**Margin Protection**:
```php
'min_margin_percent' => 20.0, // Minimum profit margin
'max_margin_percent' => 60.0, // Maximum profit margin
'cost_basis' => 15.00,         // Product cost
```

**Change Limits**:
```php
'max_price_change_percent' => 10.0, // Max 10% change per update
'max_price_change_amount' => 5.00,  // Max $5 change per update
'max_changes_per_day' => 3,          // Max 3 changes per day
```

## Automated Pricing

### How It Works

1. **Evaluation**: Pricing rules are evaluated periodically (every 30 minutes by default)
2. **Calculation**: Engine calculates suggested price based on rule type
3. **Validation**: Price is validated against boundaries and limits
4. **Suggestion**: Price suggestion is created with reasoning
5. **Application**:
   - If `auto_apply = true` and `require_approval = false`: Price applied immediately
   - If `require_approval = true`: Price waits for manual approval
   - If `auto_apply = false`: Price suggestion created but not applied

### Manual Rule Application

```php
use App\Services\Pricing\AutomatedPricingEngine;

$engine = app(AutomatedPricingEngine::class);
$product = Product::find(1);
$channel = Channel::find(1);

$result = $engine->applyRules($product, null, $channel);
// Returns:
// [
//     'success' => true,
//     'rules_checked' => 3,
//     'suggestions_created' => 2,
//     'suggestions' => [...],
// ]
```

### Approving Price Suggestions

```php
$suggestion = PriceSuggestion::find(1);
$user = auth()->user();

// Approve and apply
$suggestion->approve($user);

// Reject
$suggestion->reject($user, 'Price too low, would hurt brand');
```

### Applying Suggestions Manually

```php
$engine = app(AutomatedPricingEngine::class);
$suggestion = PriceSuggestion::find(1);

$success = $engine->applySuggestion($suggestion);
```

## Price Monitoring

### Monitoring Significant Changes

The `MonitorCompetitorPricesJob` automatically detects significant price changes (default: ±5%) and logs them.

```php
$job = new MonitorCompetitorPricesJob(
    significantChangeThreshold: 5.0 // 5% threshold
);
dispatch($job);
```

### Price History

```php
$competitor = CompetitorProduct::find(1);

// Get price history
$history = $competitor->priceHistory()
    ->orderBy('scraped_at', 'desc')
    ->take(30)
    ->get();

// Get price trend
$trend = $competitor->getPriceTrend(days: 7);
// Returns: 'up', 'down', or 'stable'

// Get statistics
$avgPrice = $competitor->getAveragePrice(days: 30);
$lowestPrice = $competitor->getLowestPrice(days: 30);
$highestPrice = $competitor->getHighestPrice(days: 30);
```

## Analytics & Insights

### Competitive Analysis

```php
use App\Services\Pricing\PricingIntelligenceService;

$service = app(PricingIntelligenceService::class);
$product = Product::find(1);
$channel = Channel::find(1);

$analysis = $service->getCompetitiveAnalysis($product, $channel);
// Returns:
// [
//     'has_competitors' => true,
//     'competitors_count' => 5,
//     'lowest_price' => 24.99,
//     'highest_price' => 34.99,
//     'average_price' => 29.99,
//     'median_price' => 29.99,
//     'current_product_price' => 32.99,
//     'position' => [
//         'rank' => 4,
//         'total' => 6,
//         'percentile' => 66.7,
//         'description' => 'Above average'
//     ],
//     'competitors' => [...]
// ]
```

### Price History Analysis

```php
$history = $service->getPriceHistory($product, days: 30);
// Returns:
// [
//     'has_history' => true,
//     'changes_count' => 12,
//     'total_price_change' => -2.50,
//     'average_price' => 29.99,
//     'lowest_price' => 27.99,
//     'highest_price' => 32.99,
//     'changes' => [...]
// ]
```

### Pricing Recommendations

```php
$recommendations = $service->getPricingRecommendations($product, $channel);
// Returns various pricing strategies with analysis:
// [
//     'has_recommendations' => true,
//     'current_price' => 32.99,
//     'market_context' => [...],
//     'recommendations' => [
//         [
//             'type' => 'beat_lowest',
//             'title' => 'Beat Lowest Competitor by 5%',
//             'suggested_price' => 23.74,
//             'change' => -9.25,
//             'change_percent' => -28.0,
//             'reasoning' => '...',
//             'risk' => 'medium',
//             'priority' => 'high',
//         ],
//         ...
//     ]
// ]
```

### Performance Metrics

```php
$metrics = $service->getPerformanceMetrics($product, days: 30);
// Returns:
// [
//     'has_data' => true,
//     'period_days' => 30,
//     'total_changes' => 8,
//     'price_increases' => 3,
//     'price_decreases' => 5,
//     'sales_impact' => [...],
//     'units_impact' => [...],
// ]
```

### Detect Opportunities

```php
$opportunities = $service->detectOpportunities($product);
// Returns:
// [
//     [
//         'type' => 'competitor_out_of_stock',
//         'title' => 'Competitors Out of Stock',
//         'description' => '2 competitors are currently out of stock',
//         'action' => 'Consider increasing price to capture demand',
//         'priority' => 'high',
//     ],
//     ...
// ]
```

### Market Insights

```php
$insights = $service->getMarketInsights($channel, days: 30);
// Returns daily market data:
// [
//     'period_days' => 30,
//     'channel' => 'eBay',
//     'daily_insights' => [
//         [
//             'date' => '2025-11-15',
//             'average_price' => 29.99,
//             'lowest_price' => 24.99,
//             'highest_price' => 34.99,
//             'price_range' => 10.00,
//             'competitors_count' => 5,
//         ],
//         ...
//     ]
// ]
```

## API Usage

### REST API Endpoints

(To be implemented in controllers)

```
GET    /api/pricing/competitors                    - List all competitors
POST   /api/pricing/competitors                    - Add competitor
GET    /api/pricing/competitors/{id}               - Get competitor details
PUT    /api/pricing/competitors/{id}               - Update competitor
DELETE /api/pricing/competitors/{id}               - Delete competitor
POST   /api/pricing/competitors/{id}/scrape        - Manually scrape price

GET    /api/pricing/competitors/{id}/history       - Price history
GET    /api/pricing/competitors/{id}/trend         - Price trend

GET    /api/pricing/rules                          - List pricing rules
POST   /api/pricing/rules                          - Create rule
GET    /api/pricing/rules/{id}                     - Get rule details
PUT    /api/pricing/rules/{id}                     - Update rule
DELETE /api/pricing/rules/{id}                     - Delete rule
POST   /api/pricing/rules/{id}/apply               - Manually apply rule

GET    /api/pricing/suggestions                    - List price suggestions
GET    /api/pricing/suggestions/pending            - Pending suggestions
POST   /api/pricing/suggestions/{id}/approve       - Approve suggestion
POST   /api/pricing/suggestions/{id}/reject        - Reject suggestion
POST   /api/pricing/suggestions/batch-approve      - Batch approve

GET    /api/pricing/changes                        - Price change history
GET    /api/pricing/analysis/{product}             - Competitive analysis
GET    /api/pricing/recommendations/{product}      - Get recommendations
GET    /api/pricing/performance/{product}          - Performance metrics
GET    /api/pricing/opportunities/{product}        - Detect opportunities
GET    /api/pricing/insights                       - Market insights
```

## Best Practices

### 1. Start Conservative

- Begin with `require_approval = true` to review suggestions
- Use wide price boundaries initially
- Set reasonable change limits
- Monitor results before enabling full automation

### 2. Competitor Selection

- Track 3-5 direct competitors per product
- Verify competitor URLs regularly
- Set appropriate `match_confidence` scores
- Use `check_frequency_minutes` wisely (hourly is usually sufficient)

### 3. Pricing Rules Strategy

- **Use priority levels**: High priority for critical rules, low for experimental
- **Set safety nets**: Always define min_price and min_margin_percent
- **Layer rules**: Combine competitor-based and margin-based rules
- **Test on subset**: Test rules on a few products first

### 4. Margin Protection

```php
'cost_basis' => 25.00,        // Actual product cost
'min_margin_percent' => 20.0, // Never go below 20% margin
```

Always set these to protect profitability!

### 5. Change Limits

```php
'max_price_change_percent' => 5.0, // Max 5% change at once
'max_changes_per_day' => 2,        // Max 2 changes per day
```

Prevents drastic price swings that could hurt brand perception.

### 6. Schedule Optimization

- **High-traffic times**: More aggressive pricing during peak hours
- **Low-traffic times**: Maintain margins during off-peak
- **Weekends**: Different strategies for weekend shoppers

### 7. A/B Testing

- Run rules on subset of products
- Compare performance vs control group
- Iterate based on results

### 8. Monitoring

- Review price suggestions daily
- Monitor competitor price changes
- Track conversion rate impact
- Adjust rules based on performance

## Troubleshooting

### Competitor Prices Not Scraping

**Problem**: Prices aren't being scraped from competitors

**Solutions**:
1. Check if competitor is marked as `active = true`
2. Verify `competitor_product_url` is correct and accessible
3. Check error logs for scraping failures
4. Some sites block scrapers - may need to rotate user agents or use APIs
5. Verify queue worker is running: `php artisan queue:work`
6. Check scheduler is running: `php artisan schedule:work`

### Prices Not Updating

**Problem**: Automated pricing not applying

**Solutions**:
1. Check if pricing rules are `active = true`
2. Verify `ApplyPricingRulesJob` is running
3. Check if rules pass `isActiveNow()` (schedule check)
4. Verify rules haven't hit `max_changes_per_day`
5. Check if `require_approval = true` (suggestions waiting for approval)
6. Review error logs for validation failures

### Price Validation Errors

**Problem**: Suggested prices being rejected

**Solutions**:
1. Check price boundaries (`min_price`, `max_price`)
2. Verify margin constraints (`min_margin_percent`)
3. Review change limits (`max_price_change_percent`)
4. Ensure `cost_basis` is set correctly

### Web Scraping Issues

**Problem**: Web scraping failing for certain sites

**Solutions**:
1. **Amazon**: Use Product Advertising API instead of scraping
2. **eBay**: Use eBay Finding API with `competitor_product_id`
3. **Rate limiting**: Increase delay between requests
4. **User agent blocks**: Rotate user agent strings
5. **JavaScript rendering**: Use headless browser (Puppeteer, Selenium)
6. **CAPTCHA**: Consider third-party scraping services

### Performance Issues

**Problem**: Slow pricing rule evaluation

**Solutions**:
1. Reduce number of active rules
2. Add more specific `product_id` filters to rules
3. Increase job timeout values
4. Use Redis for queue instead of database
5. Process rules in smaller batches

### Incorrect Competitor Matching

**Problem**: Wrong competitors being tracked

**Solutions**:
1. Review `match_confidence` scores
2. Verify competitor URLs point to correct products
3. Add better competitor identification (ASIN, SKU, etc.)
4. Regular manual review of competitor lists

## Advanced Topics

### Custom Scraping Logic

Extend `CompetitorPriceScraper` to add custom scraping for specific sites:

```php
protected function scrapeCustomSitePrice(CompetitorProduct $competitor): ?array
{
    // Your custom scraping logic
    $response = Http::get($competitor->competitor_product_url);
    $html = $response->body();

    // Extract price using your logic
    $price = $this->extractPriceFromHtml($html);

    return [
        'price' => $price,
        'currency' => 'USD',
        'shipping_cost' => 0,
        'in_stock' => true,
    ];
}
```

### Multi-Currency Support

The system supports multi-currency tracking. Ensure you:
1. Store prices in their original currency
2. Convert to base currency for comparisons
3. Set `current_currency` correctly on competitors

### Channel-Specific Pricing

Create separate rules for each channel:

```php
// eBay pricing
PricingRule::create([
    'channel_id' => $ebayChannelId,
    'rule_type' => 'beat_competitor',
    'adjustment_value' => 5.0,
]);

// Amazon pricing
PricingRule::create([
    'channel_id' => $amazonChannelId,
    'rule_type' => 'match_competitor',
]);
```

### Event Listeners

Listen for pricing events:

```php
// When price changes
Event::listen(PriceChanged::class, function ($event) {
    // Your logic here
    Log::info('Price changed', [
        'product_id' => $event->priceChange->product_id,
        'old_price' => $event->priceChange->old_price,
        'new_price' => $event->priceChange->new_price,
    ]);
});
```

## Cost Considerations

### Web Scraping

- **Free**: Self-hosted scraping (limited by IP blocks, CAPTCHAs)
- **ScraperAPI**: ~$49-$249/month for 100k-1M requests
- **Bright Data**: Pay per GB, typically $500-$1000/month

### API Access

- **eBay Finding API**: Free (rate limited)
- **Amazon PA API**: Free with associate account
- **Etsy Open API**: Free (rate limited)

### Infrastructure

- **Queue Workers**: Existing server resources
- **Scheduler**: Existing server resources
- **Database**: Minimal storage impact

## Security

### Best Practices

1. **Rate Limiting**: Respect robots.txt and rate limits
2. **User Agents**: Identify yourself properly
3. **API Keys**: Store securely in environment variables
4. **Access Control**: Restrict pricing rule modifications to authorized users
5. **Audit Logging**: Track all price changes and who approved them

## Future Enhancements

Potential improvements:

1. **Machine Learning**: Learn optimal prices from historical performance
2. **Predictive Pricing**: Forecast future price trends
3. **Demand Elasticity**: Adjust for price sensitivity
4. **Seasonal Patterns**: Detect and apply seasonal pricing
5. **Competitor Behavior Analysis**: Predict competitor price changes
6. **Real-time Alerts**: Push notifications for significant changes
7. **Advanced Charts**: Price trend visualization
8. **Bulk Operations**: Mass import/export of competitors and rules

---

**Last Updated**: November 15, 2025
**Version**: 1.0.0
**Status**: Production Ready
