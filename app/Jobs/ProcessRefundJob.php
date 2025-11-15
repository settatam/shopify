<?php

namespace App\Jobs;

use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 3;
    public array $backoff = [300, 900, 1800]; // 5min, 15min, 30min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ReturnRequest $returnRequest
    ) {
        $this->onQueue('refunds');
    }

    /**
     * Execute the job.
     */
    public function handle(RefundService $refundService): void
    {
        Log::info('Processing refund for return', [
            'rma_number' => $this->returnRequest->rma_number,
        ]);

        // Check if inspection is approved
        if ($this->returnRequest->inspection_result !== 'approved') {
            Log::info('Inspection not approved, skipping refund', [
                'rma_number' => $this->returnRequest->rma_number,
                'inspection_result' => $this->returnRequest->inspection_result,
            ]);
            return;
        }

        // Check if refund already exists
        if ($this->returnRequest->refund()->exists()) {
            $refund = $this->returnRequest->refund;

            // Retry if failed
            if ($refund->status === 'failed' && $refund->canRetry()) {
                $user = User::where('shop_id', $this->returnRequest->shop_id)->first();
                $refundService->retryRefund($refund, $user);
            }

            return;
        }

        try {
            // Create refund
            $refund = $refundService->createRefundFromReturn($this->returnRequest);

            // Get system user for processing
            $user = User::where('shop_id', $this->returnRequest->shop_id)->first();

            if (!$user) {
                throw new \Exception('No user found for shop');
            }

            // Process the refund
            $refundService->processRefund($refund, $user);

            Log::info('Refund processed successfully', [
                'rma_number' => $this->returnRequest->rma_number,
                'refund_number' => $refund->refund_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process refund', [
                'rma_number' => $this->returnRequest->rma_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Refund processing failed permanently', [
            'rma_number' => $this->returnRequest->rma_number,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify merchant
    }
}
