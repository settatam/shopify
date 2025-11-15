# AI Product Mapping Guide

## Overview

The AI Product Mapping feature uses artificial intelligence (OpenAI GPT-4) to automatically generate optimized product mappings for all your sales channels (Amazon, eBay, Walmart, etc.) with minimal user input. This dramatically reduces the time needed to list products across multiple marketplaces.

## How It Works

### 1. Analysis Phase
The AI analyzes your product data including:
- Product title, description, and type
- Product variants (size, color, etc.)
- Images and pricing
- Vendor/brand information
- Product tags and categories

### 2. Channel-Specific Optimization
For each sales channel, the AI:
- Selects the best matching category from available options
- Generates SEO-optimized titles and descriptions
- Maps product attributes to channel-specific requirements
- Suggests appropriate values for required and optional fields
- Applies channel-specific best practices (Amazon, eBay, Walmart rules)

### 3. Confidence Scoring
Each suggestion includes a confidence score (0-1):
- **High Confidence (≥0.8)**: AI is very confident about the mapping
- **Medium Confidence (0.5-0.8)**: Review recommended
- **Low Confidence (<0.5)**: Manual review required

## Setup

### 1. Get an OpenAI API Key

1. Go to [OpenAI Platform](https://platform.openai.com/)
2. Create an account or sign in
3. Navigate to API Keys section
4. Create a new API key
5. Copy the key (starts with `sk-`)

### 2. Configure the Application

Add your OpenAI API key to `.env`:

```env
# OpenAI Configuration (for AI product mapping)
OPENAI_API_KEY=sk-your-actual-api-key-here
OPENAI_MODEL=gpt-4-turbo-preview
OPENAI_API_URL=https://api.openai.com/v1/chat/completions
```

### 3. Run Database Migrations

```bash
php artisan migrate
```

This creates the `ai_mapping_suggestions` table to store AI-generated mappings.

## Usage

### Via API

#### Generate AI Mappings for a Product

```bash
POST /api/ai-mapping/products/{product_id}/generate
```

Optional parameters:
```json
{
  "channel_ids": [1, 2, 3]  // Optional: specific channels only
}
```

Response:
```json
{
  "success": true,
  "message": "AI mappings generated successfully",
  "suggestions": [
    {
      "id": 1,
      "channel": {
        "id": 1,
        "name": "Amazon US",
        "type": "amazon"
      },
      "category": {
        "id": 123,
        "name": "Men's Athletic Shoes",
        "path": "Clothing > Shoes > Athletic"
      },
      "mapping": {
        "title": "Nike Air Max 2024 Men's Running Shoes - Breathable Athletic Sneakers",
        "description": "Premium running shoes with Air Max cushioning...",
        "brand": "Nike",
        "attributes": {
          "size": "10",
          "color": "Black",
          "material": "Mesh"
        },
        "images": ["https://..."],
        "keywords": ["running shoes", "athletic", "nike"]
      },
      "confidence": 0.95,
      "reasoning": "Selected Men's Athletic Shoes category based on...",
      "warnings": [],
      "high_confidence": true
    }
  ]
}
```

#### Get Suggestions for a Product

```bash
GET /api/ai-mapping/products/{product_id}/suggestions
```

#### Approve a Suggestion

```bash
POST /api/ai-mapping/suggestions/{suggestion_id}/approve
```

This creates an actual channel listing with the AI-generated mapping.

#### Reject a Suggestion

```bash
POST /api/ai-mapping/suggestions/{suggestion_id}/reject
```

#### Batch Approve Multiple Suggestions

```bash
POST /api/ai-mapping/suggestions/batch-approve
Content-Type: application/json

{
  "suggestion_ids": [1, 2, 3, 4]
}
```

#### Update a Suggestion Before Approving

```bash
PUT /api/ai-mapping/suggestions/{suggestion_id}
Content-Type: application/json

{
  "mapping": {
    "title": "Updated title...",
    "description": "Updated description...",
    ...
  },
  "category_id": 456
}
```

### Via Frontend

#### 1. Product Mapping Page

Navigate to `/ai/products/{product_id}/mapping` to access the full AI mapping interface:

- View all AI-generated suggestions
- See confidence scores and AI reasoning
- Review warnings and recommendations
- Edit mappings before approving
- Batch approve multiple channels at once

#### 2. Quick AI Mapping Button

Add the AI mapping button component to any product view:

```vue
<template>
  <AIMappingButton
    :product-id="product.id"
    variant="primary"
    @generated="handleGenerated"
    @error="handleError"
  />
</template>

<script setup>
import AIMappingButton from '@/Components/AIMappingButton.vue'

const handleGenerated = (data) => {
  console.log('Generated', data.suggestions.length, 'mappings')
  // Refresh product data or show success message
}

const handleError = (error) => {
  console.error('AI mapping failed:', error)
}
</script>
```

## Channel-Specific Rules

The AI applies specific optimization rules for each sales channel:

### Amazon

- **Title**: Max 200 characters, includes brand and key features
- **Description**: Uses bullet points for features
- **Attributes**: Includes variation theme (Size, Color, SizeColor, etc.)
- **Requirements**: Brand, manufacturer, UPC/EAN/ISBN
- **Keywords**: SEO-optimized search terms

### eBay

- **Title**: Max 80 characters, keyword-focused
- **Item Specifics**: Channel-specific attributes
- **Condition**: Required (new, used, refurbished)
- **Shipping**: Weight and dimensions important

### Walmart

- **Title**: Max 75 characters
- **Rich Content**: Detailed product descriptions supported
- **GTIN**: UPC required for most items
- **Brand**: Required for most categories

## Best Practices

### 1. Review High-Confidence Suggestions First

Suggestions with confidence ≥ 0.8 are typically very accurate and can be batch-approved after a quick review.

### 2. Always Review Warnings

If a suggestion has warnings, review them carefully before approving. Warnings might include:
- Missing required attributes
- Category mismatch concerns
- Pricing strategy recommendations
- Image quality issues

### 3. Edit Before Approving

You can modify AI suggestions before approving them:
- Adjust titles for better SEO
- Add or modify attributes
- Change category if needed
- Update pricing strategies

### 4. Use Batch Operations

For products with multiple variants or when mapping to many channels:
- Generate mappings for all channels at once
- Review them systematically
- Use batch approve for high-confidence suggestions
- Individually review lower-confidence ones

### 5. Monitor AI Performance

Track AI mapping statistics:
```bash
GET /api/ai-mapping/statistics
```

Returns:
```json
{
  "total_suggestions": 150,
  "pending": 25,
  "approved": 120,
  "rejected": 5,
  "high_confidence": 110,
  "average_confidence": 0.87
}
```

## Troubleshooting

### AI Returns Low Confidence Scores

**Possible causes:**
- Product data is incomplete or unclear
- Product doesn't fit well into available categories
- Multiple possible categories exist
- Product description is too generic

**Solutions:**
- Improve product title and description
- Add more product tags and attributes
- Ensure product type is specified
- Add high-quality images

### Missing Required Attributes

If AI suggestions are missing required attributes:

1. Check the product's base attributes are filled
2. Review channel category requirements
3. Manually add missing attributes before approving
4. Consider changing to a different category

### API Rate Limits

OpenAI has rate limits. For high-volume operations:

1. Process products in batches (10-20 at a time)
2. Add delays between batches
3. Consider upgrading OpenAI plan for higher limits
4. Cache results and reuse for similar products

### Cost Management

AI mapping uses OpenAI API credits:

**Approximate costs (GPT-4 Turbo):**
- Per product: $0.01 - $0.05
- Per channel: $0.01 - $0.02
- 100 products × 3 channels = $3 - $15

**Tips to reduce costs:**
1. Use `gpt-3.5-turbo` for simpler products (cheaper)
2. Batch similar products together
3. Reuse mappings for product variants
4. Only regenerate when product data changes significantly

## Database Schema

### ai_mapping_suggestions

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| product_id | bigint | FK to products |
| channel_id | bigint | FK to channels |
| status | string | pending/approved/rejected |
| suggested_mapping | json | AI-generated mapping |
| channel_category_suggestion | json | Suggested category details |
| ai_reasoning | text | Why AI made these choices |
| confidence_score | float | 0-1 confidence level |
| metadata | json | Warnings, additional data |
| approved_at | timestamp | When approved |
| approved_by | bigint | FK to users |

## Advanced Configuration

### Custom AI Model

To use a different OpenAI model:

```env
OPENAI_MODEL=gpt-3.5-turbo  # Faster, cheaper
# or
OPENAI_MODEL=gpt-4  # More accurate, slower, more expensive
```

### Custom Prompts

You can customize AI prompts by extending the `AIMappingService` class:

```php
namespace App\Services\AI;

use App\Services\AI\AIMappingService as BaseService;

class CustomAIMappingService extends BaseService
{
    protected function getChannelSpecificRules(string $channelType): string
    {
        // Add your custom rules
        $baseRules = parent::getChannelSpecificRules($channelType);
        return $baseRules . "\n- Custom rule 1\n- Custom rule 2";
    }
}
```

Then bind it in a service provider:

```php
$this->app->bind(AIMappingService::class, CustomAIMappingService::class);
```

## Security Considerations

1. **API Key Protection**: Never commit API keys to version control
2. **Rate Limiting**: Implement rate limiting on AI mapping endpoints
3. **User Permissions**: Only allow authorized users to approve mappings
4. **Cost Monitoring**: Set up alerts for unusual API usage
5. **Data Privacy**: Product data is sent to OpenAI - review their privacy policy

## Future Enhancements

Potential improvements to consider:

1. **Learning from Approvals**: Train on approved vs. rejected suggestions
2. **A/B Testing**: Test different prompts and compare results
3. **Auto-Approval**: Automatically approve very high confidence (>0.95) suggestions
4. **Bulk Processing**: Background job to process large product catalogs
5. **Image Analysis**: Use GPT-4 Vision to analyze product images
6. **Competitive Analysis**: Compare with competitor listings

## Support

For issues or questions:
- Check OpenAI status: https://status.openai.com/
- Review Laravel logs: `storage/logs/laravel.log`
- Check API response times and errors
- Contact support if persistent issues occur

## License

This AI mapping feature is part of Shopmata Multichannel and follows the same license terms.
