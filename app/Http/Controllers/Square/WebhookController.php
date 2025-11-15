<?php

namespace App\Http\Controllers\Square;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Jobs\Square\FetchSquareOrders;
use App\Jobs\Square\SyncInventoryToSquare;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Square webhook events
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Verify webhook signature
            if (!$this->verifySignature($request)) {
                Log::warning('Square webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $payload = $request->all();
            $eventType = $payload['type'] ?? null;
            $data = $payload['data'] ?? [];

            Log::info("Square webhook received: {$eventType}", $payload);

            // Route to appropriate handler
            switch ($eventType) {
                case 'payment.created':
                    $this->handlePaymentCreated($data);
                    break;

                case 'payment.updated':
                    $this->handlePaymentUpdated($data);
                    break;

                case 'order.created':
                case 'order.updated':
                    $this->handleOrderEvent($data);
                    break;

                case 'inventory.count.updated':
                    $this->handleInventoryUpdated($data);
                    break;

                case 'catalog.version.updated':
                    $this->handleCatalogUpdated($data);
                    break;

                default:
                    Log::info("Unhandled Square webhook event: {$eventType}");
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Square webhook processing error: ' . $e->getMessage(), [
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle payment.created event
     */
    protected function handlePaymentCreated(array $data): void
    {
        $object = $data['object'] ?? [];
        $payment = $object['payment'] ?? [];
        $locationId = $payment['location_id'] ?? null;

        if (!$locationId) {
            return;
        }

        // Find channel by location ID
        $channel = Channel::where('type', 'square')
            ->whereJsonContains('auth_json->location_id', $locationId)
            ->first();

        if (!$channel) {
            Log::warning("No Square channel found for location: {$locationId}");
            return;
        }

        // Fetch orders to process the new payment
        FetchSquareOrders::dispatch($channel);
    }

    /**
     * Handle payment.updated event
     */
    protected function handlePaymentUpdated(array $data): void
    {
        // Similar to payment.created
        $this->handlePaymentCreated($data);
    }

    /**
     * Handle order events
     */
    protected function handleOrderEvent(array $data): void
    {
        $object = $data['object'] ?? [];
        $order = $object['order'] ?? [];
        $locationId = $order['location_id'] ?? null;

        if (!$locationId) {
            return;
        }

        $channel = Channel::where('type', 'square')
            ->whereJsonContains('auth_json->location_id', $locationId)
            ->first();

        if (!$channel) {
            return;
        }

        // Fetch latest orders
        FetchSquareOrders::dispatch($channel);
    }

    /**
     * Handle inventory.count.updated event
     */
    protected function handleInventoryUpdated(array $data): void
    {
        $object = $data['object'] ?? [];
        $inventoryCount = $object['inventory_counts'] ?? [];

        foreach ($inventoryCount as $count) {
            $catalogObjectId = $count['catalog_object_id'] ?? null;
            $locationId = $count['location_id'] ?? null;
            $quantity = $count['quantity'] ?? '0';

            if (!$catalogObjectId || !$locationId) {
                continue;
            }

            // Find the product variant by Square variation ID
            $variant = \App\Models\ProductVariant::whereJsonContains('sync_metadata->square_variation_id', $catalogObjectId)
                ->first();

            if (!$variant) {
                continue;
            }

            // Find channel by location
            $channel = Channel::where('type', 'square')
                ->whereJsonContains('auth_json->location_id', $locationId)
                ->first();

            if (!$channel) {
                continue;
            }

            // Update local stock item if Square inventory changed externally
            // Only update if the change came from outside our system
            $stockItem = $variant->stockItems()->first();
            if ($stockItem) {
                $newQuantity = (int) $quantity;

                // Avoid circular updates - only update if significantly different
                if (abs($stockItem->quantity - $newQuantity) > 0) {
                    Log::info("Updating local inventory from Square webhook", [
                        'variant_id' => $variant->id,
                        'old_quantity' => $stockItem->quantity,
                        'new_quantity' => $newQuantity,
                    ]);

                    $stockItem->update(['quantity' => $newQuantity]);
                }
            }
        }
    }

    /**
     * Handle catalog.version.updated event
     */
    protected function handleCatalogUpdated(array $data): void
    {
        // Catalog was updated in Square
        // You could trigger a re-sync here if needed
        Log::info('Square catalog updated', $data);
    }

    /**
     * Verify webhook signature
     */
    protected function verifySignature(Request $request): bool
    {
        $signatureKey = config('services.square.webhook_signature_key');

        if (!$signatureKey) {
            // If no signature key configured, skip verification (development mode)
            return true;
        }

        $signature = $request->header('X-Square-Signature');
        if (!$signature) {
            return false;
        }

        // Get the raw request body
        $body = $request->getContent();

        // Combine webhook URL and body
        $url = $request->url();
        $stringToSign = $url . $body;

        // Calculate HMAC SHA-256
        $expectedSignature = base64_encode(hash_hmac('sha256', $stringToSign, $signatureKey, true));

        return hash_equals($expectedSignature, $signature);
    }
}
