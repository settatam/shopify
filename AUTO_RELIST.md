# AI Auto-Relist (Dead Listing Reviver) 🔄

Automatically detect underperforming listings and revive them with AI-powered optimization and strategic relisting.

## Overview

The AI Auto-Relist system continuously monitors your product listings across all channels and automatically detects "dead listings" - products that aren't selling despite being listed. When a dead listing is detected, the system uses AI to optimize every aspect of the listing and relists it at the optimal time for maximum visibility.

## Key Features

### 🎯 Intelligent Detection
- **Configurable Criteria**: Define what constitutes a "dead listing" for your business
  - Minimum days listed without sales
  - Maximum views threshold
  - Maximum sales threshold
  - Minimum view-to-sale ratio
- **Multi-Channel Support**: Monitor listings across eBay, Amazon, Etsy, Walmart, and more
- **Automated Scanning**: Runs on your configured schedule (hourly, daily, etc.)

### 🤖 AI-Powered Optimization
When a dead listing is detected, the system can automatically:

1. **✍️ Rewrite Title**
   - Channel-specific keyword optimization
   - SEO best practices
   - Character limit compliance
   - Brand and product highlighting

2. **📝 Fix Description**
   - Engaging product storytelling
   - Feature highlighting
   - Call-to-action optimization
   - HTML formatting for supported channels

3. **🗂️ Fix Category**
   - AI category mapper integration
   - Channel-specific category trees
   - Most relevant category selection

4. **🏷️ Add Missing Attributes**
   - Required marketplace attributes
   - Enhanced product specifications
   - Buyer decision factors

5. **📸 Swap Primary Image**
   - Reorder images to test different visuals
   - Move last image to first position
   - A/B testing approach

6. **💰 Adjust Price**
   - Multiple pricing strategies:
     - **Decrease**: Lower price by percentage
     - **Increase**: Raise price (if underpriced)
     - **Market-Based**: Use competitive intelligence
     - **None**: Keep current price
   - Integration with Pricing Intelligence system

7. **⏰ Optimal Relist Time**
   - Time-of-day optimization
   - Day-of-week preferences
   - Maximum visibility timing

### 📊 Campaign Management
- **Multiple Campaigns**: Run different strategies for different product categories
- **Channel-Specific**: Target specific marketplaces or all channels
- **Approval Workflows**: Require manual approval before relisting (optional)
- **Daily Limits**: Control max relists per day per campaign
- **Per-Product Limits**: Prevent over-relisting the same product

### 📈 Performance Tracking
- **Before/After Metrics**:
  - Views comparison
  - Sales comparison
  - Conversion rate changes
- **Success Rate**: Track which optimizations work best
- **Improvement Percentage**: Measure ROI of relisting efforts
- **Automated Monitoring**: 7-day and 30-day performance tracking

## How It Works

### 1. Campaign Setup

Create a campaign with detection criteria and optimization preferences:

```javascript
POST /api/auto-relist/campaigns

{
  "name": "eBay 30-Day No Sales Campaign",
  "channel_id": 1, // or null for all channels

  // Detection Criteria
  "min_days_listed": 30,
  "max_views": 50,
  "max_sales": 0,
  "min_view_to_sale_ratio": 100,

  // Optimization Actions
  "auto_rewrite_title": true,
  "auto_fix_description": true,
  "auto_fix_category": true,
  "auto_add_attributes": true,
  "auto_swap_images": true,
  "auto_adjust_price": true,

  // Pricing Strategy
  "price_adjustment_strategy": "market_based", // decrease, increase, market_based, none
  "price_adjustment_percent": 10, // for decrease/increase strategies

  // Timing Optimization
  "optimize_relist_time": true,
  "preferred_relist_time": "18:00", // 6 PM
  "preferred_relist_days": [0, 1, 2, 3, 4], // Monday-Friday

  // Workflow
  "requires_approval": true, // manual approval before relisting

  // Automation Settings
  "check_frequency_hours": 24, // check daily
  "max_relists_per_day": 20,
  "max_relists_per_product": 3,

  "is_active": true
}
```

