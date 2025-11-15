<?php

namespace App\Services\AI;

use App\Models\Product;
use App\Models\Channel;
use App\Models\AIMappingSuggestion;
use App\Models\ChannelCategory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIMappingService
{
    protected string $apiKey;
    protected string $model;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4-turbo-preview');
        $this->apiUrl = config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions');
    }

    /**
     * Generate AI mapping suggestions for a product across all channels
     */
    public function generateMappingsForProduct(Product $product, ?array $channelIds = null): array
    {
        $channels = $channelIds
            ? Channel::whereIn('id', $channelIds)->where('status', 'connected')->get()
            : $product->shop->channels()->where('status', 'connected')->get();

        $suggestions = [];

        foreach ($channels as $channel) {
            try {
                $suggestion = $this->generateMappingForChannel($product, $channel);
                if ($suggestion) {
                    $suggestions[] = $suggestion;
                }
            } catch (\Exception $e) {
                Log::error("AI Mapping failed for product {$product->id} on channel {$channel->id}: " . $e->getMessage());
            }
        }

        return $suggestions;
    }

    /**
     * Generate AI mapping suggestion for a specific channel
     */
    public function generateMappingForChannel(Product $product, Channel $channel): ?AIMappingSuggestion
    {
        // Get available categories for this channel
        $categories = ChannelCategory::where('channel_id', $channel->id)
            ->select('id', 'name', 'category_path', 'attributes_json')
            ->get();

        // Prepare product context
        $productContext = $this->prepareProductContext($product);

        // Prepare channel context
        $channelContext = $this->prepareChannelContext($channel, $categories);

        // Generate AI suggestion
        $aiResponse = $this->callOpenAI($productContext, $channelContext, $channel->type);

        if (!$aiResponse) {
            return null;
        }

        // Parse and validate AI response
        $parsedMapping = $this->parseAIResponse($aiResponse, $channel);

        // Create or update suggestion
        return AIMappingSuggestion::updateOrCreate(
            [
                'product_id' => $product->id,
                'channel_id' => $channel->id,
                'status' => 'pending',
            ],
            [
                'suggested_mapping' => $parsedMapping['mapping'],
                'channel_category_suggestion' => $parsedMapping['category'],
                'ai_reasoning' => $parsedMapping['reasoning'],
                'confidence_score' => $parsedMapping['confidence'],
                'metadata' => $parsedMapping['metadata'] ?? [],
            ]
        );
    }

    /**
     * Prepare product context for AI
     */
    protected function prepareProductContext(Product $product): array
    {
        return [
            'title' => $product->title,
            'description' => $product->description,
            'vendor' => $product->vendor,
            'product_type' => $product->product_type,
            'tags' => $product->tags,
            'variants' => $product->variants->map(function ($variant) {
                return [
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'weight' => $variant->weight,
                    'weight_unit' => $variant->weight_unit,
                    'option1' => $variant->option1,
                    'option2' => $variant->option2,
                    'option3' => $variant->option3,
                ];
            })->toArray(),
            'images' => is_array($product->images_json) ? $product->images_json : [],
            'options' => $product->options_json ?? [],
        ];
    }

    /**
     * Prepare channel context for AI
     */
    protected function prepareChannelContext(Channel $channel, $categories): array
    {
        return [
            'channel_type' => $channel->type,
            'channel_name' => $channel->name,
            'available_categories' => $categories->take(50)->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'path' => $cat->category_path,
                    'required_attributes' => $cat->attributes_json['required'] ?? [],
                    'optional_attributes' => $cat->attributes_json['optional'] ?? [],
                ];
            })->toArray(),
        ];
    }

    /**
     * Call OpenAI API to generate mapping
     */
    protected function callOpenAI(array $productContext, array $channelContext, string $channelType): ?array
    {
        if (!$this->apiKey) {
            Log::warning('OpenAI API key not configured');
            return null;
        }

        $prompt = $this->buildPrompt($productContext, $channelContext, $channelType);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert in e-commerce product mapping and categorization. Your task is to analyze products and create optimal mappings for different sales channels (Amazon, eBay, Walmart, etc.). Always respond with valid JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3, // Lower temperature for more consistent results
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return json_decode($data['choices'][0]['message']['content'], true);
            }

            Log::error('OpenAI API error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI API exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build prompt for AI
     */
    protected function buildPrompt(array $productContext, array $channelContext, string $channelType): string
    {
        $productJson = json_encode($productContext, JSON_PRETTY_PRINT);
        $categoriesJson = json_encode($channelContext['available_categories'], JSON_PRETTY_PRINT);

        return <<<PROMPT
Analyze this product and create an optimal mapping for {$channelContext['channel_name']} ({$channelType}).

PRODUCT DATA:
{$productJson}

AVAILABLE CATEGORIES:
{$categoriesJson}

TASK:
1. Select the BEST matching category from the available categories
2. Generate appropriate values for all required attributes
3. Suggest values for relevant optional attributes
4. Create SEO-optimized title and description for this channel
5. Map product images appropriately

MAPPING RULES FOR {$channelType}:
{$this->getChannelSpecificRules($channelType)}

RESPONSE FORMAT (JSON):
{
  "category_id": <selected_category_id>,
  "category_name": "<selected_category_name>",
  "confidence": <0.0-1.0>,
  "reasoning": "<why you chose this category and mapping>",
  "mapping": {
    "title": "<optimized title for this channel>",
    "description": "<optimized description>",
    "brand": "<brand/vendor name>",
    "attributes": {
      "<attribute_name>": "<attribute_value>",
      ...
    },
    "images": ["<image_url_1>", "<image_url_2>"],
    "pricing_strategy": "<suggested pricing strategy>",
    "keywords": ["<keyword1>", "<keyword2>", ...]
  },
  "warnings": ["<any potential issues or recommendations>"],
  "metadata": {
    "variation_theme": "<for Amazon: size/color/etc>",
    "condition": "new",
    "fulfillment_type": "<FBA/FBM for Amazon, etc>"
  }
}

Provide ONLY valid JSON in your response.
PROMPT;
    }

    /**
     * Get channel-specific mapping rules
     */
    protected function getChannelSpecificRules(string $channelType): string
    {
        return match (strtolower($channelType)) {
            'amazon' => <<<RULES
- Title max 200 characters, include brand, key features, size/color
- Use bullet points in description
- MUST include brand, manufacturer
- Use proper variation theme (Size, Color, SizeColor, etc.)
- Include search terms/keywords
- UPC/EAN/ISBN required for most categories
RULES,
            'ebay' => <<<RULES
- Title max 80 characters, front-load keywords
- Support for item specifics (attributes)
- Condition must be specified
- Shipping weight and dimensions important
- Category-specific required fields
RULES,
            'walmart' => <<<RULES
- Title max 75 characters
- Rich product content supported
- Brand required for most items
- GTIN (UPC) required
- Detailed attributes improve visibility
RULES,
            default => <<<RULES
- Follow standard e-commerce best practices
- Clear, keyword-rich titles
- Detailed, benefit-focused descriptions
- Complete all required attributes
- Accurate product categorization
RULES,
        };
    }

    /**
     * Parse and validate AI response
     */
    protected function parseAIResponse(array $aiResponse, Channel $channel): array
    {
        // Validate required fields
        $requiredFields = ['category_id', 'mapping', 'confidence'];
        foreach ($requiredFields as $field) {
            if (!isset($aiResponse[$field])) {
                throw new \Exception("AI response missing required field: {$field}");
            }
        }

        // Validate category exists
        $category = ChannelCategory::where('channel_id', $channel->id)
            ->where('id', $aiResponse['category_id'])
            ->first();

        if (!$category) {
            // Try to find by name if ID doesn't match
            $category = ChannelCategory::where('channel_id', $channel->id)
                ->where('name', $aiResponse['category_name'] ?? '')
                ->first();
        }

        return [
            'mapping' => $aiResponse['mapping'],
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'path' => $category->category_path,
            ] : null,
            'reasoning' => $aiResponse['reasoning'] ?? 'AI-generated mapping',
            'confidence' => floatval($aiResponse['confidence']),
            'metadata' => array_merge(
                $aiResponse['metadata'] ?? [],
                ['warnings' => $aiResponse['warnings'] ?? []]
            ),
        ];
    }

    /**
     * Approve an AI suggestion and create actual mapping
     */
    public function approveSuggestion(AIMappingSuggestion $suggestion, ?int $userId = null): bool
    {
        try {
            // Create actual channel listing with the suggested mapping
            $listing = $suggestion->product->listings()->create([
                'channel_id' => $suggestion->channel_id,
                'channel_product_id' => null, // Will be set when published
                'status' => 'draft',
                'mapping_json' => $suggestion->suggested_mapping,
                'channel_category_id' => $suggestion->channel_category_suggestion['id'] ?? null,
            ]);

            // Mark suggestion as approved
            $suggestion->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to approve AI suggestion {$suggestion->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Batch approve multiple suggestions
     */
    public function batchApproveSuggestions(array $suggestionIds, ?int $userId = null): array
    {
        $results = ['approved' => 0, 'failed' => 0];

        $suggestions = AIMappingSuggestion::whereIn('id', $suggestionIds)
            ->where('status', 'pending')
            ->get();

        foreach ($suggestions as $suggestion) {
            if ($this->approveSuggestion($suggestion, $userId)) {
                $results['approved']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
