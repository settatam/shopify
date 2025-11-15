<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\Variant;
use App\Models\Channel;
use App\Models\PricingRule;
use App\Models\PriceSuggestion;
use App\Models\PriceChange;
use App\Models\CompetitorProduct;
use Illuminate\Support\Facades\Log;

class AutomatedPricingEngine
{
    protected PricingIntelligenceService $intelligenceService;

    public function __construct(PricingIntelligenceService $intelligenceService)
    {
        $this->intelligenceService = $intelligenceService;
    }

    /**
     * Apply pricing rules for a product
     */
    public function applyRules(Product $product, ?Variant $variant = null, ?Channel $channel = null): array
    {
        $rules = $this->getApplicableRules($product, $variant, $channel);

        if ($rules->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No applicable pricing rules found',
            ];
        }

        $suggestions = [];

        foreach ($rules as $rule) {
            if (!$rule->canApply()) {
                continue;
            }

            $suggestion = $this->applyRule($rule, $product, $variant, $channel);

            if ($suggestion) {
                $suggestions[] = $suggestion;

                // If auto-apply is enabled and doesn't require approval
                if ($rule->auto_apply && !$rule->require_approval) {
                    $this->applySuggestion($suggestion);
                }

                $rule->markAsChecked();
            }
        }

        return [
            'success' => true,
            'rules_checked' => $rules->count(),
            'suggestions_created' => count($suggestions),
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Get applicable rules for product/variant/channel
     */
    protected function getApplicableRules(Product $product, ?Variant $variant, ?Channel $channel): \Illuminate\Database\Eloquent\Collection
    {
        $query = PricingRule::active()->byPriority();

        // Filter by product
        $query->where(function ($q) use ($product) {
            $q->where('product_id', $product->id)
                ->orWhereNull('product_id');
        });

        // Filter by variant if specified
        if ($variant) {
            $query->where(function ($q) use ($variant) {
                $q->where('variant_id', $variant->id)
                    ->orWhereNull('variant_id');
            });
        }

        // Filter by channel if specified
        if ($channel) {
            $query->where(function ($q) use ($channel) {
                $q->where('channel_id', $channel->id)
                    ->orWhere('channel_type', $channel->channel_type)
                    ->orWhereNull('channel_id');
            });
        }

        return $query->get();
    }

    /**
     * Apply a single pricing rule
     */
    protected function applyRule(PricingRule $rule, Product $product, ?Variant $variant, ?Channel $channel): ?PriceSuggestion
    {
        $currentPrice = $this->getCurrentPrice($product, $variant, $channel);

        if (!$currentPrice) {
            return null;
        }

        $suggestedPrice = match ($rule->rule_type) {
            'match_competitor' => $this->calculateMatchCompetitorPrice($rule, $product, $channel),
            'beat_competitor' => $this->calculateBeatCompetitorPrice($rule, $product, $channel),
            'beat_competitor_fixed' => $this->calculateBeatCompetitorFixedPrice($rule, $product, $channel),
            'stay_below_competitor' => $this->calculateStayBelowCompetitorPrice($rule, $product, $channel),
            'stay_above_competitor' => $this->calculateStayAboveCompetitorPrice($rule, $product, $channel),
            'maintain_margin' => $this->calculateMaintainMarginPrice($rule, $currentPrice),
            'market_based' => $this->calculateMarketBasedPrice($rule, $product, $channel),
            'inventory_based' => $this->calculateInventoryBasedPrice($rule, $product, $currentPrice),
            'custom' => $this->calculateCustomPrice($rule, $currentPrice),
            default => null,
        };

        if (!$suggestedPrice || $suggestedPrice == $currentPrice) {
            return null;
        }

        // Validate against boundaries
        $suggestedPrice = $this->applyBoundaries($suggestedPrice, $currentPrice, $rule);

        // Validate price
        $errors = $rule->validatePrice($suggestedPrice, $currentPrice);
        if (!empty($errors)) {
            Log::warning('Price suggestion failed validation', [
                'rule_id' => $rule->id,
                'suggested_price' => $suggestedPrice,
                'errors' => $errors,
            ]);
            return null;
        }

        // Get competitive context
        $competitiveAnalysis = $this->intelligenceService->getCompetitiveAnalysis($product, $channel);

        // Create price suggestion
        return PriceSuggestion::create([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'channel_id' => $channel?->id,
            'pricing_rule_id' => $rule->id,
            'current_price' => $currentPrice,
            'suggested_price' => $suggestedPrice,
            'price_difference' => $suggestedPrice - $currentPrice,
            'price_difference_percent' => (($suggestedPrice - $currentPrice) / $currentPrice) * 100,
            'reasoning' => $this->generateReasoning($rule, $currentPrice, $suggestedPrice, $competitiveAnalysis),
            'analysis_data' => [
                'rule_type' => $rule->rule_type,
                'competitive_analysis' => $competitiveAnalysis,
            ],
            'lowest_competitor_price' => $competitiveAnalysis['lowest_price'] ?? null,
            'highest_competitor_price' => $competitiveAnalysis['highest_price'] ?? null,
            'average_competitor_price' => $competitiveAnalysis['average_price'] ?? null,
            'competitors_checked' => $competitiveAnalysis['competitors_count'] ?? 0,
            'current_margin_percent' => $rule->calculateMarginPercent($currentPrice),
            'suggested_margin_percent' => $rule->calculateMarginPercent($suggestedPrice),
            'current_margin_amount' => $rule->cost_basis ? $currentPrice - $rule->cost_basis : null,
            'suggested_margin_amount' => $rule->cost_basis ? $suggestedPrice - $rule->cost_basis : null,
            'confidence_score' => $this->calculateConfidenceScore($rule, $competitiveAnalysis),
            'priority' => $this->calculatePriority($currentPrice, $suggestedPrice, $competitiveAnalysis),
            'expires_at' => now()->addHours(24), // Suggestions expire after 24 hours
        ]);
    }

    /**
     * Calculate match competitor price
     */
    protected function calculateMatchCompetitorPrice(PricingRule $rule, Product $product, ?Channel $channel): ?float
    {
        $competitors = $this->getCompetitorPrices($product, $channel, $rule->target_competitor_id);

        if ($competitors->isEmpty()) {
            return null;
        }

        return match ($rule->competitor_selection) {
            'lowest' => $competitors->min('current_price'),
            'highest' => $competitors->max('current_price'),
            'average' => $competitors->avg('current_price'),
            'specific' => $competitors->first()?->current_price,
            default => $competitors->avg('current_price'),
        };
    }

    /**
     * Calculate beat competitor price (by percentage)
     */
    protected function calculateBeatCompetitorPrice(PricingRule $rule, Product $product, ?Channel $channel): ?float
    {
        $competitorPrice = $this->calculateMatchCompetitorPrice($rule, $product, $channel);

        if (!$competitorPrice) {
            return null;
        }

        $adjustmentMultiplier = 1 - ($rule->adjustment_value / 100);
        return round($competitorPrice * $adjustmentMultiplier, 2);
    }

    /**
     * Calculate beat competitor price (by fixed amount)
     */
    protected function calculateBeatCompetitorFixedPrice(PricingRule $rule, Product $product, ?Channel $channel): ?float
    {
        $competitorPrice = $this->calculateMatchCompetitorPrice($rule, $product, $channel);

        if (!$competitorPrice) {
            return null;
        }

        return round($competitorPrice - $rule->adjustment_value, 2);
    }

    /**
     * Calculate stay below competitor price
     */
    protected function calculateStayBelowCompetitorPrice(PricingRule $rule, Product $product, ?Channel $channel): ?float
    {
        $competitorPrice = $this->calculateMatchCompetitorPrice($rule, $product, $channel);

        if (!$competitorPrice) {
            return null;
        }

        $maxPrice = $competitorPrice * (1 - ($rule->adjustment_value / 100));
        return round($maxPrice, 2);
    }

    /**
     * Calculate stay above competitor price
     */
    protected function calculateStayAboveCompetitorPrice(PricingRule $rule, Product $product, ?Channel $channel): ?float
    {
        $competitorPrice = $this->calculateMatchCompetitorPrice($rule, $product, $channel);

        if (!$competitorPrice) {
            return null;
        }

        $minPrice = $competitorPrice * (1 + ($rule->adjustment_value / 100));
        return round($minPrice, 2);
    }

    /**
     * Calculate maintain margin price
     */
    protected function calculateMaintainMarginPrice(PricingRule $rule, float $currentPrice): ?float
    {
        if (!$rule->cost_basis || !$rule->min_margin_percent) {
            return null;
        }

        // Price = Cost / (1 - Margin%)
        $targetMarginDecimal = $rule->min_margin_percent / 100;
        $targetPrice = $rule->cost_basis / (1 - $targetMarginDecimal);

        return round($targetPrice, 2);
    }

    /**
     * Calculate market-based price
     */
    protected function calculateMarketBasedPrice(PricingRule $rule, Product $product, ?Channel $channel): ?float
    {
        $analysis = $this->intelligenceService->getCompetitiveAnalysis($product, $channel);

        if (!$analysis['has_competitors']) {
            return null;
        }

        // Use median price as base
        $basePrice = $analysis['median_price'];

        // Apply adjustment if specified
        if ($rule->adjustment_value) {
            $adjustmentMultiplier = 1 + ($rule->adjustment_value / 100);
            $basePrice *= $adjustmentMultiplier;
        }

        return round($basePrice, 2);
    }

    /**
     * Calculate inventory-based price
     */
    protected function calculateInventoryBasedPrice(PricingRule $rule, Product $product, float $currentPrice): ?float
    {
        $inventory = $product->variants->sum('inventory_quantity') ?? 0;

        $config = $rule->strategy_config ?? [];
        $lowStockThreshold = $config['low_stock_threshold'] ?? 10;
        $highStockThreshold = $config['high_stock_threshold'] ?? 100;
        $lowStockMultiplier = $config['low_stock_multiplier'] ?? 1.1; // Increase 10% when low
        $highStockMultiplier = $config['high_stock_multiplier'] ?? 0.95; // Decrease 5% when high

        if ($inventory <= $lowStockThreshold) {
            return round($currentPrice * $lowStockMultiplier, 2);
        } elseif ($inventory >= $highStockThreshold) {
            return round($currentPrice * $highStockMultiplier, 2);
        }

        return $currentPrice;
    }

    /**
     * Calculate custom price based on formula
     */
    protected function calculateCustomPrice(PricingRule $rule, float $currentPrice): ?float
    {
        $config = $rule->strategy_config ?? [];
        $formula = $config['formula'] ?? null;

        if (!$formula) {
            return null;
        }

        // Simple formula evaluation (extend as needed)
        // Example: "current_price * 1.1" or "current_price + 5"
        $price = $currentPrice;
        try {
            eval("\$result = {$formula};");
            return round($result, 2);
        } catch (\Exception $e) {
            Log::error('Custom pricing formula failed', [
                'rule_id' => $rule->id,
                'formula' => $formula,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get competitor prices
     */
    protected function getCompetitorPrices(Product $product, ?Channel $channel, ?int $targetCompetitorId)
    {
        $query = CompetitorProduct::where('product_id', $product->id)
            ->where('active', true)
            ->where('in_stock', true);

        if ($targetCompetitorId) {
            $query->where('id', $targetCompetitorId);
        } elseif ($channel) {
            $query->where('channel_id', $channel->id);
        }

        return $query->get();
    }

    /**
     * Get current price
     */
    protected function getCurrentPrice(Product $product, ?Variant $variant, ?Channel $channel): ?float
    {
        if ($variant) {
            return $variant->price;
        }

        return $product->variants->first()?->price;
    }

    /**
     * Apply price boundaries
     */
    protected function applyBoundaries(float $price, float $currentPrice, PricingRule $rule): float
    {
        // Apply min/max price boundaries
        if ($rule->min_price && $price < $rule->min_price) {
            $price = $rule->min_price;
        }

        if ($rule->max_price && $price > $rule->max_price) {
            $price = $rule->max_price;
        }

        // Apply max change limits
        if ($rule->max_price_change_percent) {
            $maxChange = $currentPrice * ($rule->max_price_change_percent / 100);
            $change = abs($price - $currentPrice);

            if ($change > $maxChange) {
                if ($price > $currentPrice) {
                    $price = $currentPrice + $maxChange;
                } else {
                    $price = $currentPrice - $maxChange;
                }
            }
        }

        if ($rule->max_price_change_amount) {
            $change = abs($price - $currentPrice);

            if ($change > $rule->max_price_change_amount) {
                if ($price > $currentPrice) {
                    $price = $currentPrice + $rule->max_price_change_amount;
                } else {
                    $price = $currentPrice - $rule->max_price_change_amount;
                }
            }
        }

        return round($price, 2);
    }

    /**
     * Generate reasoning text
     */
    protected function generateReasoning(PricingRule $rule, float $currentPrice, float $suggestedPrice, array $analysis): string
    {
        $change = $suggestedPrice - $currentPrice;
        $changePercent = round((($change) / $currentPrice) * 100, 2);
        $direction = $change > 0 ? 'increase' : 'decrease';

        $reasoning = "Based on the '{$rule->name}' pricing rule ({$rule->rule_type}), ";
        $reasoning .= "we suggest a {$direction} from \${$currentPrice} to \${$suggestedPrice} ";
        $reasoning .= "({$changePercent}%). ";

        if ($analysis['has_competitors']) {
            $reasoning .= "Current market analysis shows {$analysis['competitors_count']} active competitors ";
            $reasoning .= "with prices ranging from \${$analysis['lowest_price']} to \${$analysis['highest_price']} ";
            $reasoning .= "(average: \${$analysis['average_price']}). ";
        }

        return $reasoning;
    }

    /**
     * Calculate confidence score
     */
    protected function calculateConfidenceScore(PricingRule $rule, array $analysis): float
    {
        $score = 50; // Base score

        // More competitors = higher confidence
        if ($analysis['has_competitors']) {
            $score += min($analysis['competitors_count'] * 10, 30);
        }

        // Recent price checks = higher confidence
        // Well-defined rules = higher confidence
        if ($rule->min_price && $rule->max_price) {
            $score += 10;
        }

        return min($score, 100);
    }

    /**
     * Calculate priority
     */
    protected function calculatePriority(float $currentPrice, float $suggestedPrice, array $analysis): string
    {
        $changePercent = abs((($suggestedPrice - $currentPrice) / $currentPrice) * 100);

        if ($changePercent > 10) {
            return 'urgent';
        } elseif ($changePercent > 5) {
            return 'high';
        } elseif ($changePercent > 2) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Apply a price suggestion
     */
    public function applySuggestion(PriceSuggestion $suggestion): bool
    {
        try {
            // Update variant price
            if ($suggestion->variant_id) {
                $variant = Variant::find($suggestion->variant_id);
                $variant->update(['price' => $suggestion->suggested_price]);
            } else {
                // Update all variants of the product
                $product = Product::find($suggestion->product_id);
                $product->variants->each(function ($variant) use ($suggestion) {
                    $variant->update(['price' => $suggestion->suggested_price]);
                });
            }

            // Mark suggestion as approved
            $suggestion->update(['status' => 'approved']);

            // Create price change record
            PriceChange::create([
                'product_id' => $suggestion->product_id,
                'variant_id' => $suggestion->variant_id,
                'channel_id' => $suggestion->channel_id,
                'old_price' => $suggestion->current_price,
                'new_price' => $suggestion->suggested_price,
                'price_difference' => $suggestion->price_difference,
                'price_difference_percent' => $suggestion->price_difference_percent,
                'change_source' => 'automated_rule',
                'pricing_rule_id' => $suggestion->pricing_rule_id,
                'reason' => $suggestion->reasoning,
                'context' => $suggestion->analysis_data,
                'status' => 'applied',
                'applied_at' => now(),
            ]);

            // Increment rule usage counter
            if ($suggestion->pricing_rule_id) {
                $rule = PricingRule::find($suggestion->pricing_rule_id);
                $rule?->incrementTimesApplied();
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to apply price suggestion', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