### 2. Automated Detection

The system runs on your configured schedule:

```php
// Automatically dispatched based on check_frequency_hours
DetectDeadListingsJob::dispatch($campaign);
```

The job:
1. Queries for products matching campaign criteria
2. Retrieves performance metrics (views, sales, conversion)
3. Identifies dead listings
4. Creates optimization actions

### 3. AI Optimization

For each detected dead listing:

```php
// AutoRelistService optimizes the listing
$service->optimizeListing($campaign, $action);
```

The service:
1. Uses AI Content Optimizer for title and description
2. Uses AI Category Mapper for category selection
3. Suggests missing marketplace attributes
4. Reorders images for visual testing
5. Calculates optimal price using competitive intelligence
6. Determines best relist time

### 4. Approval (Optional)

If `requires_approval` is enabled:

```javascript
// Get pending approvals
GET /api/auto-relist/pending-approvals

// Review action details
GET /api/auto-relist/actions/{actionId}

// Approve and queue for processing
POST /api/auto-relist/actions/{actionId}/approve

// Or reject
POST /api/auto-relist/actions/{actionId}/reject
{
  "reason": "Price too low"
}
```

### 5. Automated Relisting

Once approved (or if auto-approval enabled):

```php
// ProcessAutoRelistJob executes the relist
ProcessAutoRelistJob::dispatch($action);
```

The job:
1. Applies all optimizations to the product
2. Updates listing on the channel
3. Marks original listing as ended/sold
4. Creates new listing (or updates existing)
5. Schedules performance monitoring

### 6. Performance Monitoring

After relisting:

```php
// MonitorRelistPerformanceJob tracks results
MonitorRelistPerformanceJob::dispatch($action)
    ->delay(now()->addDays(7));
```

The job:
1. Retrieves post-relist metrics (views, sales)
2. Compares to pre-relist performance
3. Calculates improvement percentage
4. Continues monitoring up to 30 days
5. Stops when sales occur or sufficient data collected

## API Endpoints

### Campaign Management

```javascript
// List all campaigns
GET /api/auto-relist/campaigns
  ?is_active=true
  &per_page=15

// Get campaign details
GET /api/auto-relist/campaigns/{campaignId}

// Create campaign
POST /api/auto-relist/campaigns
  (see "Campaign Setup" section for body)

// Update campaign
PUT /api/auto-relist/campaigns/{campaignId}

// Delete campaign
DELETE /api/auto-relist/campaigns/{campaignId}

// Run campaign manually
POST /api/auto-relist/campaigns/{campaignId}/run
```

### Action Management

```javascript
// Get pending approvals
GET /api/auto-relist/pending-approvals
  ?per_page=15

// Get action details
GET /api/auto-relist/actions/{actionId}

// Get actions for a product
GET /api/auto-relist/products/{productId}/actions

// Approve action
POST /api/auto-relist/actions/{actionId}/approve

// Reject action
POST /api/auto-relist/actions/{actionId}/reject
{
  "reason": "Optional rejection reason"
}

// Bulk approve
POST /api/auto-relist/actions/bulk-approve
{
  "action_ids": [1, 2, 3, 4, 5]
}

// Delete action (rejected/failed only)
DELETE /api/auto-relist/actions/{actionId}
```

### Statistics

```javascript
// Get comprehensive statistics
GET /api/auto-relist/statistics

Response:
{
  "total_campaigns": 5,
  "active_campaigns": 3,
  "total_actions": 127,
  "pending_approval": 8,
  "completed_relists": 95,
  "successful_relists": 67,
  "average_improvement": 45.2,
  "recent_actions": [...]
}
```

## Database Schema

