<?php

namespace App\Services\AI;

use App\Models\Product;
use App\Models\Channel;
use App\Models\AIOptimization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIContentOptimizer
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
     * Optimize product content for a specific channel
     */
    public function optimizeContent(Product $product, Channel $channel, string $type = 'both'): array
    {
        $productData = $this->prepareProductData($product);
        $guidelines = $this->getChannelGuidelines($channel->channel_type);

        $prompt = $this->buildPrompt($productData, $channel->channel_type, $guidelines, $type);

        $response = $this->callOpenAI($prompt);

        return $this->parseAIResponse($response, $type);
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
            'price' => $product->variants->first()?->price ?? 0,
            'variants' => $product->variants->map(function ($variant) {
                return [
                    'title' => $variant->title,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'attributes' => $variant->attributes ?? [],
                ];
            })->toArray(),
        ];
    }

    /**
     * Get channel-specific guidelines
     */
    protected function getChannelGuidelines(string $channelType): array
    {
        $guidelines = [
            'ebay' => [
                'title_max_length' => 80,
                'title_rules' => [
                    'No promotional text (FREE, SALE, etc.)',
                    'Include brand, model, size, color',
                    'Use keywords buyers search for',
                    'Be specific and descriptive',
                    'No special characters or symbols',
                ],
                'description_rules' => [
                    'HTML formatting allowed',
                    'Include detailed specifications',
                    'Add shipping and return policy',
                    'Use bullet points for features',
                    'Include measurements and materials',
                ],
                'seo_focus' => 'Search engine optimization within eBay',
            ],

            'amazon' => [
                'title_max_length' => 200,
                'title_rules' => [
                    'Format: Brand + Model + Key Features + Size/Color',
                    'Capitalize first letter of each word',
                    'No promotional phrases',
                    'Include size, color, quantity in title',
                    'Use numerals (not spelled out)',
                ],
                'description_rules' => [
                    'Use bullet points (5-7 bullets)',
                    'Start each bullet with capital letter',
                    'Focus on benefits, not just features',
                    'Include dimensions and warranty info',
                    'Plain text only (no HTML)',
                ],
                'seo_focus' => 'Amazon A9 algorithm optimization',
            ],

            'etsy' => [
                'title_max_length' => 140,
                'title_rules' => [
                    'Front-load important keywords',
                    'Include who it\'s for (gifts, etc.)',
                    'Describe style/aesthetic',
                    'Mention materials and technique',
                    'Natural language, not keyword stuffing',
                ],
                'description_rules' => [
                    'Tell the story of the item',
                    'Describe materials and process',
                    'Include care instructions',
                    'Mention customization options',
                    'Add shop policies at end',
                ],
                'seo_focus' => 'Long-tail keywords for handmade/vintage items',
            ],

            'walmart' => [
                'title_max_length' => 75,
                'title_rules' => [
                    'Format: Brand + Defining Quality + Item Name + Key Feature',
                    'No special characters',
                    'Include pack size if applicable',
                    'Be concise and specific',
                    'No promotional text',
                ],
                'description_rules' => [
                    'Plain text, no HTML',
                    'Include key features as bullets',
                    'Mention warranty and care',
                    'Describe usage scenarios',
                    'Add specifications',
                ],
                'seo_focus' => 'Clear, keyword-rich content',
            ],

            'shopify' => [
                'title_max_length' => 255,
                'title_rules' => [
                    'Customer-facing, marketing focused',
                    'Include brand and model',
                    'Highlight unique selling points',
                    'Use compelling language',
                    'Can be more creative',
                ],
                'description_rules' => [
                    'Rich HTML formatting encouraged',
                    'Tell brand story',
                    'Use emotional appeal',
                    'Include size guides and FAQs',
                    'Add lifestyle imagery descriptions',
                ],
                'seo_focus' => 'Google SEO and customer conversion',
            ],
        ];

        return $guidelines[$channelType] ?? [
            'title_max_length' => 100,
            'title_rules' => ['Be descriptive', 'Include key features'],
            'description_rules' => ['Provide detailed information'],
            'seo_focus' => 'General SEO best practices',
        ];
    }

    /**
     * Build prompt for AI
     */
    protected function buildPrompt(array $productData, string $channelType, array $guidelines, string $type): string
    {
        $channelName = ucfirst(str_replace('_', ' ', $channelType));
        $productJson = json_encode($productData, JSON_PRETTY_PRINT);
        $guidelinesJson = json_encode($guidelines, JSON_PRETTY_PRINT);

        $titlePrompt = ($type === 'title' || $type === 'both') ? "OPTIMIZED TITLE (max {$guidelines['title_max_length']} characters)" : '';
        $descPrompt = ($type === 'description' || $type === 'both') ? "OPTIMIZED DESCRIPTION" : '';

        return <<<PROMPT
You are an expert e-commerce copywriter specializing in {$channelName} listings.

PRODUCT INFORMATION:
{$productJson}

{$channelName} GUIDELINES:
{$guidelinesJson}

TASK:
Create an optimized {$type} for this product specifically for {$channelName}. Follow these requirements:

1. STRICT ADHERENCE to {$channelName} guidelines above
2. SEO optimization for: {$guidelines['seo_focus']}
3. Compelling, conversion-focused copy
4. Accurate representation of product
5. Professional tone appropriate for {$channelName}

{$titlePrompt}
{$descPrompt}

IMPORTANT INSTRUCTIONS:
- Title MUST be under {$guidelines['title_max_length']} characters
- Follow ALL channel-specific rules
- Include relevant keywords naturally
- Make it appealing to buyers
- Be truthful and accurate

Return ONLY valid JSON in this exact format:

{
  "optimized_title": "Your optimized title here (if requested)",
  "optimized_description": "Your optimized description here (if requested)",
  "quality_score": 95.5,
  "reasoning": "Detailed explanation of optimizations made",
  "improvements_made": [
    "Added brand name for clarity",
    "Included key searchable keywords",
    "Formatted according to channel guidelines"
  ],
  "keywords_added": ["keyword1", "keyword2", "keyword3"],
  "channel_guidelines_followed": [
    "Kept title under character limit",
    "Removed promotional language",
    "Used proper capitalization"
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
            ])->timeout(90)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert e-commerce copywriter. You always respond with valid JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7, // Balanced creativity and consistency
                'max_tokens' => 2500,
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

            // Extract JSON from response
            $content = $this->extractJSON($content);

            return json_decode($content, true);
        } catch (\Exception $e) {
            Log::error('AI content optimization failed', [
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
    protected function parseAIResponse(array $response, string $type): array
    {
        $result = [
            'optimized_title' => $response['optimized_title'] ?? null,
            'optimized_description' => $response['optimized_description'] ?? null,
            'quality_score' => $response['quality_score'] ?? null,
            'ai_reasoning' => $response['reasoning'] ?? null,
            'improvements_made' => $response['improvements_made'] ?? [],
            'keywords_added' => $response['keywords_added'] ?? [],
            'channel_guidelines' => $response['channel_guidelines_followed'] ?? [],
        ];

        // Calculate character counts
        if ($result['optimized_title']) {
            $result['optimized_title_length'] = mb_strlen($result['optimized_title']);
        }
        if ($result['optimized_description']) {
            $result['optimized_description_length'] = mb_strlen($result['optimized_description']);
        }

        return $result;
    }

    /**
     * Batch optimize products for a channel
     */
    public function batchOptimize(array $productIds, Channel $channel, string $type = 'both'): array
    {
        $results = [];

        foreach ($productIds as $productId) {
            try {
                $product = Product::find($productId);
                if (!$product) {
                    continue;
                }

                $optimization = $this->optimizeContent($product, $channel, $type);

                // Create AI optimization record
                $aiOptimization = AIOptimization::create([
                    'product_id' => $product->id,
                    'channel_id' => $channel->id,
                    'channel_type' => $channel->channel_type,
                    'optimization_type' => $type,
                    'original_title' => $product->title,
                    'original_description' => $product->description,
                    'optimized_title' => $optimization['optimized_title'],
                    'optimized_description' => $optimization['optimized_description'],
                    'quality_score' => $optimization['quality_score'],
                    'ai_reasoning' => $optimization['ai_reasoning'],
                    'improvements_made' => $optimization['improvements_made'],
                    'keywords_added' => $optimization['keywords_added'],
                    'channel_guidelines' => $optimization['channel_guidelines'],
                    'original_title_length' => mb_strlen($product->title ?? ''),
                    'original_description_length' => mb_strlen($product->description ?? ''),
                    'optimized_title_length' => $optimization['optimized_title_length'] ?? null,
                    'optimized_description_length' => $optimization['optimized_description_length'] ?? null,
                    'product_data_used' => $this->prepareProductData($product),
                    'status' => 'pending',
                ]);

                $results[] = [
                    'product_id' => $product->id,
                    'optimization_id' => $aiOptimization->id,
                    'success' => true,
                ];
            } catch (\Exception $e) {
                Log::error('Failed to optimize product content', [
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
     * Get optimization for preview without saving
     */
    public function previewOptimization(Product $product, Channel $channel, string $type = 'both'): array
    {
        return $this->optimizeContent($product, $channel, $type);
    }
}
