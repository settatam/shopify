<?php

namespace App\Services\AI;

use App\Models\Product;
use App\Models\Channel;
use App\Models\AICategoryMapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AICategoryMapper
{
    protected string $apiKey;
    protected string $model;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4-turbo-preview');
        $this->apiUrl = config('services.openai.api_url');
    }

    /**
     * Generate category mapping for a product on a channel
     */
    public function mapProductCategory(Product $product, Channel $channel, array $availableCategories): array
    {
        $productData = $this->prepareProductData($product);
        $categoriesData = $this->prepareCategoriesData($availableCategories, $channel->channel_type);

        $prompt = $this->buildPrompt($productData, $categoriesData, $channel->channel_type);

        $response = $this->callOpenAI($prompt);

        return $this->parseAIResponse($response, $availableCategories);
    }

    /**
     * Prepare product data for AI analysis
     */
    protected function prepareProductData(Product $product): array
    {
        return [
            'title' => $product->title,
            'description' => $product->description ?? '',
            'category' => $product->category ?? '',
            'tags' => $product->tags ?? [],
            'brand' => $product->brand ?? '',
            'product_type' => $product->product_type ?? '',
            'variants' => $product->variants->map(function ($variant) {
                return [
                    'title' => $variant->title,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                ];
            })->toArray(),
        ];
    }

    /**
     * Prepare categories data for AI
     */
    protected function prepareCategoriesData(array $categories, string $channelType): string
    {
        // Format categories based on channel type
        switch ($channelType) {
            case 'ebay':
                return $this->formatEbayCategories($categories);
            case 'amazon':
                return $this->formatAmazonCategories($categories);
            case 'etsy':
                return $this->formatEtsyCategories($categories);
            case 'walmart':
                return $this->formatWalmartCategories($categories);
            default:
                return $this->formatGenericCategories($categories);
        }
    }

    /**
     * Format eBay categories
     */
    protected function formatEbayCategories(array $categories): string
    {
        $formatted = [];
        foreach ($categories as $category) {
            $path = $category['path'] ?? $category['name'];
            $id = $category['id'] ?? $category['category_id'];
            $formatted[] = "ID: {$id}, Path: {$path}";
        }
        return implode("\n", $formatted);
    }

    /**
     * Format Amazon categories
     */
    protected function formatAmazonCategories(array $categories): string
    {
        $formatted = [];
        foreach ($categories as $category) {
            $path = $category['path'] ?? $category['name'];
            $browseNode = $category['browse_node_id'] ?? $category['id'];
            $formatted[] = "Browse Node: {$browseNode}, Path: {$path}";
        }
        return implode("\n", $formatted);
    }

    /**
     * Format Etsy categories
     */
    protected function formatEtsyCategories(array $categories): string
    {
        $formatted = [];
        foreach ($categories as $category) {
            $path = $category['path'] ?? $category['name'];
            $id = $category['id'] ?? $category['taxonomy_id'];
            $formatted[] = "Taxonomy ID: {$id}, Path: {$path}";
        }
        return implode("\n", $formatted);
    }

    /**
     * Format Walmart categories
     */
    protected function formatWalmartCategories(array $categories): string
    {
        $formatted = [];
        foreach ($categories as $category) {
            $path = $category['path'] ?? $category['name'];
            $id = $category['id'] ?? $category['category_id'];
            $formatted[] = "Category ID: {$id}, Path: {$path}";
        }
        return implode("\n", $formatted);
    }

    /**
     * Format generic categories
     */
    protected function formatGenericCategories(array $categories): string
    {
        $formatted = [];
        foreach ($categories as $category) {
            $name = $category['name'] ?? $category['category_name'] ?? 'Unknown';
            $id = $category['id'] ?? $category['category_id'] ?? 'N/A';
            $path = $category['path'] ?? $name;
            $formatted[] = "ID: {$id}, Path: {$path}";
        }
        return implode("\n", $formatted);
    }

    /**
     * Build prompt for AI
     */
    protected function buildPrompt(array $productData, string $categoriesData, string $channelType): string
    {
        $channelName = ucfirst(str_replace('_', ' ', $channelType));
        $productJson = json_encode($productData, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are an expert in e-commerce product categorization, specifically for {$channelName} marketplace.

PRODUCT INFORMATION:
{$productJson}

AVAILABLE {$channelName} CATEGORIES:
{$categoriesData}

TASK:
Analyze the product information and select the MOST APPROPRIATE category from the available categories list. Consider:
1. Product title and description
2. Product type and existing category
3. Brand and variants
4. Industry-standard categorization for {$channelName}
5. Best practices for {$channelName} marketplace

IMPORTANT:
- You must select a category that EXISTS in the available categories list
- Choose the most specific/granular category that fits
- Consider buyer search behavior on {$channelName}
- Return ONLY valid JSON in this exact format:

{
  "category_id": "the exact category ID from the list",
  "category_name": "the exact category name",
  "category_path": "the full breadcrumb path",
  "confidence_score": 95.5,
  "reasoning": "Detailed explanation of why this category was chosen",
  "matched_keywords": ["keyword1", "keyword2", "keyword3"],
  "alternatives": [
    {
      "category_id": "alternative id",
      "category_name": "alternative name",
      "category_path": "alternative path",
      "confidence_score": 85.0,
      "reasoning": "Why this is also a good match"
    }
  ]
}

Respond with ONLY the JSON, no other text.
PROMPT;
    }

    /**
     * Call OpenAI API
     */
    protected function callOpenAI(string $prompt): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert e-commerce product categorization AI. You always respond with valid JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3, // Lower temperature for more consistent results
                'max_tokens' => 2000,
            ]);

            if ($response->failed()) {
                Log::error('OpenAI API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('OpenAI API call failed: ' . $response->body());
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Extract JSON from response (in case AI added extra text)
            $content = $this->extractJSON($content);

            return json_decode($content, true);
        } catch (\Exception $e) {
            Log::error('AI category mapping failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract JSON from AI response
     */
    protected function extractJSON(string $content): string
    {
        // Try to find JSON in the response
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            return $matches[0];
        }

        return $content;
    }

    /**
     * Parse AI response
     */
    protected function parseAIResponse(array $response, array $availableCategories): array
    {
        return [
            'suggested_category_id' => $response['category_id'] ?? null,
            'suggested_category_name' => $response['category_name'] ?? null,
            'suggested_category_path' => $response['category_path'] ?? null,
            'confidence_score' => $response['confidence_score'] ?? null,
            'ai_reasoning' => $response['reasoning'] ?? null,
            'matched_keywords' => $response['matched_keywords'] ?? [],
            'alternative_suggestions' => $response['alternatives'] ?? [],
        ];
    }

    /**
     * Batch map products for a channel
     */
    public function batchMapProducts(array $productIds, Channel $channel, array $availableCategories): array
    {
        $results = [];

        foreach ($productIds as $productId) {
            try {
                $product = Product::find($productId);
                if (!$product) {
                    continue;
                }

                $mapping = $this->mapProductCategory($product, $channel, $availableCategories);

                // Create AI category mapping record
                $aiMapping = AICategoryMapping::create([
                    'product_id' => $product->id,
                    'channel_id' => $channel->id,
                    'channel_type' => $channel->channel_type,
                    'suggested_category_id' => $mapping['suggested_category_id'],
                    'suggested_category_name' => $mapping['suggested_category_name'],
                    'suggested_category_path' => $mapping['suggested_category_path'],
                    'confidence_score' => $mapping['confidence_score'],
                    'alternative_suggestions' => $mapping['alternative_suggestions'],
                    'ai_reasoning' => $mapping['ai_reasoning'],
                    'matched_keywords' => $mapping['matched_keywords'],
                    'product_data_used' => $this->prepareProductData($product),
                    'status' => 'pending',
                ]);

                $results[] = [
                    'product_id' => $product->id,
                    'mapping_id' => $aiMapping->id,
                    'success' => true,
                ];
            } catch (\Exception $e) {
                Log::error('Failed to map product category', [
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'product_id' => $productId,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get category suggestions for a product without saving
     */
    public function getSuggestions(Product $product, Channel $channel, array $availableCategories): array
    {
        return $this->mapProductCategory($product, $channel, $availableCategories);
    }
}