### auto_relist_campaigns

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| shop_id | bigint | Shop reference |
| channel_id | bigint | Target channel (nullable for all) |
| name | string | Campaign name |
| min_days_listed | integer | Min days without sales |
| max_views | integer | Max views threshold (nullable) |
| max_sales | integer | Max sales threshold (nullable) |
| min_view_to_sale_ratio | decimal | Min ratio to qualify as dead |
| auto_rewrite_title | boolean | Enable title optimization |
| auto_fix_description | boolean | Enable description optimization |
| auto_fix_category | boolean | Enable category mapping |
| auto_add_attributes | boolean | Enable attribute suggestions |
| auto_swap_images | boolean | Enable image reordering |
| auto_adjust_price | boolean | Enable price adjustment |
| price_adjustment_strategy | enum | decrease, increase, market_based, none |
| price_adjustment_percent | decimal | Percentage for decrease/increase |
| optimize_relist_time | boolean | Enable timing optimization |
| preferred_relist_time | time | Preferred time of day |
| preferred_relist_days | json | Array of preferred days (0-6) |
| requires_approval | boolean | Require manual approval |
| check_frequency_hours | integer | How often to check |
| max_relists_per_day | integer | Daily limit (nullable) |
| max_relists_per_product | integer | Per-product limit (nullable) |
| last_run_at | timestamp | Last execution time |
| products_detected | integer | Total detected |
| products_relisted | integer | Total relisted |
| is_active | boolean | Campaign status |

### auto_relist_actions

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| campaign_id | bigint | Parent campaign |
| product_id | bigint | Target product |
| channel_id | bigint | Target channel |
| status | enum | detected, analyzing, pending_approval, approved, processing, completed, rejected, failed |
| days_listed | integer | Days product was listed |
| total_views | integer | Views before relist |
| total_sales | integer | Sales before relist |
| view_to_sale_ratio | decimal | Conversion ratio |
| original_title | text | Before optimization |
| original_description | text | Before optimization |
| original_category_id | bigint | Before optimization |
| original_attributes | json | Before optimization |
| original_images | json | Before optimization |
| original_price | decimal | Before optimization |
| new_title | text | After optimization |
| new_description | text | After optimization |
| new_category_id | bigint | After optimization |
| new_attributes | json | After optimization |
| new_images | json | After optimization |
| new_price | decimal | After optimization |
| ai_analysis | json | AI reasoning and suggestions |
| changes_made | json | Array of changes applied |
| optimization_reasoning | text | Human-readable explanation |
| relist_scheduled_at | timestamp | When to relist |
| relisted_at | timestamp | When relisted |
| views_before | integer | Views in period before |
| sales_before | integer | Sales in period before |
| conversion_rate_before | decimal | Conversion before |
| views_after | integer | Views after relist |
| sales_after | integer | Sales after relist |
| conversion_rate_after | decimal | Conversion after |
| approved_by_id | bigint | Approving user |
| approved_at | timestamp | Approval time |
| rejected_by_id | bigint | Rejecting user |
| rejected_at | timestamp | Rejection time |
| rejection_reason | text | Why rejected |
| failure_reason | text | Why failed |

## Background Jobs

### DetectDeadListingsJob

**Purpose**: Scans for underperforming listings and creates optimization actions

**Frequency**: Based on campaign `check_frequency_hours`

**Queue**: `auto-relist`

**Timeout**: 10 minutes

**Retries**: 3 attempts with exponential backoff

**Process**:
1. Verify campaign is active
2. Check daily relist limits
3. Query for products matching criteria
4. Calculate performance metrics
5. Create AutoRelistAction for each dead listing
6. Trigger AI optimization
7. Schedule next run

### ProcessAutoRelistJob

**Purpose**: Executes approved relist actions

**Trigger**: Manual approval or auto-approval

**Queue**: `auto-relist`

**Timeout**: 10 minutes

**Retries**: 3 attempts with exponential backoff

**Process**:
1. Verify action is approved
2. Check scheduled relist time
3. Apply all optimizations to product
4. Update listing on channel API
5. Mark original listing as ended
6. Create/update channel listing
7. Record relisted timestamp
8. Schedule performance monitoring

