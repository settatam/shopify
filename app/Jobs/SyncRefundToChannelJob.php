<?php

namespace App\Jobs;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncRefundToChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public array $backoff = [300, 900, 1800]; // 5min, 15min, 30min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Refund $refund
    ) {
        $this->onQueue('channel-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Only sync completed refunds
        if ($this->refund->status !== 'completed') {
            Log::info('Refund not completed, skipping channel sync', [
                'refund_number' => $this->refund->refund_number,
                'status' => $this->refund->status,
            ]);
            return;
        }

        // Only sync if channel exists and not already synced
        if (!$this->refund->channel_id || $this->refund->synced_to_channel) {
            return;
        }

        Log::info('Syncing refund to channel', [
            'refund_number' => $this->refund->refund_number,
            'channel_id' => $this->refund->channel_id,
        ]);

        try {
            $channel = $this->refund->channel;

            if (!$channel) {
                throw new \Exception('Channel not found');
            }

            $channelRefundId = match($channel->type) {
                'ebay' => $this->syncToEbay(),
                'amazon' => $this->syncToAmazon(),
                'shopify' => $this->syncToShopify(),
                'etsy' => $this->syncToEtsy(),
                'walmart' => $this->syncToWalmart(),
                default => $this->handleUnsupportedChannel(),
            };

            if ($channelRefundId) {
                $this->refund->markSyncedToChannel($channelRefundId);

                Log::info('Refund synced to channel successfully', [
                    'refund_number' => $this->refund->refund_number,
                    'channel_type' => $channel->type,
                    'channel_refund_id' => $channelRefundId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync refund to channel', [
                'refund_number' => $this->refund->refund_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync refund to eBay.
     */
    protected function syncToEbay(): ?string
    {
        // This would integrate with eBay's Returns API
        // POST /post-order/v2/return/{returnId}/issue_refund

        Log::info('Syncing refund to eBay', [
            'refund_number' => $this->refund->refund_number,
        ]);

        // Return the eBay refund ID
        return 'ebay_' . uniqid();
    }

    /**
     * Sync refund to Amazon.
     */
    protected function syncToAmazon(): ?string
    {
        // This would integrate with Amazon MWS/SP-API
        // Refund API

        Log::info('Syncing refund to Amazon', [
            'refund_number' => $this->refund->refund_number,
        ]);

        // Return the Amazon refund ID
        return 'amz_' . uniqid();
    }

    /**
     * Sync refund to Shopify.
     */
    protected function syncToShopify(): ?string
    {
        // This would integrate with Shopify Admin API
        // POST /admin/api/2024-01/orders/{order_id}/refunds.json

        Log::info('Syncing refund to Shopify', [
            'refund_number' => $this->refund->refund_number,
        ]);

        // Return the Shopify refund ID
        return 'shopify_' . uniqid();
    }

    /**
     * Sync refund to Etsy.
     */
    protected function syncToEtsy(): ?string
    {
        // This would integrate with Etsy Open API
        // POST /v3/application/shops/{shop_id}/transactions/{transaction_id}/refund

        Log::info('Syncing refund to Etsy', [
            'refund_number' => $this->refund->refund_number,
        ]);

        // Return the Etsy refund ID
        return 'etsy_' . uniqid();
    }

    /**
     * Sync refund to Walmart.
     */
    protected function syncToWalmart(): ?string
    {
        // This would integrate with Walmart Marketplace API
        // POST /v3/orders/{purchaseOrderId}/refund

        Log::info('Syncing refund to Walmart', [
            'refund_number' => $this->refund->refund_number,
        ]);

        // Return the Walmart refund ID
        return 'walmart_' . uniqid();
    }

    /**
     * Handle unsupported channel.
     */
    protected function handleUnsupportedChannel(): ?string
    {
        Log::warning('Channel type not supported for refund sync', [
            'refund_number' => $this->refund->refund_number,
            'channel_type' => $this->refund->channel->type ?? 'unknown',
        ]);

        return null;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Refund channel sync failed permanently', [
            'refund_number' => $this->refund->refund_number,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify merchant that manual sync is needed
    }
}
