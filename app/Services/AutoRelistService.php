<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Channel;
use App\Models\AutoRelistCampaign;
use App\Models\AutoRelistAction;
use App\Services\AI\AIContentOptimizer;
use App\Services\AI\AICategoryMapper;
use App\Services\Pricing\PricingIntelligenceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AutoRelistService
{
    protected AIContentOptimizer $contentOptimizer;
    protected AICategoryMapper $categoryMapper;
    protected PricingIntelligenceService $pricingService;

    public function __construct(
        AIContentOptimizer $contentOptimizer,
        AICategoryMapper $categoryMapper,
        PricingIntelligenceService $pricingService
    ) {
        $this->contentOptimizer = $contentOptimizer;
        $this->categoryMapper = $categoryMapper;
        $this->pricingService = $pricingService;
    }

    /**
     * Run campaign to detect and optimize dead listings
     */
    public function runCampaign(AutoRelistCampaign $campaign): array
    {
        Log::info('Running auto-relist campaign', ['campaign_id' => $campaign->id]);

        $campaign->markAsRun();

        $deadListings = $this->detectDeadListings($campaign);

        $results = [
            'detected' => 0,
            'analyzed' => 0,
            'pending_approval' => 0,
            'auto_relisted' => 0,
            'errors' => 0,
        ];

        foreach ($deadListings as $listing) {
            try {
                $action = $this->createAction($campaign, $listing);
                $results['detected']++;

                // Optimize the listing
                $this->optimizeListing($campaign, $action);
                $results['analyzed']++;

                if ($campaign->require_approval) {
                    $action->update(['status' => 'pending_approval']);
                    $campaign->incrementPending();
                    $results['pending_approval']++;
                } else {
                    // Auto-approve and relist
                    $this->relistProduct($action);
                    $results['auto_relisted']++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to process dead listing', [
                    'product_id' => $listing['product_id'],
                    'error' => $e->getMessage(),
                ]);
                $results['errors']++;
            }
        }

        Log::info('Auto-relist campaign completed', [
            'campaign_id' => $campaign->id,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Detect dead listings based on campaign criteria
     */
    protected function detectDeadListings(AutoRelistCampaign $campaign): array
    {
        $deadListings = [];

        $channels = Channel::whereIn('id', $campaign->channel_ids ?? [])->get();

        foreach ($channels as $channel) {
            // Query for underperforming products
            $products = Product::where('shop_id', $campaign->shop_id)
                ->where('status', 'active')
                ->get();

            foreach ($products as $product) {
                $metrics = $this->getProductMetrics($product, $channel);

                if ($this->meetsDeadListingCriteria($metrics, $campaign)) {
                    // Check if already relisted too many times
                    $relistCount = AutoRelistAction::where('product_id', $product->id)
                        ->where('channel_id', $channel->id)
                        ->where('status', 'completed')
                        ->count();

                    if ($relistCount >= $campaign->max_relists_per_product) {
                        continue; // Skip, already relisted max times
                    }

                    $deadListings[] = [
                        'product_id' => $product->id,
                        'channel_id' => $channel->id,
                        'days_listed' => $metrics['days_listed'],
                        'total_views' => $metrics['total_views'],
                        'total_sales' => $metrics['total_sales'],
                        'view_to_sale_ratio' => $metrics['view_to_sale_ratio'],
                    ];
                }
            }
        }

        return $deadListings;
    }

    /**
     * Get product performance metrics
     */
    protected function getProductMetrics(Product $product, Channel $channel): array
    {
        // This would integrate with analytics/tracking system
        // For now, return simulated data

        $createdDaysAgo = $product->created_at->diffInDays(now());

        return [
            'days_listed' => $createdDaysAgo,
            'total_views' => rand(0, 50), // Would come from analytics
            'total_sales' => 0, // Would come from sales data
            'view_to_sale_ratio' => 0, // views but no sales = 0% conversion
        ];
    }

    /**
     * Check if metrics meet dead listing criteria
     */
    protected function meetsDeadListingCriteria(array $metrics, AutoRelistCampaign $campaign): bool
    {
        // Must be listed for minimum days
        if ($metrics['days_listed'] < $campaign->min_days_listed) {
            return false;
        }

        // Must have low views
        if ($metrics['total_views'] > $campaign->max_views) {
            return false;
        }

        // Must have low/no sales
        if ($metrics['total_sales'] > $campaign->max_sales) {
            return false;
        }

        // Check view-to-sale ratio if specified
        if ($campaign->min_view_to_sale_ratio && $metrics['total_views'] > 0) {
            $actualRatio = ($metrics['total_sales'] / $metrics['total_views']) * 100;
            if ($actualRatio >= $campaign->min_view_to_sale_ratio) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create auto-relist action
     */
    protected function createAction(AutoRelistCampaign $campaign, array $listing): AutoRelistAction
    {
        $product = Product::find($listing['product_id']);
        $channel = Channel::find($listing['channel_id']);

        $campaign->incrementDetected();

        return AutoRelistAction::create([
            'campaign_id' => $campaign->id,
            'product_id' => $product->id,
            'channel_id' => $channel->id,
            'days_listed' => $listing['days_listed'],
            'total_views' => $listing['total_views'],
            'total_sales' => $listing['total_sales'],
            'view_to_sale_ratio' => $listing['view_to_sale_ratio'],
            'detected_at' => now(),
            'status' => 'analyzing',
            'original_title' => $product->title,
            'original_description' => $product->description,
            'original_category_id' => $product->category,
            'original_attributes' => $product->attributes ?? [],
            'original_images' => $product->images ?? [],
            'original_price' => $product->variants->first()?->price,
            'original_listed_at' => $product->created_at,
            'views_before' => $listing['total_views'],
            'sales_before' => $listing['total_sales'],
            'conversion_rate_before' => $listing['total_views'] > 0
                ? ($listing['total_sales'] / $listing['total_views']) * 100
                : 0,
        ]);
    }

    /**
     * Optimize listing with AI
     */
    protected function optimizeListing(AutoRelistCampaign $campaign, AutoRelistAction $action): void
    {
        $product = $action->product;
        $channel = $action->channel;

        $changes = [];
        $aiAnalysis = [];

        // 1. Rewrite title
        if ($campaign->auto_rewrite_title) {
            try {
                $optimization = $this->contentOptimizer->optimizeContent($product, $channel, 'title');
                $action->new_title = $optimization['optimized_title'];
                $changes[] = 'title_rewritten';
                $aiAnalysis['title'] = [
                    'quality_score' => $optimization['quality_score'],
                    'improvements' => $optimization['improvements_made'],
                ];
            } catch (\Exception $e) {
                Log::error('Title optimization failed', ['error' => $e->getMessage()]);
                $action->new_title = $product->title; // Keep original
            }
        }

        // 2. Fix description
        if ($campaign->auto_fix_description) {
            try {
                $optimization = $this->contentOptimizer->optimizeContent($product, $channel, 'description');
                $action->new_description = $optimization['optimized_description'];
                $changes[] = 'description_improved';
                $aiAnalysis['description'] = [
                    'quality_score' => $optimization['quality_score'],
                    'keywords_added' => $optimization['keywords_added'],
                ];
            } catch (\Exception $e) {
                Log::error('Description optimization failed', ['error' => $e->getMessage()]);
                $action->new_description = $product->description; // Keep original
            }
        }

        // 3. Fix category
        if ($campaign->auto_fix_category) {
            try {
                // Would fetch categories and map
                // For now, keep original
                $action->new_category_id = $product->category;
            } catch (\Exception $e) {
                $action->new_category_id = $product->category;
            }
        }

        // 4. Add missing attributes
        if ($campaign->auto_add_attributes) {
            $newAttributes = $this->suggestMissingAttributes($product, $channel);
            $action->new_attributes = $newAttributes;
            if ($newAttributes !== ($product->attributes ?? [])) {
                $changes[] = 'attributes_added';
                $aiAnalysis['attributes'] = $newAttributes;
            }
        }

        // 5. Swap/reorder images
        if ($campaign->auto_swap_images) {
            $newImages = $this->optimizeImageOrder($product);
            $action->new_images = $newImages;
            if ($newImages !== ($product->images ?? [])) {
                $changes[] = 'images_reordered';
            }
        }

        // 6. Adjust price
        if ($campaign->auto_adjust_price) {
            $newPrice = $this->calculateOptimalPrice($product, $channel, $campaign);
            $action->new_price = $newPrice;
            if ($newPrice !== $product->variants->first()?->price) {
                $changes[] = 'price_adjusted';
                $aiAnalysis['price'] = [
                    'strategy' => $campaign->price_adjustment_strategy,
                    'old_price' => $product->variants->first()?->price,
                    'new_price' => $newPrice,
                ];
            }
        }

        // 7. Calculate optimal relist time
        if ($campaign->optimize_relist_time) {
            $optimalTime = $this->calculateOptimalRelistTime($campaign);
            $action->relist_scheduled_at = $optimalTime;
        }

        $action->update([
            'changes_made' => $changes,
            'ai_analysis' => $aiAnalysis,
            'optimization_reasoning' => $this->generateReasoning($changes, $aiAnalysis),
        ]);
    }

    /**
     * Suggest missing attributes
     */
    protected function suggestMissingAttributes(Product $product, Channel $channel): array
    {
        $currentAttributes = $product->attributes ?? [];

        $suggestions = [
            'Brand' => $product->brand ?? 'Unbranded',
            'Condition' => 'New',
            'Type' => $product->product_type ?? null,
        ];

        // Add variant attributes
        if ($product->variants->count() > 0) {
            $variant = $product->variants->first();
            $variantAttrs = $variant->attributes ?? [];

            if (isset($variantAttrs['color'])) {
                $suggestions['Color'] = $variantAttrs['color'];
            }

            if (isset($variantAttrs['size'])) {
                $suggestions['Size'] = $variantAttrs['size'];
            }
        }

        return array_merge($currentAttributes, array_filter($suggestions));
    }

    /**
     * Optimize image order (put best image first)
     */
    protected function optimizeImageOrder(Product $product): array
    {
        $images = $product->images ?? [];

        if (count($images) <= 1) {
            return $images;
        }

        // Simple strategy: move the last image to first
        // In production, you'd use AI to determine best image
        $reordered = $images;
        $lastImage = array_pop($reordered);
        array_unshift($reordered, $lastImage);

        return $reordered;
    }

    /**
     * Calculate optimal price
     */
    protected function calculateOptimalPrice(Product $product, Channel $channel, AutoRelistCampaign $campaign): float
    {
        $currentPrice = $product->variants->first()?->price ?? 0;

        $newPrice = match ($campaign->price_adjustment_strategy) {
            'decrease' => $currentPrice * (1 - ($campaign->price_adjustment_percent / 100)),
            'increase' => $currentPrice * (1 + ($campaign->price_adjustment_percent / 100)),
            'market_based' => $this->getMarketBasedPrice($product, $channel),
            'none' => $currentPrice,
        };

        // Apply floor and ceiling
        if ($campaign->min_price_floor && $newPrice < $campaign->min_price_floor) {
            $newPrice = $campaign->min_price_floor;
        }

        if ($campaign->max_price_ceiling && $newPrice > $campaign->max_price_ceiling) {
            $newPrice = $campaign->max_price_ceiling;
        }

        return round($newPrice, 2);
    }

    /**
     * Get market-based price
     */
    protected function getMarketBasedPrice(Product $product, Channel $channel): float
    {
        try {
            $recommendations = $this->pricingService->getPricingRecommendations($product, $channel);

            if ($recommendations['has_recommendations']) {
                return $recommendations['recommendations'][0]['suggested_price'];
            }
        } catch (\Exception $e) {
            Log::error('Market-based pricing failed', ['error' => $e->getMessage()]);
        }

        return $product->variants->first()?->price ?? 0;
    }

    /**
     * Calculate optimal relist time
     */
    protected function calculateOptimalRelistTime(AutoRelistCampaign $campaign): \DateTime
    {
        $now = now();

        // If specific time is set
        if ($campaign->preferred_relist_time) {
            $time = \Carbon\Carbon::parse($campaign->preferred_relist_time);
            $scheduled = $now->copy()->setTime($time->hour, $time->minute, 0);

            // If time has passed today, schedule for tomorrow
            if ($scheduled->isPast()) {
                $scheduled->addDay();
            }

            // Check preferred days
            if ($campaign->preferred_relist_days && !in_array($scheduled->dayOfWeek, $campaign->preferred_relist_days)) {
                // Find next preferred day
                while (!in_array($scheduled->dayOfWeek, $campaign->preferred_relist_days)) {
                    $scheduled->addDay();
                }
            }

            return $scheduled;
        }

        // Default: 10 AM tomorrow
        return $now->copy()->addDay()->setTime(10, 0, 0);
    }

    /**
     * Generate optimization reasoning
     */
    protected function generateReasoning(array $changes, array $aiAnalysis): string
    {
        $reasons = [];

        if (in_array('title_rewritten', $changes)) {
            $score = $aiAnalysis['title']['quality_score'] ?? 0;
            $reasons[] = "Title rewritten with AI (quality score: {$score}%)";
        }

        if (in_array('description_improved', $changes)) {
            $keywords = count($aiAnalysis['description']['keywords_added'] ?? []);
            $reasons[] = "Description improved with {$keywords} SEO keywords added";
        }

        if (in_array('price_adjusted', $changes)) {
            $strategy = $aiAnalysis['price']['strategy'] ?? 'adjusted';
            $reasons[] = "Price {$strategy} to improve competitiveness";
        }

        if (in_array('images_reordered', $changes)) {
            $reasons[] = "Primary image changed to attract more buyers";
        }

        if (in_array('attributes_added', $changes)) {
            $reasons[] = "Missing attributes added for better discoverability";
        }

        return implode('. ', $reasons) . '.';
    }

    /**
     * Relist the product
     */
    public function relistProduct(AutoRelistAction $action): bool
    {
        try {
            $product = $action->product;
            $channel = $action->channel;

            // Apply optimizations
            $product->update([
                'title' => $action->new_title ?? $action->original_title,
                'description' => $action->new_description ?? $action->original_description,
                'category' => $action->new_category_id ?? $action->original_category_id,
                'attributes' => $action->new_attributes ?? $action->original_attributes,
                'images' => $action->new_images ?? $action->original_images,
            ]);

            // Update price
            if ($action->new_price) {
                $product->variants->each(function ($variant) use ($action) {
                    $variant->update(['price' => $action->new_price]);
                });
            }

            // Call channel publish API to relist
            // This would integrate with channel-specific APIs
            Log::info('Relisting product', [
                'product_id' => $product->id,
                'channel_id' => $channel->id,
            ]);

            $action->markRelisted();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to relist product', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            $action->markFailed($e->getMessage());

            return false;
        }
    }
}
