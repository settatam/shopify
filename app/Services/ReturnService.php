<?php

namespace App\Services;

use App\Models\ReturnRequest;
use App\Models\ReturnItem;
use App\Models\ChannelOrder;
use App\Models\Product;
use App\Models\Variant;
use App\Models\Location;
use App\Models\User;
use App\Models\Shop;
use App\Jobs\GenerateReturnLabelJob;
use App\Jobs\ProcessRefundJob;
use App\Jobs\NotifyReturnStatusJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnService
{
    public function __construct(
        protected RefundService $refundService,
        protected ReturnShippingService $shippingService
    ) {}

    /**
     * Create a new return request.
     */
    public function createReturnRequest(Shop $shop, array $data): ReturnRequest
    {
        DB::beginTransaction();

        try {
            $returnRequest = ReturnRequest::create([
                'shop_id' => $shop->id,
                'rma_number' => ReturnRequest::generateRmaNumber(),
                'order_number' => $data['order_number'],
                'channel_order_id' => $data['channel_order_id'] ?? null,
                'channel_id' => $data['channel_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'return_reason' => $data['return_reason'],
                'return_reason_details' => $data['return_reason_details'] ?? null,
                'images' => $data['images'] ?? null,
                'status' => $data['requires_approval'] ?? true ? 'pending_approval' : 'approved',
                'return_type' => $data['return_type'] ?? 'refund',
                'items_subtotal' => $data['items_subtotal'],
                'shipping_paid' => $data['shipping_paid'] ?? 0,
                'tax_paid' => $data['tax_paid'] ?? 0,
                'total_paid' => $data['total_paid'],
                'refund_shipping' => $data['refund_shipping'] ?? false,
                'requires_approval' => $data['requires_approval'] ?? true,
                'customer_pays_return_shipping' => $data['customer_pays_return_shipping'] ?? true,
                'return_by_date' => isset($data['return_window_days'])
                    ? now()->addDays($data['return_window_days'])
                    : now()->addDays(30),
                'return_address' => $data['return_address'] ?? $this->getDefaultReturnAddress($shop),
                'internal_notes' => $data['internal_notes'] ?? null,
            ]);

            // Create return items
            foreach ($data['items'] as $itemData) {
                $this->createReturnItem($returnRequest, $itemData);
            }

            // Calculate refund amount
            $refundAmount = $returnRequest->calculateRefundAmount();
            $returnRequest->update(['refund_amount' => $refundAmount]);

            DB::commit();

            // Send notification to customer
            NotifyReturnStatusJob::dispatch($returnRequest, 'created')
                ->delay(now()->addMinutes(1));

            Log::info('Return request created', [
                'rma_number' => $returnRequest->rma_number,
                'shop_id' => $shop->id,
            ]);

            return $returnRequest;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create return request', [
                'error' => $e->getMessage(),
                'shop_id' => $shop->id,
            ]);
            throw $e;
        }
    }

    /**
     * Create a return item.
     */
    protected function createReturnItem(ReturnRequest $returnRequest, array $data): ReturnItem
    {
        return ReturnItem::create([
            'return_request_id' => $returnRequest->id,
            'product_id' => $data['product_id'] ?? null,
            'variant_id' => $data['variant_id'] ?? null,
            'channel_order_item_id' => $data['channel_order_item_id'] ?? null,
            'sku' => $data['sku'] ?? null,
            'product_name' => $data['product_name'],
            'variant_name' => $data['variant_name'] ?? null,
            'quantity_ordered' => $data['quantity_ordered'],
            'quantity_returned' => $data['quantity_returned'],
            'unit_price' => $data['unit_price'],
            'total_price' => $data['total_price'],
            'tax_amount' => $data['tax_amount'] ?? 0,
            'return_reason' => $data['return_reason'] ?? $returnRequest->return_reason,
            'return_reason_details' => $data['return_reason_details'] ?? null,
            'refundable' => $data['refundable'] ?? true,
            'restocking_fee' => $data['restocking_fee'] ?? 0,
            'images' => $data['images'] ?? null,
        ]);
    }

    /**
     * Approve a return request.
     */
    public function approveReturn(ReturnRequest $returnRequest, User $user, ?array $options = null): void
    {
        if (!$returnRequest->canBeApproved()) {
            throw new \Exception('Return request cannot be approved in current status');
        }

        DB::beginTransaction();

        try {
            $returnRequest->approve($user);

            // Apply any options
            if ($options) {
                if (isset($options['restocking_fee'])) {
                    $returnRequest->update(['restocking_fee' => $options['restocking_fee']]);
                }
                if (isset($options['refund_shipping'])) {
                    $returnRequest->update(['refund_shipping' => $options['refund_shipping']]);
                }
            }

            // Recalculate refund amount
            $refundAmount = $returnRequest->calculateRefundAmount();
            $returnRequest->update(['refund_amount' => $refundAmount]);

            DB::commit();

            // Generate return shipping label
            if ($returnRequest->return_type !== 'exchange') {
                GenerateReturnLabelJob::dispatch($returnRequest)
                    ->delay(now()->addMinutes(2));
            }

            // Notify customer
            NotifyReturnStatusJob::dispatch($returnRequest, 'approved')
                ->delay(now()->addMinutes(1));

            Log::info('Return request approved', [
                'rma_number' => $returnRequest->rma_number,
                'approved_by' => $user->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve return request', [
                'rma_number' => $returnRequest->rma_number,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reject a return request.
     */
    public function rejectReturn(ReturnRequest $returnRequest, User $user, string $reason): void
    {
        if (!$returnRequest->canBeRejected()) {
            throw new \Exception('Return request cannot be rejected in current status');
        }

        $returnRequest->reject($user, $reason);

        // Notify customer
        NotifyReturnStatusJob::dispatch($returnRequest, 'rejected')
            ->delay(now()->addMinutes(1));

        Log::info('Return request rejected', [
            'rma_number' => $returnRequest->rma_number,
            'rejected_by' => $user->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Process received return.
     */
    public function processReceivedReturn(ReturnRequest $returnRequest, User $user): void
    {
        $returnRequest->markReceived();
        $returnRequest->startInspection($user);

        Log::info('Return received and inspection started', [
            'rma_number' => $returnRequest->rma_number,
            'inspected_by' => $user->id,
        ]);
    }

    /**
     * Complete inspection of return items.
     */
    public function completeInspection(
        ReturnRequest $returnRequest,
        array $itemInspections,
        string $result,
        ?string $notes = null
    ): void {
        DB::beginTransaction();

        try {
            // Update each item's inspection results
            foreach ($itemInspections as $itemId => $inspection) {
                $item = ReturnItem::find($itemId);
                if ($item && $item->return_request_id === $returnRequest->id) {
                    $item->update([
                        'condition_received' => $inspection['condition'],
                        'disposition' => $inspection['disposition'],
                        'inspection_notes' => $inspection['notes'] ?? null,
                        'refundable' => $inspection['refundable'] ?? true,
                        'refund_amount' => $inspection['refund_amount'] ?? $item->calculateRefundAmount(),
                    ]);
                }
            }

            // Complete the inspection
            $returnRequest->completeInspection($result, $notes);

            // Recalculate total refund
            $totalRefund = $returnRequest->items->sum('refund_amount');
            $returnRequest->update(['refund_amount' => $totalRefund]);

            DB::commit();

            // Process refund if approved
            if ($result === 'approved') {
                ProcessRefundJob::dispatch($returnRequest)
                    ->delay(now()->addMinutes(5));
            }

            // Notify customer
            NotifyReturnStatusJob::dispatch($returnRequest, 'inspection_completed')
                ->delay(now()->addMinutes(1));

            Log::info('Return inspection completed', [
                'rma_number' => $returnRequest->rma_number,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete inspection', [
                'rma_number' => $returnRequest->rma_number,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Restock returned items.
     */
    public function restockItems(ReturnRequest $returnRequest, array $itemRestocks, User $user): void
    {
        DB::beginTransaction();

        try {
            foreach ($itemRestocks as $itemId => $restockData) {
                $item = ReturnItem::find($itemId);
                if (!$item || $item->return_request_id !== $returnRequest->id) {
                    continue;
                }

                $location = Location::find($restockData['location_id']);
                if (!$location) {
                    continue;
                }

                // Mark item as restocked
                $item->markRestocked($user, $location);

                // Update inventory (simplified - you may have an InventoryService)
                if ($item->variant_id) {
                    // Add inventory back to location
                    // This would integrate with your InventoryService
                    Log::info('Restocking item', [
                        'variant_id' => $item->variant_id,
                        'quantity' => $item->quantity_returned,
                        'location_id' => $location->id,
                    ]);
                }
            }

            DB::commit();

            Log::info('Items restocked', [
                'rma_number' => $returnRequest->rma_number,
                'restocked_by' => $user->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restock items', [
                'rma_number' => $returnRequest->rma_number,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Complete the return process.
     */
    public function completeReturn(ReturnRequest $returnRequest, User $user): void
    {
        $returnRequest->markCompleted($user);

        // Notify customer
        NotifyReturnStatusJob::dispatch($returnRequest, 'completed')
            ->delay(now()->addMinutes(1));

        Log::info('Return completed', [
            'rma_number' => $returnRequest->rma_number,
            'completed_by' => $user->id,
        ]);
    }

    /**
     * Get default return address for shop.
     */
    protected function getDefaultReturnAddress(Shop $shop): string
    {
        // This would come from shop settings
        // For now, return a placeholder
        return json_encode([
            'name' => $shop->name,
            'address1' => 'Returns Department',
            'address2' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => 'US',
        ]);
    }

    /**
     * Get return statistics.
     */
    public function getStatistics(Shop $shop, ?array $filters = null): array
    {
        $query = ReturnRequest::where('shop_id', $shop->id);

        if ($filters) {
            if (isset($filters['start_date'])) {
                $query->where('created_at', '>=', $filters['start_date']);
            }
            if (isset($filters['end_date'])) {
                $query->where('created_at', '<=', $filters['end_date']);
            }
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
        }

        $totalReturns = $query->count();
        $pendingApproval = ReturnRequest::where('shop_id', $shop->id)
            ->where('status', 'pending_approval')
            ->count();

        $completed = ReturnRequest::where('shop_id', $shop->id)
            ->where('status', 'completed')
            ->count();

        $totalRefunded = ReturnRequest::where('shop_id', $shop->id)
            ->where('status', 'completed')
            ->sum('refund_amount');

        // Return reasons breakdown
        $reasonsBreakdown = ReturnRequest::where('shop_id', $shop->id)
            ->selectRaw('return_reason, COUNT(*) as count')
            ->groupBy('return_reason')
            ->get()
            ->pluck('count', 'return_reason')
            ->toArray();

        return [
            'total_returns' => $totalReturns,
            'pending_approval' => $pendingApproval,
            'completed' => $completed,
            'total_refunded' => $totalRefunded,
            'return_rate' => 0, // Would need order data to calculate
            'reasons_breakdown' => $reasonsBreakdown,
        ];
    }
}
