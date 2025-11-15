<?php

namespace App\Jobs\Pricing;

use App\Models\Product;
use App\Models\PricingRule;
use App\Services\Pricing\AutomatedPricingEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ApplyPricingRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 2;
    public array $backoff = [120, 240]; // Backoff in seconds

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
    public function handle(AutomatedPricingEngine $engine): void
    {
        Log::info('Starting automated pricing rules application');

        // Get all active pricing rules
        $rules = PricingRule::active()->byPriority()->get();

        if ($rules->isEmpty()) {
            Log::info('No active pricing rules found');
            return;
        }

        $productsProcessed = [];
        $suggestionsCreated = 0;
        $pricesApplied = 0;

        foreach ($rules as $rule) {
            if (!$rule->isActiveNow()) {
                continue;
            }

            try {
                // Get products for this rule
                $products = $this->getProductsForRule($rule);

                foreach ($products as $product) {
                    if (in_array($product->id, $productsProcessed)) {
                        continue; // Already processed by higher priority rule
                    }

                    $result = $engine->applyRules(
                        $product,
                        $rule->variant_id ? $product->variants->find($rule->variant_id) : null,
                        $rule->channel_id ? $product->shop->channels()->find($rule->channel_id) : null
                    );

                    if ($result['success']) {
                        $suggestionsCreated += $result['suggestions_created'];

                        // If rule has auto-apply, count as applied
                        if ($rule->auto_apply && !$rule->require_approval) {
                            $pricesApplied += $result['suggestions_created'];
                        }
                    }

                    $productsProcessed[] = $product->id;
                }
            } catch (\Exception $e) {
                Log::error('Failed to apply pricing rule', [
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed automated pricing rules application', [
            'rules_checked' => $rules->count(),
            'products_processed' => count($productsProcessed),
            'suggestions_created' => $suggestionsCreated,
            'prices_applied' => $pricesApplied,
        ]);
    }

    /**
     * Get products for a pricing rule
     */
    protected function getProductsForRule(PricingRule $rule): \Illuminate\Database\Eloquent\Collection
    {
        if ($rule->product_id) {
            return Product::where('id', $rule->product_id)->get();
        }

        // Get all products for the shop
        // This is simplified - in production, you'd want to scope by shop
        return Product::active()->limit(100)->get();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Pricing rules application job failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