### MonitorRelistPerformanceJob

**Purpose**: Tracks post-relist performance metrics

**Frequency**: 7-day intervals (up to 30 days)

**Queue**: `analytics`

**Timeout**: 5 minutes

**Retries**: 3 attempts with exponential backoff

**Process**:
1. Verify action is completed
2. Retrieve current metrics from analytics
3. Update views_after, sales_after, conversion_rate_after
4. Calculate improvement percentage
5. Continue monitoring if needed (< 30 days, no sales yet)
6. Log performance comparison

## Use Cases

### 1. eBay Stale Listings Campaign

Detect eBay listings that haven't sold in 30 days with fewer than 100 views:

```javascript
{
  "name": "eBay 30-Day Stale Listings",
  "channel_id": 1,
  "min_days_listed": 30,
  "max_views": 100,
  "max_sales": 0,
  "auto_rewrite_title": true,
  "auto_fix_category": true,
  "auto_adjust_price": true,
  "price_adjustment_strategy": "decrease",
  "price_adjustment_percent": 10,
  "optimize_relist_time": true,
  "preferred_relist_time": "18:00",
  "preferred_relist_days": [6, 0], // Sunday, Monday
  "requires_approval": false,
  "check_frequency_hours": 24,
  "is_active": true
}
```

### 2. Multi-Channel Low Conversion Campaign

Target products with poor view-to-sale ratio across all channels:

```javascript
{
  "name": "Low Conversion Optimizer",
  "channel_id": null, // all channels
  "min_days_listed": 14,
  "min_view_to_sale_ratio": 50, // 1 sale per 50 views is poor
  "auto_rewrite_title": true,
  "auto_fix_description": true,
  "auto_add_attributes": true,
  "auto_swap_images": true,
  "price_adjustment_strategy": "market_based",
  "requires_approval": true,
  "check_frequency_hours": 48,
  "max_relists_per_day": 10,
  "is_active": true
}
```

### 3. Amazon Quick Turnaround Campaign

Aggressively relist Amazon products not selling after 7 days:

```javascript
{
  "name": "Amazon Quick Relist",
  "channel_id": 2,
  "min_days_listed": 7,
  "max_sales": 0,
  "auto_rewrite_title": true,
  "auto_fix_category": true,
  "auto_add_attributes": true,
  "price_adjustment_strategy": "market_based",
  "optimize_relist_time": true,
  "requires_approval": false,
  "check_frequency_hours": 12,
  "max_relists_per_product": 2,
  "is_active": true
}
```

## Best Practices

### Detection Criteria
- **Start Conservative**: Begin with longer `min_days_listed` (30+ days) to avoid premature relisting
- **Use View-to-Sale Ratio**: Better indicator than absolute views or sales
- **Channel-Specific**: Different channels have different performance norms
- **Seasonal Products**: Adjust criteria based on seasonality

### Optimization Strategy
- **Enable All AI Features**: Let AI optimize all aspects for best results
- **Test Image Swapping**: Different primary images can dramatically impact CTR
- **Market-Based Pricing**: Most effective for competitive categories
- **Category Fixes**: Wrong category = invisible listing

### Timing
- **Weekend Relisting**: Higher traffic on weekends for many categories
- **Evening Hours**: 6 PM - 9 PM typically sees peak shopping activity
- **Avoid Holidays**: Adjust preferred days around major holidays
- **Channel Patterns**: eBay auctions end Sunday evening, optimize accordingly

### Approval Workflow
- **Start with Manual Approval**: Review AI suggestions until confident
- **Auto-Approve After Success**: Once success rate is high, enable auto-approval
- **Bulk Approve**: Use bulk operations for efficiency
- **Monitor Statistics**: Track success rates to refine strategies

### Limits and Safety
- **Set Daily Limits**: Prevent overwhelming your catalog with changes
- **Per-Product Limits**: Avoid relisting the same product repeatedly
- **Monitor Performance**: Review improvement metrics weekly
- **A/B Test Strategies**: Run multiple campaigns with different approaches

