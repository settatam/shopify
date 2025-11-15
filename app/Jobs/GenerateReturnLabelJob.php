<?php

namespace App\Jobs;

use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\ReturnShippingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateReturnLabelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public array $backoff = [60, 120, 300]; // 1min, 2min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ReturnRequest $returnRequest
    ) {
        $this->onQueue('returns');
    }

    /**
     * Execute the job.
     */
    public function handle(ReturnShippingService $shippingService): void
    {
        Log::info('Generating return shipping label', [
            'rma_number' => $this->returnRequest->rma_number,
        ]);

        // Check if return is still approved
        if ($this->returnRequest->status !== 'approved') {
            Log::info('Return not approved, skipping label generation', [
                'rma_number' => $this->returnRequest->rma_number,
                'status' => $this->returnRequest->status,
            ]);
            return;
        }

        // Check if label already exists
        if ($this->returnRequest->shipping()->exists()) {
            Log::info('Return label already exists', [
                'rma_number' => $this->returnRequest->rma_number,
            ]);
            return;
        }

        try {
            // Get system user for label generation
            $user = User::where('shop_id', $this->returnRequest->shop_id)->first();

            if (!$user) {
                throw new \Exception('No user found for shop');
            }

            $shippingService->generateReturnLabel($this->returnRequest, $user);

            Log::info('Return shipping label generated successfully', [
                'rma_number' => $this->returnRequest->rma_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate return label', [
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
        Log::error('Return label generation failed permanently', [
            'rma_number' => $this->returnRequest->rma_number,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify merchant
    }
}
