<?php

namespace App\Jobs\Pricing;

use App\Models\CompetitorPrice;
use App\Models\CompetitorProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MonitorCompetitorPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180; // 3 minutes
    public int $tries = 2;

    protected float $significantChangeThreshold;

    /**
     * Create a new job instance.
     */
    public function __construct(float $significantChangeThreshold = 5.0)
    {
        $this->significantChangeThreshold = $significantChangeThreshold;
        $this->onQueue('pricing');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting competitor price monitoring');

        // Get recent price changes (last hour)
        $recentPrices = CompetitorPrice::where('scraped_at', '>=', now()->subHour())
            ->with('competitorProduct.product')
            ->get();

        $significantChanges = [];
        $priceIncreases = 0;
        $priceDecreases = 0;
        $outOfStock = 0;

        foreach ($recentPrices as $price) {
            // Check for significant price change
            if ($price->hasSignificantChange($this->significantChangeThreshold)) {
                $significantChanges[] = [
                    'competitor' => $price->competitorProduct,
                    'price_change' => $price,
                    'product' => $price->competitorProduct->product,
                ];

                if ($price->priceIncreased()) {
                    $priceIncreases++;
                } else {
                    $priceDecreases++;
                }
            }

            // Check for out of stock
            if (!$price->in_stock && $price->competitorProduct->in_stock) {
                $outOfStock++;

                Log::info('Competitor went out of stock', [
                    'competitor_id' => $price->competitorProduct->id,
                    'product_id' => $price->competitorProduct->product_id,
                ]);
            }
        }

        // Log summary
        if (!empty($significantChanges)) {
            Log::info('Detected significant competitor price changes', [
                'total_changes' => count($significantChanges),
                'price_increases' => $priceIncreases,
                'price_decreases' => $priceDecreases,
                'out_of_stock' => $outOfStock,
            ]);

            // In production, send notifications to users
            // Notification::send($users, new CompetitorPriceChangeNotification($significantChanges));
        }

        Log::info('Completed competitor price monitoring', [
            'prices_checked' => $recentPrices->count(),
            'significant_changes' => count($significantChanges),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Competitor price monitoring job failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