## Integration with Other Systems

### AI Content Optimizer
- Automatically uses optimized titles and descriptions
- Channel-specific formatting and keyword optimization
- SEO best practices applied

### AI Category Mapper
- Selects most relevant category for each channel
- Improves discoverability and compliance
- Reduces category-related errors

### Pricing Intelligence
- `market_based` strategy uses competitive analysis
- Real-time competitor price monitoring
- Margin protection and safety limits

### Analytics Service
- Performance tracking and metrics collection
- Before/after comparison data
- ROI calculation for optimization efforts

## Monitoring and Reporting

### Campaign Dashboard
```javascript
GET /api/auto-relist/campaigns/{campaignId}

{
  "campaign": { ... },
  "statistics": {
    "success_rate": 68.5,
    "can_relist_more_today": true,
    "is_due_for_run": false
  }
}
```

### Action Details
```javascript
GET /api/auto-relist/actions/{actionId}

{
  "action": {
    "status": "completed",
    "original_title": "Old Title",
    "new_title": "AI Optimized Title",
    "views_before": 25,
    "views_after": 87,
    "sales_before": 0,
    "sales_after": 3,
    ...
  },
  "changes_summary": [
    "title_rewritten",
    "category_changed",
    "price_decreased",
    "images_reordered"
  ],
  "improvement": 248.0,
  "is_successful": true
}
```

### Global Statistics
```javascript
GET /api/auto-relist/statistics

{
  "total_campaigns": 5,
  "active_campaigns": 3,
  "total_actions": 127,
  "pending_approval": 8,
  "completed_relists": 95,
  "successful_relists": 67, // sales_after > 0
  "average_improvement": 45.2,
  "recent_actions": [...]
}
```

## Troubleshooting

### Campaign Not Running
- Check `is_active` is true
- Verify `check_frequency_hours` has elapsed since `last_run_at`
- Review queue configuration
- Check job logs for errors

### No Dead Listings Detected
- Review detection criteria (may be too strict)
- Verify products exist on the channel
- Check analytics data is being collected
- Ensure sufficient time has passed (`min_days_listed`)

### Actions Stuck in Pending
- Check `requires_approval` setting
- Review approval queue for backlog
- Consider enabling auto-approval
- Verify user permissions

### Relisting Failures
- Check channel API credentials
- Verify product data completeness
- Review channel-specific requirements
- Check failure_reason in action record

### Poor Performance After Relist
- Review AI optimization suggestions
- Test different price strategies
- Try different primary images
- Verify category mapping accuracy
- Check competitive landscape changes

## Future Enhancements

- **Machine Learning**: Learn from successful relists to improve AI suggestions
- **A/B Testing**: Automatically test multiple optimization approaches
- **Image Generation**: AI-generated lifestyle images for products
- **Competitor Monitoring**: Trigger relists when competitors change prices
- **Seasonal Optimization**: Adjust strategies based on seasonal trends
- **Performance Prediction**: AI predicts likelihood of success before relisting
- **Automated Image Enhancement**: Background removal, color correction, etc.
- **Multi-Variant Support**: Optimize variant-level attributes and pricing

## Conclusion

The AI Auto-Relist (Dead Listing Reviver) system provides a comprehensive, automated solution for identifying and reviving underperforming product listings. By combining intelligent detection, AI-powered optimization, strategic timing, and performance tracking, it helps maximize the sales potential of your entire catalog across all channels.

Key benefits:
- **Automated**: Set it and forget it - runs 24/7
- **Intelligent**: AI optimizes every aspect of your listings
- **Measurable**: Track improvement and ROI
- **Flexible**: Multiple campaigns with different strategies
- **Safe**: Approval workflows and daily limits
- **Integrated**: Works with all your AI and pricing tools

Start with conservative criteria and manual approval, monitor the results, and gradually increase automation as you gain confidence in the system's performance.
