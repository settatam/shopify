# AI-Powered Category Mapper

This document provides comprehensive information about the AI-Powered Category Mapper that intelligently maps products to the correct categories for each selling channel using OpenAI.

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Setup](#setup)
5. [How It Works](#how-it-works)
6. [Usage](#usage)
7. [API Reference](#api-reference)
8. [Channel-Specific Categories](#channel-specific-categories)
9. [Best Practices](#best-practices)
10. [Troubleshooting](#troubleshooting)

## Overview

Different marketplaces have different category structures:
- **eBay**: Hierarchical category tree with Category IDs
- **Amazon**: Browse nodes with specific requirements
- **Etsy**: Taxonomy system with specific paths
- **Walmart**: Category IDs with strict requirements
- **Shopify**: Collections as categories

The AI Category Mapper uses OpenAI GPT-4 to analyze product information and intelligently suggest the best matching category for each marketplace.

### Key Benefits

- **Automated Categorization**: No manual category selection
- **High Accuracy**: AI considers product details, industry standards, and marketplace best practices
- **Time Savings**: Bulk mapping for hundreds of products
- **Learning from Context**: Uses product title, description, type, brand, and variants
- **Multiple Suggestions**: Provides alternatives with confidence scores
- **Human Review**: Approve, reject, or modify AI suggestions

## Features

### Implemented Features

1. **AI-Powered Analysis**
   - GPT-4 Turbo for intelligent categorization
   - Context-aware category selection
   - Confidence scoring (0-100%)
   - Alternative suggestions with reasoning

2. **Multi-Channel Support**
   - eBay categories
   - Amazon browse nodes
   - Etsy taxonomy
   - Walmart categories
   - Shopify collections
   - Generic category structures

3. **Review Workflow**
   - Pending mappings dashboard
   - Approve/reject/modify options
   - Batch operations
   - Detailed reasoning display

4. **Category Management**
   - Auto-fetch channel categories
   - 24-hour caching
   - Hierarchical path display
   - Matched keywords tracking

5. **Statistics & Insights**
   - Total mappings count
   - Pending/approved/rejected breakdown
   - By-channel statistics
   - Confidence score distribution

## Architecture

### Components

```
Backend:
├── app/
│   ├── Services/AI/
│   │   └── AICategoryMapper.php         # AI mapping service
│   ├── Models/
│   │   └── AICategoryMapping.php        # Mapping model
│   ├── Http/Controllers/Api/
│   │   └── AICategoryMappingController.php  # API endpoints
│   └── Jobs/AI/
│       ├── GenerateCategoryMappingsJob.php  # Batch generation
│       └── FetchChannelCategoriesJob.php    # Category fetching

Frontend:
├── resources/js/Pages/AI/
│   └── CategoryMapper.vue               # Management interface

Database:
├── database/migrations/
│   └── xxx_create_ai_category_mappings_table.php
```

### Data Flow

```
1. Fetch Categories
   Channel → FetchChannelCategoriesJob
          → Channel API (eBay/Amazon/Etsy/etc.)
          → Cache for 24 hours

2. Generate Mapping
   Product + Channel + Categories
   → AICategoryMapper
   → OpenAI GPT-4
   → AI Analysis with Reasoning
   → AICategoryMapping Record (pending)

3. Review
   User → Dashboard
       → Review AI Suggestion
       → Approve/Reject/Modify
       → Update Status

4. Apply Mapping
   Approved Mapping
   → Use final_category_id for publishing
   → Product successfully categorized
```

## Setup

### Prerequisites

1. OpenAI API key (GPT-4 access)
2. Active channel connections (eBay, Amazon, Etsy, etc.)
3. Products in the system

### Configuration

Add to your `.env` file:

```env
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-4-turbo-preview
OPENAI_API_URL=https://api.openai.com/v1/chat/completions
```

### Database Setup

Run the migration:

```bash
php artisan migrate
```

This creates the `ai_category_mappings` table with fields for:
- Product and channel associations
- AI suggestions and alternatives
- Confidence scores and reasoning
- Review status and final categories

## How It Works

### Step 1: Fetch Channel Categories

Categories are fetched automatically and cached:

```php
use App\Jobs\AI\FetchChannelCategoriesJob;

$channel = Channel::find(1);
FetchChannelCategoriesJob::dispatch($channel);
```

Categories are cached for 24 hours using key: `channel_categories_{channel_id}`

### Step 2: Generate AI Mapping

The AI analyzes:
1. **Product Title**: Main product name
2. **Description**: Detailed product information
3. **Current Category**: Existing categorization
4. **Product Type**: Type/classification
5. **Brand**: Brand name
6. **Variants**: Variant information (SKU, barcode, title)
7. **Tags**: Product tags

AI considers:
- Marketplace-specific best practices
- Buyer search behavior
- Industry standards
- Category hierarchy specificity

### Step 3: AI Response

The AI returns:

```json
{
  "category_id": "123456",
  "category_name": "Electronics > Computers > Laptops",
  "category_path": "Electronics > Computers & Accessories > Laptops > Traditional Laptops",
  "confidence_score": 95.5,
  "reasoning": "Product title clearly indicates a laptop computer. The description mentions specific laptop features like screen size, processor, and RAM. This is the most specific category for laptop computers on this marketplace.",
  "matched_keywords": ["laptop", "computer", "15.6 inch", "Intel", "RAM"],
  "alternatives": [
    {
      "category_id": "123457",
      "category_name": "Electronics > Computers > Notebooks",
      "category_path": "Electronics > Computers & Accessories > Notebooks",
      "confidence_score": 85.0,
      "reasoning": "Alternative category for portable computers, also appropriate but less specific"
    }
  ]
}
```

### Step 4: Review Process

Users can:

**Approve**:
- Accept AI suggestion
- Sets `status = 'approved'`
- Copies suggested category to final category

**Reject**:
- Decline AI suggestion
- Optionally provide reason
- Sets `status = 'rejected'`

**Modify**:
- Choose different category (from alternatives or manual)
- Sets `status = 'modified'`
- Stores new category as final category

## Usage

### Single Product Mapping

```php
use App\Services\AI\AICategoryMapper;
use App\Models\Product;
use App\Models\Channel;

$mapper = new AICategoryMapper();
$product = Product::find(1);
$channel = Channel::where('channel_type', 'ebay')->first();
$categories = Cache::get("channel_categories_{$channel->id}");

$mapping = $mapper->mapProductCategory($product, $channel, $categories);
```

### Batch Mapping

Via API:

```bash
POST /api/ai-category-mapping/batch-generate
{
  "product_ids": [1, 2, 3, 4, 5],
  "channel_id": 1,
  "categories": [...]
}
```

This queues a `GenerateCategoryMappingsJob` for async processing.

### Frontend Interface

1. Navigate to AI Category Mapper page
2. View pending mappings
3. Filter by channel type or status
4. Review each mapping:
   - View AI reasoning
   - See matched keywords
   - Check alternative suggestions
   - See confidence score
5. Approve, reject, or modify
6. Batch approve multiple mappings

### Programmatic Usage

```php
// Get suggestions without saving
$suggestions = $mapper->getSuggestions($product, $channel, $categories);

// Create mapping record
$aiMapping = AICategoryMapping::create([
    'product_id' => $product->id,
    'channel_id' => $channel->id,
    'channel_type' => $channel->channel_type,
    'suggested_category_id' => $suggestions['suggested_category_id'],
    'suggested_category_name' => $suggestions['suggested_category_name'],
    'suggested_category_path' => $suggestions['suggested_category_path'],
    'confidence_score' => $suggestions['confidence_score'],
    'alternative_suggestions' => $suggestions['alternative_suggestions'],
    'ai_reasoning' => $suggestions['ai_reasoning'],
    'matched_keywords' => $suggestions['matched_keywords'],
    'status' => 'pending',
]);

// Approve mapping
$aiMapping->approve($user);

// Reject mapping
$aiMapping->reject($user, 'Category not specific enough');

// Modify mapping
$aiMapping->modify($user, 'new_cat_id', 'New Category Name', 'Full > Path');
```

## API Reference

### Generate Category Mapping

```http
POST /api/ai-category-mapping/products/{product}/generate
```

**Request Body:**
```json
{
  "channel_id": 1,
  "categories": [
    {
      "id": "123",
      "name": "Electronics",
      "path": "Electronics > Computers"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Category mapping generated successfully",
  "mapping": {
    "id": 1,
    "product_id": 1,
    "channel_id": 1,
    "suggested_category_id": "123456",
    "confidence_score": 95.5,
    ...
  }
}
```

### Get Pending Mappings

```http
GET /api/ai-category-mapping/pending?channel_type=ebay&status=pending
```

**Response:**
```json
{
  "success": true,
  "mappings": {
    "data": [...],
    "links": [...],
    "total": 50
  }
}
```

### Approve Mapping

```http
POST /api/ai-category-mapping/{mapping}/approve
```

### Reject Mapping

```http
POST /api/ai-category-mapping/{mapping}/reject

{
  "reason": "Category not specific enough"
}
```

### Modify Mapping

```http
POST /api/ai-category-mapping/{mapping}/modify

{
  "category_id": "new_id",
  "category_name": "New Category",
  "category_path": "Full > Category > Path"
}
```

### Batch Approve

```http
POST /api/ai-category-mapping/batch-approve

{
  "mapping_ids": [1, 2, 3, 4, 5]
}
```

### Get Statistics

```http
GET /api/ai-category-mapping/statistics
```

**Response:**
```json
{
  "success": true,
  "statistics": {
    "total": 150,
    "pending": 30,
    "approved": 100,
    "rejected": 20,
    "by_channel": {
      "ebay": {
        "total": 50,
        "pending": 10,
        "approved": 35,
        "rejected": 5
      },
      ...
    }
  }
}
```

## Channel-Specific Categories

### eBay Categories

Format:
```php
[
    'id' => '12345',
    'name' => 'Laptops & Netbooks',
    'path' => 'Computers/Tablets & Networking > Laptops & Netbooks',
    'parent_id' => '58058',
    'level' => 2
]
```

Fetched via eBay Trading API `GetCategories` call.

### Amazon Browse Nodes

Format:
```php
[
    'id' => '565098',  // Browse node ID
    'name' => 'Laptop Computers',
    'path' => 'Electronics > Computers & Accessories > Computers & Tablets > Laptops > Traditional Laptops'
]
```

Note: Amazon browse nodes are typically pre-defined or fetched via Product Advertising API.

### Etsy Taxonomy

Format:
```php
[
    'id' => '1234',  // Taxonomy ID
    'name' => 'Clothing',
    'path' => 'Accessories > Clothing > Shirts & Tops',
    'parent_id' => '123',
    'level' => 3
]
```

Fetched via Etsy Open API v3 Taxonomy endpoint.

### Walmart Categories

Format:
```php
[
    'id' => '0',
    'name' => 'Electronics',
    'path' => 'Electronics > Computers'
]
```

### Shopify Collections

Format:
```php
[
    'id' => '123456789',
    'name' => 'Laptops',
    'path' => 'Laptops'
]
```

Fetched via Shopify Admin API.

## Best Practices

### Prompt Engineering

The AI prompt is carefully crafted to:
1. Emphasize marketplace-specific requirements
2. Prioritize specificity over generality
3. Consider buyer search behavior
4. Follow industry standards
5. Return only valid categories from provided list

### Confidence Scores

Interpret confidence scores:
- **90-100%**: Highly confident, usually safe to auto-approve
- **70-89%**: Moderate confidence, review recommended
- **Below 70%**: Low confidence, manual review required

### Batch Processing

For large product catalogs:
1. Fetch categories once, cache for all products
2. Batch generate mappings (10-50 at a time)
3. Queue jobs to avoid timeouts
4. Review in batches using dashboard

### Category Caching

Categories are cached for 24 hours to:
- Reduce API calls
- Improve performance
- Ensure consistency

Manually refresh if categories change:
```php
Cache::forget("channel_categories_{$channel_id}");
FetchChannelCategoriesJob::dispatch($channel);
```

### Review Workflow

Recommended process:
1. Generate mappings for batch of products
2. Review high-confidence (>90%) first - quick approve
3. Review medium-confidence (70-90%) - verify logic
4. Review low-confidence (<70%) - may need manual selection
5. Reject and regenerate if needed

## Troubleshooting

### AI Not Responding

**Problem**: OpenAI API calls failing

**Solutions:**
1. Verify API key is correct in `.env`
2. Check OpenAI account has credits/quota
3. Ensure internet connectivity
4. Check OpenAI service status
5. Review error logs for specific error messages

### Low Confidence Scores

**Problem**: All mappings have low confidence

**Solutions:**
1. Improve product data (add descriptions, better titles)
2. Add more specific product details
3. Use consistent naming conventions
4. Add product type and category
5. Include brand information

### Wrong Category Suggestions

**Problem**: AI suggests incorrect categories

**Solutions:**
1. Check product data quality
2. Verify category list is complete and current
3. Review AI reasoning to understand logic
4. Reject and provide feedback
5. Consider modifying prompt for specific cases

### Categories Not Fetching

**Problem**: Channel categories not loading

**Solutions:**
1. Verify channel connection is active
2. Check channel API credentials
3. Review API rate limits
4. Check error logs
5. Manually trigger fetch job

### Performance Issues

**Problem**: Slow mapping generation

**Solutions:**
1. Use batch processing instead of one-by-one
2. Reduce batch size (10-20 products at a time)
3. Use queue workers for async processing
4. Cache categories before batch processing
5. Consider using faster AI model for bulk operations

## Advanced Features

### Custom Category Formats

Add support for new channel category format:

```php
// In AICategoryMapper.php
protected function formatCustomChannelCategories(array $categories): string
{
    $formatted = [];
    foreach ($categories as $category) {
        $formatted[] = "ID: {$category['id']}, Name: {$category['name']}";
    }
    return implode("\n", $formatted);
}
```

### AI Temperature Adjustment

Modify temperature for more/less creative suggestions:

```php
// In AICategoryMapper::callOpenAI()
'temperature' => 0.1,  // More deterministic (0.0-2.0)
```

### Custom Prompts

Customize prompts for specific use cases:

```php
protected function buildCustomPrompt($productData, $categoriesData, $channelType): string
{
    // Add custom instructions
    // Include specific requirements
    // Adjust tone and style
}
```

### Webhook Integration

Automatically generate mappings when products are created:

```php
// In ProductObserver
public function created(Product $product)
{
    $channels = Channel::where('shop_id', $product->shop_id)->get();
    foreach ($channels as $channel) {
        // Dispatch mapping job
    }
}
```

## Future Enhancements

Potential improvements:

1. **Learning from Approvals**: Use approved mappings to improve future suggestions
2. **Auto-Approval**: Automatically approve high-confidence mappings
3. **Multi-Language Support**: Category mapping in different languages
4. **Custom Rules**: User-defined rules for specific product types
5. **A/B Testing**: Test different AI models for accuracy
6. **Analytics**: Track accuracy rates and improvement over time
7. **Feedback Loop**: Learn from rejections to improve prompts

## Cost Considerations

### OpenAI API Costs

GPT-4 Turbo pricing (as of 2025):
- Input: ~$0.01 per 1K tokens
- Output: ~$0.03 per 1K tokens

Typical category mapping:
- Input: ~1,000-2,000 tokens (product data + categories)
- Output: ~300-500 tokens (suggestions)
- Cost per mapping: ~$0.02-$0.05

For 1,000 products: **$20-$50**

### Optimization Tips

1. Use caching to avoid re-mapping
2. Batch similar products together
3. Use cheaper models for simple products
4. Cache category lists
5. Implement approval thresholds for auto-processing

## Support

For issues with:
- **OpenAI API**: [OpenAI Platform Documentation](https://platform.openai.com/docs)
- **Category Mapping**: Check logs and AI reasoning
- **Integration**: Review channel-specific API docs

## References

- [OpenAI GPT-4 Documentation](https://platform.openai.com/docs/models/gpt-4)
- [eBay Category API](https://developer.ebay.com/devzone/xml/docs/reference/ebay/getcategories.html)
- [Amazon Browse Nodes](https://webservices.amazon.com/paapi5/documentation/use-cases/use-case-browse-nodes.html)
- [Etsy Taxonomy](https://www.etsy.com/developers/documentation/reference/taxonomy)
- [Walmart Categories](https://developer.walmart.com/doc/us/mp/us-mp-items/)

---

**Last Updated**: November 15, 2025
**Version**: 1.0.0
**AI Model**: GPT-4 Turbo
