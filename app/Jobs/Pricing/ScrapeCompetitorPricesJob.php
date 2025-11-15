<?php

namespace App\Jobs\Pricing;

use App\Models\CompetitorProduct;
use App\Services\Pricing\CompetitorPriceScraper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeCompetitorPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 2;
    public array $backoff = [60, 120]; // Backoff in seconds

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('pricing');
    }

    /**
     * Execute the job.
     */
    public function handle(CompetitorPriceScraper $scraper): void
    {
        Log::info('Starting competitor price scraping');

        // Get all competitors due for checking
        $competitors = CompetitorProduct::dueForCheck()->get();

        $successCount = 0;
        $errorCount = 0;

        foreach ($competitors as $competitor) {
            try {
                $priceData = $scraper->scrapePrice($competitor);

                if ($priceData) {
                    $successCount++;
                    Log::info('Successfully scraped competitor price', [
                        'competitor_id' => $competitor->id,
                        'price' => $priceData['price'],
                    ]);
                } else {
                    $errorCount++;
                }

                // Delay between requests to avoid rate limiting
                usleep(500000); // 0.5 second delay
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to scrape competitor price', [
                    'competitor_id' => $competitor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed competitor price scraping', [
            'total_competitors' => $competitors->count(),
            'successful' => $successCount,
            'errors' => $errorCount,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Competitor price scraping job failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
