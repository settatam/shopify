<?php

namespace App\Jobs\AI;

use App\Models\Channel;
use App\Models\Product;
use App\Models\AIOptimization;
use App\Services\AI\AIContentOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateContentOptimizationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900; // 15 minutes for batch processing
    public int $tries = 3;
    public array $backoff = [60, 120, 240]; // Backoff in seconds

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $productIds,
        public Channel $channel,
        public string $type = 'both'
    ) {
        $this->onQueue('ai-optimization');
    }

    /**
     * Execute the job.
     */
    public function handle(AIContentOptimizer $optimizer): void
    {
        Log::info('Starting batch content optimization', [
            'product_count' => count($this->productIds),
            'channel_id' => $this->channel->id,
            'channel_type' => $this->channel->channel_type,
            'optimization_type' => $this->type,
        ]);

        $successCount = 0;
        $errorCount = 0;

        foreach ($this->productIds as $productId) {
            try {
                $product = Product::find($productId);

                if (!$product) {
                    Log::warning('Product not found for optimization', [
                        'product_id' => $productId,
                    ]);
                    $errorCount++;
                    continue;
                }

                // Check if optimization already exists
                $existingOptimization = AIOptimization::where('product_id', $product->id)
                    ->where('channel_id', $this->channel->id)
                    ->where('optimization_type', $this->type)
                    ->latest()
                    ->first();

                if ($existingOptimization && $existingOptimization->isPending()) {
                    Log::info('Skipping product - optimization already pending', [
                        'product_id' => $product->id,
                        'optimization_id' => $existingOptimization->id,
                    ]);
                    continue;
                }

                // Generate optimization
                $optimization = $optimizer->optimizeContent($product, $this->channel, $this->type);

                // Create AI optimization record
                AIOptimization::create([
                    'product_id' => $product->id,
                    'channel_id' => $this->channel->id,
                    'channel_type' => $this->channel->channel_type,
                    'optimization_type' => $this->type,
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
                    'product_data_used' => $optimizer->prepareProductData($product),
                    'status' => 'pending',
                ]);

                $successCount++;

                Log::info('Content optimization generated successfully', [
                    'product_id' => $product->id,
                    'quality_score' => $optimization['quality_score'],
                ]);

                // Small delay to avoid rate limiting
                usleep(100000); // 100ms delay
            } catch (\Exception $e) {
                $errorCount++;

                Log::error('Failed to generate content optimization', [
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('Batch content optimization completed', [
            'total_products' => count($this->productIds),
            'successful' => $successCount,
            'errors' => $errorCount,
            'channel_type' => $this->channel->channel_type,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Batch content optimization job failed', [
            'product_count' => count($this->productIds),
            'channel_id' => $this->channel->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
