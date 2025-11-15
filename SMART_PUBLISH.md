# Smart Publish - One-Click Multi-Channel Publishing

This document provides comprehensive information about the Smart Publish feature that automates the entire product publishing workflow with AI-powered optimization.

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [How It Works](#how-it-works)
4. [Setup](#setup)
5. [Usage](#usage)
6. [API Reference](#api-reference)
7. [Workflow Steps](#workflow-steps)
8. [Monitoring & Reports](#monitoring--reports)
9. [Best Practices](#best-practices)
10. [Troubleshooting](#troubleshooting)

## Overview

Smart Publish is a comprehensive, one-click solution that automates the entire product publishing process across multiple channels. It orchestrates all your AI features into a single streamlined workflow.

### What Smart Publish Does

With one click, Smart Publish automatically:

✅ **Optimizes Titles** - AI-generated, channel-specific titles
✅ **Generates Descriptions** - Compelling, SEO-optimized descriptions
✅ **Picks Best Category** - AI-powered category mapping
✅ **Suggests Attributes** - Required marketplace attributes
✅ **Optimizes Images** - Resizes and formats images
✅ **Sets Competitive Price** - Market-based pricing
✅ **Ensures Compliance** - Validates marketplace rules
✅ **Publishes to All Channels** - Multi-channel publishing

### Key Benefits

- **Save Hours of Manual Work**: What takes hours manually happens in minutes
- **Consistency Across Channels**: Each marketplace gets optimized content
- **Reduced Errors**: Automated compliance checking
- **Better Performance**: AI-optimized content converts better
- **Complete Visibility**: Detailed reports for every publish attempt
- **Retry Capability**: Easy retry for failed publishes

## Features

### Implemented Features

1. **Orchestrated Workflow**
   - 8-step automated process
   - Each step can be enabled/disabled
   - Failure handling and recovery
   - Progress tracking in real-time

2. **AI Content Optimization**
   - Channel-specific title optimization (eBay: 80 chars, Amazon: 200 chars, etc.)
   - Description generation with SEO keywords
   - Quality scoring for each optimization
   - Fallback to original content if AI fails

3. **Intelligent Category Mapping**
   - AI-powered category selection
   - Confidence scoring
   - Alternative suggestions
   - Marketplace-specific category trees

4. **Attribute Suggestions**
   - Channel-specific required attributes
   - Auto-filled from product data
   - Brand, color, size, type suggestions
   - Marketplace compliance

5. **Image Optimization**
   - Automatic resizing to channel requirements
   - Format conversion
   - Position ordering
   - Quality optimization

6. **Competitive Pricing**
   - Market-based price recommendations
   - Competitor analysis integration
   - Margin protection
   - Price reasoning and context

7. **Compliance Checking**
   - Title length validation
   - Required field validation
   - Image requirements
   - Channel-specific rules

8. **Multi-Channel Publishing**
   - Simultaneous publishing to multiple channels
   - Channel-specific formatting
   - Error handling per channel
   - Success/failure tracking

9. **Comprehensive Reporting**
   - Detailed step-by-step reports
   - Success/failure tracking
   - Error and warning collection
   - Performance metrics
   - Duration tracking

10. **Progress Monitoring**
    - Real-time status updates
    - Progress percentage
    - Per-step status
    - Estimated completion time

## How It Works

### Workflow Overview

```
User Clicks "Smart Publish"
    ↓
Select Channels to Publish To
    ↓
Job Queued for Background Processing
    ↓
For Each Channel:
    1. Optimize Title (AI)
    2. Optimize Description (AI)
    3. Map Category (AI)
    4. Suggest Attributes
    5. Optimize Images
    6. Set Competitive Price
    7. Check Compliance
    8. Publish to Channel
    ↓
Generate Detailed Report
    ↓
Notify User of Results
```

### Step-by-Step Process

**1. Title Optimization**
- Analyzes product data
- Uses channel-specific AI prompts
- Generates optimized title
- Validates length constraints
- Falls back to original if AI fails

**2. Description Optimization**
- Creates compelling descriptions
- Includes SEO keywords
- Follows channel guidelines
- Optimizes for conversion

**3. Category Mapping**
- Fetches channel categories (cached 24h)
- AI analyzes product for best fit
- Returns category with confidence score
- Provides alternatives

**4. Attribute Suggestions**
- Determines required attributes per channel
- Auto-fills from product data
- Suggests missing attributes
- Ensures compliance

**5. Image Optimization**
- Validates image availability
- Resizes to channel specs
- Optimizes compression
- Orders by position

**6. Competitive Pricing**
- Analyzes competitor prices
- Gets market recommendations
- Applies pricing rules if configured
- Provides pricing reasoning

**7. Compliance Check**
- Validates title length
- Checks required fields
- Verifies image requirements
- Collects errors and warnings

**8. Publishing**
- Publishes to channel API
- Handles channel-specific formatting
- Tracks success/failure
- Stores channel listing ID

## Setup

### Prerequisites

1. Laravel 12+ application
2. Queue worker for background jobs
3. OpenAI API key (for AI features)
4. Active channel connections
5. Products in the system

### Database Setup

Run the migration:

```bash
php artisan migrate
```

This creates the `smart_publish_reports` table.

### Queue Configuration

Add 'smart-publish' queue to your worker:

```bash
php artisan queue:work --queue=smart-publish,pricing,default
```

### Environment Variables

Ensure these are set in `.env`:

```env
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-4-turbo-preview
OPENAI_API_URL=https://api.openai.com/v1/chat/completions
```

## Usage

### Basic Usage via API

```bash
POST /api/smart-publish/products/{product}/publish

{
  "channel_ids": [1, 2, 3],
  "auto_optimize_title": true,
  "auto_optimize_description": true,
  "auto_map_category": true,
  "auto_suggest_attributes": true,
  "auto_optimize_images": true,
  "auto_set_price": true,
  "auto_check_compliance": true
}
```

Response:
```json
{
  "success": true,
  "message": "Smart publish initiated. Processing in background.",
  "product_id": 1
}
```

### Preview Before Publishing

```bash
POST /api/smart-publish/products/{product}/preview

{
  "channel_ids": [1, 2, 3]
}
```

Response shows what will happen:
```json
{
  "success": true,
  "preview": {
    "product": {...},
    "channels": [1, 2, 3],
    "steps": [
      {
        "name": "Title Optimization",
        "description": "AI will optimize titles for each marketplace",
        "enabled": true
      },
      ...
    ],
    "estimated_duration": "2-5 minutes"
  }
}
```

### Programmatic Usage

```php
use App\Jobs\SmartPublishJob;
use App\Models\Product;
use App\Models\User;

$product = Product::find(1);
$user = auth()->user();
$channelIds = [1, 2, 3]; // eBay, Amazon, Etsy

SmartPublishJob::dispatch($product, $channelIds, $user, [
    'auto_optimize_title' => true,
    'auto_optimize_description' => true,
    'auto_map_category' => true,
    'auto_suggest_attributes' => true,
    'auto_optimize_images' => true,
    'auto_set_price' => true,
    'auto_check_compliance' => true,
]);
```

### Direct Service Usage

```php
use App\Services\SmartPublishService;

$service = app(SmartPublishService::class);
$product = Product::find(1);
$channelIds = [1, 2, 3];
$user = auth()->user();

$report = $service->publish($product, $channelIds, $user);

echo "Status: {$report->status}\n";
echo "Progress: {$report->getProgressPercentage()}%\n";
echo "Channels Succeeded: {$report->channels_succeeded}/{$report->channels_attempted}\n";
```

## API Reference

### Publish Product

```http
POST /api/smart-publish/products/{product}/publish
```

**Request:**
```json
{
  "channel_ids": [1, 2, 3],
  "auto_optimize_title": true,
  "auto_optimize_description": true,
  "auto_map_category": true,
  "auto_suggest_attributes": true,
  "auto_optimize_images": true,
  "auto_set_price": true,
  "auto_check_compliance": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Smart publish initiated. Processing in background.",
  "product_id": 1
}
```

### Preview Publish

```http
POST /api/smart-publish/products/{product}/preview
```

### Get Report

```http
GET /api/smart-publish/reports/{report}
```

**Response:**
```json
{
  "success": true,
  "report": {
    "id": 1,
    "product": {...},
    "user": {...},
    "status": "completed",
    "progress": 100,
    "success_rate": 66.7,
    "channels": {
      "attempted": 3,
      "succeeded": 2,
      "failed": 1
    },
    "steps": {
      "total": 8,
      "completed": 7,
      "failed": 1,
      "skipped": 0
    },
    "step_statuses": {
      "title_optimization": "completed",
      "description_optimization": "completed",
      "category_mapping": "completed",
      "attribute_suggestion": "completed",
      "image_optimization": "completed",
      "pricing": "completed",
      "compliance_check": "completed",
      "publishing": "completed"
    },
    "results": {
      "optimized_content": {...},
      "mapped_categories": {...},
      "suggested_attributes": {...},
      "optimized_images": {...},
      "pricing_data": {...},
      "compliance_results": {...},
      "publish_results": {...}
    },
    "errors": [...],
    "warnings": [...],
    "timing": {
      "started_at": "2025-11-15T10:00:00Z",
      "completed_at": "2025-11-15T10:03:45Z",
      "duration_seconds": 225
    }
  }
}
```

### Get Product Reports

```http
GET /api/smart-publish/products/{product}/reports?status=completed
```

### Get User Reports

```http
GET /api/smart-publish/reports?status=processing&days=7
```

### Get Statistics

```http
GET /api/smart-publish/statistics
```

**Response:**
```json
{
  "success": true,
  "statistics": {
    "total": 150,
    "processing": 5,
    "completed": 120,
    "partial_success": 15,
    "failed": 10,
    "total_channels_attempted": 450,
    "total_channels_succeeded": 380,
    "total_channels_failed": 70,
    "average_duration": 187.5,
    "recent_reports": [...]
  }
}
```

### Retry Failed Publish

```http
POST /api/smart-publish/reports/{report}/retry
```

### Cancel Processing Publish

```http
POST /api/smart-publish/reports/{report}/cancel
```

### Delete Report

```http
DELETE /api/smart-publish/reports/{report}
```

## Workflow Steps

### Step 1: Title Optimization

**Purpose**: Generate channel-optimized titles
**Service**: AIContentOptimizer
**Input**: Product data, channel type
**Output**: Optimized title with quality score
**Fallback**: Original product title

**Channel Limits**:
- eBay: 80 characters
- Amazon: 200 characters
- Etsy: 140 characters
- Walmart: 75 characters
- Shopify: 255 characters

### Step 2: Description Optimization

**Purpose**: Generate compelling descriptions
**Service**: AIContentOptimizer
**Input**: Product data, channel guidelines
**Output**: Optimized description with keywords
**Fallback**: Original product description

### Step 3: Category Mapping

**Purpose**: Select best marketplace category
**Service**: AICategoryMapper
**Input**: Product data, channel categories
**Output**: Category ID, path, confidence score
**Fallback**: None (optional step)

### Step 4: Attribute Suggestions

**Purpose**: Fill required marketplace attributes
**Method**: Channel-specific logic
**Input**: Product data
**Output**: Attribute key-value pairs

**eBay Attributes**:
- Brand, Type, Condition, Color, Size

**Amazon Attributes**:
- Brand, Manufacturer, ProductTypeName, Color, Size, ItemPackageQuantity

**Etsy Attributes**:
- who_made, is_supply, when_made, materials, style

**Walmart Attributes**:
- brand, color, size

### Step 5: Image Optimization

**Purpose**: Prepare images for channels
**Method**: Image processing
**Input**: Product images
**Output**: Optimized image URLs with metadata

### Step 6: Competitive Pricing

**Purpose**: Set market-based prices
**Service**: PricingIntelligenceService
**Input**: Product, channel, competitor data
**Output**: Suggested price with reasoning
**Fallback**: Current product price

### Step 7: Compliance Check

**Purpose**: Validate marketplace requirements
**Method**: Rule-based validation
**Checks**:
- Title length
- Required fields
- Image availability
- Category selection

**Output**: Pass/fail with errors/warnings

### Step 8: Publishing

**Purpose**: Publish to channel API
**Method**: Channel-specific API calls
**Input**: All optimized data
**Output**: Channel listing ID and URL

## Monitoring & Reports

### Report Statuses

- **pending**: Report created, not started
- **processing**: Currently executing
- **completed**: All channels succeeded
- **partial_success**: Some channels succeeded
- **failed**: All channels failed or job error

### Step Statuses

- **pending**: Not started yet
- **processing**: Currently executing
- **completed**: Successfully completed
- **failed**: Error occurred
- **skipped**: Step disabled or not applicable

### Real-time Monitoring

```php
$report = SmartPublishReport::find(1);

// Check status
if ($report->isProcessing()) {
    echo "Progress: {$report->getProgressPercentage()}%\n";
    echo "Steps completed: {$report->completed_steps}/{$report->total_steps}\n";
}

// Check results
if ($report->isCompleted()) {
    echo "Success rate: {$report->getSuccessRate()}%\n";
    echo "Duration: {$report->duration_seconds} seconds\n";
}
```

### Error Handling

Errors are collected throughout the process:

```php
$report = SmartPublishReport::find(1);

foreach ($report->errors as $error) {
    echo "[{$error['step']}] {$error['message']}\n";
}

foreach ($report->warnings as $warning) {
    echo "[{$warning['step']}] {$warning['message']}\n";
}
```

## Best Practices

### 1. Preview First

Always preview before publishing to production channels:

```php
// Preview what will happen
$preview = $controller->preview($request, $product);

// Review the steps
foreach ($preview['preview']['steps'] as $step) {
    echo "{$step['name']}: {$step['description']}\n";
}
```

### 2. Start with One Channel

Test with a single channel first:

```php
SmartPublishJob::dispatch($product, [1], $user); // Just eBay
```

### 3. Enable Selectively

Disable steps you don't need:

```php
SmartPublishJob::dispatch($product, $channelIds, $user, [
    'auto_optimize_title' => true,
    'auto_optimize_description' => true,
    'auto_map_category' => false, // Disable category mapping
    'auto_set_price' => false,    // Use manual pricing
]);
```

### 4. Monitor Reports

Check reports regularly:

```php
// Get recent failures
$failures = SmartPublishReport::where('status', 'failed')
    ->recent(7)
    ->get();

foreach ($failures as $report) {
    echo "Product {$report->product_id}: {$report->failure_reason}\n";
}
```

### 5. Retry Failed Publishes

Use the retry feature for failures:

```bash
POST /api/smart-publish/reports/{report}/retry
```

### 6. Batch Processing

For multiple products, queue them individually:

```php
foreach ($products as $product) {
    SmartPublishJob::dispatch($product, $channelIds, $user);

    // Small delay to avoid overwhelming the queue
    usleep(100000); // 0.1 second
}
```

### 7. Schedule Off-Peak

Schedule bulk publishes during off-peak hours:

```php
// In Kernel.php
$schedule->call(function () {
    $products = Product::whereNeedsPublishing()->get();

    foreach ($products as $product) {
        SmartPublishJob::dispatch($product, [1, 2, 3], $systemUser);
    }
})->dailyAt('02:00'); // 2 AM
```

## Troubleshooting

### Publish Stuck in Processing

**Problem**: Report shows "processing" for extended time

**Solutions**:
1. Check queue worker is running: `php artisan queue:work`
2. Check job logs for errors
3. Verify timeout hasn't been exceeded (15 min default)
4. Use cancel endpoint to mark as failed
5. Retry the publish

### AI Optimization Failing

**Problem**: Title/description optimization fails

**Solutions**:
1. Verify OpenAI API key is valid
2. Check OpenAI account has credits
3. Review error logs for specific error
4. AI will fallback to original content
5. Product will still publish with original content

### Category Mapping Issues

**Problem**: Categories not being mapped

**Solutions**:
1. Check channel categories are cached
2. Verify category fetch job is running
3. Review AI reasoning in errors
4. Publishing works without category (uses default)

### Compliance Check Failures

**Problem**: Compliance check blocking publish

**Solutions**:
1. Review errors in report
2. Fix product data (title length, description, etc.)
3. Add missing required fields
4. Retry after fixes

### Image Optimization Problems

**Problem**: Images not processing correctly

**Solutions**:
1. Verify image URLs are accessible
2. Check image format compatibility
3. Review error logs
4. Product publishes without images (with warning)

### Pricing Issues

**Problem**: Competitive pricing failing

**Solutions**:
1. Check if competitor data exists
2. Verify pricing rules are configured
3. System falls back to current price
4. Manual pricing can be set later

### Channel Publishing Failures

**Problem**: Publish fails on specific channel

**Solutions**:
1. Review publish_results in report
2. Check channel credentials
3. Verify channel API is accessible
4. Check channel-specific error message
5. Other channels may have succeeded (partial_success)

### Queue Worker Not Processing

**Problem**: Jobs not being processed

**Solutions**:
```bash
# Check queue status
php artisan queue:work --once

# Restart queue worker
php artisan queue:restart

# Check failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job_id}
```

## Performance Optimization

### Queue Priority

Process smart publish jobs with priority:

```php
SmartPublishJob::dispatch($product, $channelIds, $user)
    ->onQueue('smart-publish');
```

Configure worker priorities:
```bash
php artisan queue:work --queue=smart-publish,pricing,default
```

### Caching

Categories are cached for 24 hours automatically. Manually clear if needed:

```php
Cache::forget("channel_categories_{$channelId}");
```

### Batch Processing

For large catalogs, process in smaller batches:

```php
Product::chunk(10, function ($products) use ($channelIds, $user) {
    foreach ($products as $product) {
        SmartPublishJob::dispatch($product, $channelIds, $user);
    }

    sleep(5); // Pause between batches
});
```

## Advanced Usage

### Custom Step Configuration

Override specific steps:

```php
$service = app(SmartPublishService::class);

// Custom logic
$report = $service->publish($product, $channelIds, $user, [
    'auto_optimize_title' => true,
    'auto_optimize_description' => false, // Skip description
    'auto_map_category' => true,
    'auto_suggest_attributes' => false,  // Skip attributes
    'auto_optimize_images' => true,
    'auto_set_price' => false,           // Skip pricing
    'auto_check_compliance' => true,
]);
```

### Webhook Notifications

Add webhook support for publish completion:

```php
// In SmartPublishJob::handle()
if ($report->isCompleted()) {
    Http::post('https://your-webhook-url.com/smart-publish', [
        'report_id' => $report->id,
        'product_id' => $report->product_id,
        'status' => $report->status,
        'channels_succeeded' => $report->channels_succeeded,
    ]);
}
```

### Event Listeners

Listen for smart publish events:

```php
Event::listen(SmartPublishCompleted::class, function ($event) {
    $report = $event->report;

    // Send notification
    $report->user->notify(new SmartPublishCompletedNotification($report));

    // Update analytics
    Analytics::track('smart_publish_completed', [
        'product_id' => $report->product_id,
        'channels' => $report->channels_attempted,
        'success_rate' => $report->getSuccessRate(),
    ]);
});
```

## Future Enhancements

Potential improvements:

1. **Real-time Progress Updates**: WebSocket-based live progress
2. **Bulk Actions**: Publish multiple products at once
3. **Templates**: Save common configurations
4. **Scheduling**: Schedule publishes for future dates
5. **A/B Testing**: Test different titles/descriptions
6. **Performance Insights**: Track which optimizations work best
7. **Auto-retry**: Automatically retry failed steps
8. **Channel Templates**: Pre-configured channel settings
9. **Conditional Logic**: Publish if certain conditions met
10. **Rollback**: Unpublish or revert changes

---

**Last Updated**: November 15, 2025
**Version**: 1.0.0
**Status**: Production Ready
**Estimated Processing Time**: 2-5 minutes per product (depending on number of channels)
