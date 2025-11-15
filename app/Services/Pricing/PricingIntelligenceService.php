<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\Variant;
use App\Models\Channel;
use App\Models\CompetitorProduct;
use App\Models\PriceChange;
use Illuminate\Support\Facades\DB;

class PricingIntelligenceService
{
    /**
     * Get competitive analysis for a product
     */
    public function getCompetitiveAnalysis(Product $product, ?Channel $channel = null): array
    {
        $query = CompetitorProduct::where('product_id', $product->id)
            ->where('active', true);

        if ($channel) {
            $query->where('channel_id', $channel->id);
        }

        $competitors = $query->with('priceHistory')->get();

        if ($competitors->isEmpty()) {
            return [
                'has_competitors' => false,
                'message' => 'No active competitors tracked for this product',
            ];
        }

        $prices = $competitors->pluck('current_price')->filter()->values();

        return [
            'has_competitors' => true,
            'competitors_count' => $competitors->count(),
            'lowest_price' => $prices->min(),
            'highest_price' => $prices->max(),
            'average_price' => round($prices->average(), 2),
            'median_price' => $this->calculateMedian($prices->toArray()),
            'price_range' => round($prices->max() - $prices->min(), 2),
            'current_product_price' => $product->variants->first()?->price,
            'position' => $this->calculatePricePosition(
                $product->variants->first()?->price,
                $prices->toArray()
            ),
            'competitors' => $competitors->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->competitor_name,
                'price' => $c->current_price,
                'url' => $c->competitor_product_url,
                'in_stock' => $c->in_stock,
                'last_checked' => $c->last_checked_at?->diffForHumans(),
                'price_trend' => $c->getPriceTrend(),
            ]),
        ];
    }

    /**
     * Get price position (percentile)
     */
    protected function calculatePricePosition(?float $price, array $competitorPrices): ?array
    {
        if (!$price || empty($competitorPrices)) {
            return null;
        }

        $allPrices = array_merge([$price], $competitorPrices);
        sort($allPrices);

        $position = array_search($price, $allPrices);
        $percentile = ($position / (count($allPrices) - 1)) * 100;

        return [
            'rank' => $position + 1,
            'total' => count($allPrices),
            'percentile' => round($percentile, 1),
            'description' => $this->getPricePositionDescription($percentile),
        ];
    }

    /**
     * Get human-readable price position description
     */
    protected function getPricePositionDescription(float $percentile): string
    {
        return match (true) {
            $percentile <= 10 => 'Lowest price',
            $percentile <= 25 => 'Very competitive',
            $percentile <= 50 => 'Below average',
            $percentile <= 75 => 'Above average',
            $percentile <= 90 => 'Premium pricing',
            default => 'Highest price',
        };
    }

    /**
     * Calculate median price
     */
    protected function calculateMedian(array $prices): ?float
    {
        if (empty($prices)) {
            return null;
        }

        sort($prices);
        $count = count($prices);
        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($prices[$middle - 1] + $prices[$middle]) / 2;
        }

        return $prices[$middle];
    }

    /**
     * Get price history and trends
     */
    public function getPriceHistory(Product $product, int $days = 30): array
    {
        $priceChanges = PriceChange::where('product_id', $product->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get();

        if ($priceChanges->isEmpty()) {
            return [
                'has_history' => false,
                'message' => 'No price history available',
            ];
        }

        $changes = $priceChanges->map(fn($change) => [
            'date' => $change->created_at->format('Y-m-d H:i:s'),
            'old_price' => $change->old_price,
            'new_price' => $change->new_price,
            'change_percent' => $change->price_difference_percent,
            'source' => $change->change_source,
            'reason' => $change->reason,
        ]);

        return [
            'has_history' => true,
            'changes_count' => $priceChanges->count(),
            'total_price_change' => round(
                $priceChanges->last()->new_price - $priceChanges->first()->old_price,
                2
            ),
            'average_price' => round($priceChanges->avg('new_price'), 2),
            'lowest_price' => $priceChanges->min('new_price'),
            'highest_price' => $priceChanges->max('new_price'),
            'changes' => $changes,
        ];
    }

    /**
     * Get pricing recommendations
     */
    public function getPricingRecommendations(Product $product, Channel $channel): array
    {
        $analysis = $this->getCompetitiveAnalysis($product, $channel);

        if (!$analysis['has_competitors']) {
            return [
                'has_recommendations' => false,
                'message' => 'No competitors to analyze',
            ];
        }

        $currentPrice = $product->variants->first()?->price;
        $recommendations = [];

        // Recommendation 1: Match lowest competitor
        if ($currentPrice > $analysis['lowest_price']) {
            $recommendations[] = [
                'type' => 'match_lowest',
                'title' => 'Match Lowest Competitor',
                'suggested_price' => $analysis['lowest_price'],
                'change' => round($analysis['lowest_price'] - $currentPrice, 2),
                'change_percent' => round((($analysis['lowest_price'] - $currentPrice) / $currentPrice) * 100, 2),
                'reasoning' => 'Matching the lowest competitor price could increase competitiveness',
                'risk' => 'low',
                'priority' => 'high',
            ];
        }

        // Recommendation 2: Beat lowest competitor by 5%
        $beatLowestPrice = round($analysis['lowest_price'] * 0.95, 2);
        if ($currentPrice > $beatLowestPrice) {
            $recommendations[] = [
                'type' => 'beat_lowest',
                'title' => 'Beat Lowest Competitor by 5%',
                'suggested_price' => $beatLowestPrice,
                'change' => round($beatLowestPrice - $currentPrice, 2),
                'change_percent' => round((($beatLowestPrice - $currentPrice) / $currentPrice) * 100, 2),
                'reasoning' => 'Undercutting competitors by 5% could capture more market share',
                'risk' => 'medium',
                'priority' => 'high',
            ];
        }

        // Recommendation 3: Match average price
        if (abs($currentPrice - $analysis['average_price']) > 1) {
            $recommendations[] = [
                'type' => 'match_average',
                'title' => 'Match Market Average',
                'suggested_price' => $analysis['average_price'],
                'change' => round($analysis['average_price'] - $currentPrice, 2),
                'change_percent' => round((($analysis['average_price'] - $currentPrice) / $currentPrice) * 100, 2),
                'reasoning' => 'Aligning with market average provides balanced positioning',
                'risk' => 'low',
                'priority' => 'medium',
            ];
        }

        // Recommendation 4: Premium positioning
        $premiumPrice = round($analysis['average_price'] * 1.1, 2);
        $recommendations[] = [
            'type' => 'premium',
            'title' => 'Premium Positioning (10% above average)',
            'suggested_price' => $premiumPrice,
            'change' => round($premiumPrice - $currentPrice, 2),
            'change_percent' => round((($premiumPrice - $currentPrice) / $currentPrice) * 100, 2),
            'reasoning' => 'Premium pricing for differentiated value proposition',
            'risk' => 'high',
            'priority' => 'low',
        ];

        return [
            'has_recommendations' => true,
            'current_price' => $currentPrice,
            'market_context' => [
                'lowest' => $analysis['lowest_price'],
                'average' => $analysis['average_price'],
                'highest' => $analysis['highest_price'],
            ],
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Get pricing performance metrics
     */
    public function getPerformanceMetrics(Product $product, int $days = 30): array
    {
        $priceChanges = PriceChange::where('product_id', $product->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('sales_after')
            ->get();

        if ($priceChanges->isEmpty()) {
            return [
                'has_data' => false,
                'message' => 'Not enough performance data available',
            ];
        }

        $totalSalesBefore = $priceChanges->sum('sales_before');
        $totalSalesAfter = $priceChanges->sum('sales_after');
        $totalUnitsBefore = $priceChanges->sum('units_sold_before');
        $totalUnitsAfter = $priceChanges->sum('units_sold_after');

        $priceIncreases = $priceChanges->filter(fn($c) => $c->isPriceIncrease());
        $priceDecreases = $priceChanges->filter(fn($c) => $c->isPriceDecrease());

        return [
            'has_data' => true,
            'period_days' => $days,
            'total_changes' => $priceChanges->count(),
            'price_increases' => $priceIncreases->count(),
            'price_decreases' => $priceDecreases->count(),
            'sales_impact' => [
                'before' => $totalSalesBefore,
                'after' => $totalSalesAfter,
                'change' => $totalSalesAfter - $totalSalesBefore,
                'change_percent' => $totalSalesBefore > 0
                    ? round((($totalSalesAfter - $totalSalesBefore) / $totalSalesBefore) * 100, 2)
                    : 0,
            ],
            'units_impact' => [
                'before' => $totalUnitsBefore,
                'after' => $totalUnitsAfter,
                'change' => $totalUnitsAfter - $totalUnitsBefore,
                'change_percent' => $totalUnitsBefore > 0
                    ? round((($totalUnitsAfter - $totalUnitsBefore) / $totalUnitsBefore) * 100, 2)
                    : 0,
            ],
            'average_revenue_per_unit' => [
                'before' => $totalUnitsBefore > 0 ? round($totalSalesBefore / $totalUnitsBefore, 2) : 0,
                'after' => $totalUnitsAfter > 0 ? round($totalSalesAfter / $totalUnitsAfter, 2) : 0,
            ],
        ];
    }

    /**
     * Detect pricing opportunities
     */
    public function detectOpportunities(Product $product): array
    {
        $opportunities = [];

        // Check for out-of-stock competitors
        $outOfStockCompetitors = CompetitorProduct::where('product_id', $product->id)
            ->where('active', true)
            ->where('in_stock', false)
            ->count();

        if ($outOfStockCompetitors > 0) {
            $opportunities[] = [
                'type' => 'competitor_out_of_stock',
                'title' => 'Competitors Out of Stock',
                'description' => "{$outOfStockCompetitors} competitor(s) are currently out of stock",
                'action' => 'Consider increasing price to capture demand',
                'priority' => 'high',
            ];
        }

        // Check for recent competitor price increases
        $recentIncreases = CompetitorProduct::where('product_id', $product->id)
            ->where('active', true)
            ->where('last_price_change_at', '>=', now()->subDays(7))
            ->whereHas('priceHistory', function ($q) {
                $q->where('price_change', '>', 0)
                    ->where('scraped_at', '>=', now()->subDays(7));
            })
            ->count();

        if ($recentIncreases > 0) {
            $opportunities[] = [
                'type' => 'competitor_price_increase',
                'title' => 'Competitors Raised Prices',
                'description' => "{$recentIncreases} competitor(s) increased prices recently",
                'action' => 'Consider raising your price while maintaining competitive edge',
                'priority' => 'medium',
            ];
        }

        // Check for significant price gaps
        $analysis = $this->getCompetitiveAnalysis($product);
        if ($analysis['has_competitors']) {
            $currentPrice = $product->variants->first()?->price;

            if ($currentPrice && $currentPrice < $analysis['lowest_price'] * 0.9) {
                $opportunities[] = [
                    'type' => 'underpriced',
                    'title' => 'Significantly Underpriced',
                    'description' => 'Your price is more than 10% below the lowest competitor',
                    'action' => 'Consider increasing price to improve margins',
                    'priority' => 'high',
                ];
            }
        }

        return $opportunities;
    }

    /**
     * Get market insights
     */
    public function getMarketInsights(?Channel $channel = null, int $days = 30): array
    {
        $query = DB::table('competitor_prices as cp')
            ->join('competitor_products as cprod', 'cp.competitor_product_id', '=', 'cprod.id')
            ->where('cp.scraped_at', '>=', now()->subDays($days));

        if ($channel) {
            $query->where('cprod.channel_id', $channel->id);
        }

        $priceData = $query->select(
            DB::raw('DATE(cp.scraped_at) as date'),
            DB::raw('AVG(cp.price) as avg_price'),
            DB::raw('MIN(cp.price) as min_price'),
            DB::raw('MAX(cp.price) as max_price'),
            DB::raw('COUNT(DISTINCT cprod.id) as competitors_count')
        )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'period_days' => $days,
            'channel' => $channel?->name ?? 'All Channels',
            'daily_insights' => $priceData->map(fn($d) => [
                'date' => $d->date,
                'average_price' => round($d->avg_price, 2),
                'lowest_price' => round($d->min_price, 2),
                'highest_price' => round($d->max_price, 2),
                'price_range' => round($d->max_price - $d->min_price, 2),
                'competitors_count' => $d->competitors_count,
            ]),
        ];
    }
}
