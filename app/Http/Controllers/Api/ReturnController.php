<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Models\ReturnItem;
use App\Models\Refund;
use App\Services\ReturnService;
use App\Services\RefundService;
use App\Services\ReturnShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReturnController extends Controller
{
    public function __construct(
        protected ReturnService $returnService,
        protected RefundService $refundService,
        protected ReturnShippingService $shippingService
    ) {}

    /**
     * Get all return requests for the shop.
     */
    public function getReturns(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $returns = ReturnRequest::where('shop_id', $shop->id)
            ->with(['items', 'channel', 'refund', 'shipping'])
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->has('return_type'), function ($query) use ($request) {
                $query->where('return_type', $request->input('return_type'));
            })
            ->when($request->has('customer_email'), function ($query) use ($request) {
                $query->where('customer_email', 'like', '%' . $request->input('customer_email') . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($returns);
    }

    /**
     * Get a specific return request.
     */
    public function getReturn(ReturnRequest $return): JsonResponse
    {
        $return->load([
            'items',
            'channel',
            'refund',
            'shipping',
            'approvedBy',
            'rejectedBy',
            'inspectedBy',
            'completedBy',
        ]);

        return response()->json([
            'return' => $return,
            'status_info' => $return->getStatusInfo(),
            'can_be_approved' => $return->canBeApproved(),
            'can_be_rejected' => $return->canBeRejected(),
            'is_overdue' => $return->isOverdue(),
            'requires_inspection' => $return->requiresInspection(),
        ]);
    }

    /**
     * Create a new return request.
     */
    public function createReturn(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
            'channel_order_id' => 'nullable|exists:channel_orders,id',
            'channel_id' => 'nullable|exists:channels,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'nullable|string',
            'return_reason' => 'required|in:defective,wrong_item,not_as_described,damaged_in_shipping,changed_mind,size_fit_issue,quality_issue,missing_parts,arrived_late,duplicate_order,other',
            'return_reason_details' => 'nullable|string',
            'images' => 'nullable|array',
            'return_type' => 'required|in:refund,exchange,store_credit',
            'items_subtotal' => 'required|numeric|min:0',
            'shipping_paid' => 'nullable|numeric|min:0',
            'tax_paid' => 'nullable|numeric|min:0',
            'total_paid' => 'required|numeric|min:0',
            'refund_shipping' => 'boolean',
            'requires_approval' => 'boolean',
            'customer_pays_return_shipping' => 'boolean',
            'return_window_days' => 'nullable|integer|min:1|max:365',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:variants,id',
            'items.*.product_name' => 'required|string',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.quantity_returned' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $returnRequest = $this->returnService->createReturnRequest($shop, $request->all());

            return response()->json([
                'message' => 'Return request created successfully',
                'return' => $returnRequest->load(['items', 'shipping']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create return request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a return request.
     */
    public function updateReturn(Request $request, ReturnRequest $return): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'return_reason' => 'in:defective,wrong_item,not_as_described,damaged_in_shipping,changed_mind,size_fit_issue,quality_issue,missing_parts,arrived_late,duplicate_order,other',
            'return_reason_details' => 'nullable|string',
            'images' => 'nullable|array',
            'refund_shipping' => 'boolean',
            'restocking_fee' => 'nullable|numeric|min:0',
            'internal_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $return->update($request->only([
            'return_reason',
            'return_reason_details',
            'images',
            'refund_shipping',
            'restocking_fee',
            'internal_notes',
        ]));

        return response()->json([
            'message' => 'Return updated successfully',
            'return' => $return->load(['items', 'shipping']),
        ]);
    }

    /**
     * Approve a return request.
     */
    public function approveReturn(Request $request, ReturnRequest $return): JsonResponse
    {
        if (!$return->canBeApproved()) {
            return response()->json([
                'message' => 'Return cannot be approved in current status',
                'current_status' => $return->status,
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'restocking_fee' => 'nullable|numeric|min:0',
            'refund_shipping' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->returnService->approveReturn(
                $return,
                $request->user(),
                $request->only(['restocking_fee', 'refund_shipping'])
            );

            return response()->json([
                'message' => 'Return approved successfully',
                'return' => $return->fresh(['items', 'shipping']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve return',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a return request.
     */
    public function rejectReturn(Request $request, ReturnRequest $return): JsonResponse
    {
        if (!$return->canBeRejected()) {
            return response()->json([
                'message' => 'Return cannot be rejected in current status',
                'current_status' => $return->status,
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->returnService->rejectReturn(
                $return,
                $request->user(),
                $request->input('reason')
            );

            return response()->json([
                'message' => 'Return rejected successfully',
                'return' => $return->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject return',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark return as received and start inspection.
     */
    public function markReceived(Request $request, ReturnRequest $return): JsonResponse
    {
        try {
            $this->returnService->processReceivedReturn($return, $request->user());

            return response()->json([
                'message' => 'Return marked as received and inspection started',
                'return' => $return->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process received return',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete inspection.
     */
    public function completeInspection(Request $request, ReturnRequest $return): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'result' => 'required|in:approved,partial_approval,rejected',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:return_items,id',
            'items.*.condition' => 'required|in:new_unopened,new_opened,lightly_used,heavily_used,damaged,defective,missing_parts',
            'items.*.disposition' => 'required|in:restock,restock_as_used,refurbish,dispose,return_to_vendor,quarantine,pending',
            'items.*.refundable' => 'boolean',
            'items.*.refund_amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $itemInspections = collect($request->input('items'))
                ->keyBy('item_id')
                ->map(function ($item) {
                    return [
                        'condition' => $item['condition'],
                        'disposition' => $item['disposition'],
                        'refundable' => $item['refundable'] ?? true,
                        'refund_amount' => $item['refund_amount'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ];
                })
                ->toArray();

            $this->returnService->completeInspection(
                $return,
                $itemInspections,
                $request->input('result'),
                $request->input('notes')
            );

            return response()->json([
                'message' => 'Inspection completed successfully',
                'return' => $return->fresh(['items', 'refund']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to complete inspection',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restock items.
     */
    public function restockItems(Request $request, ReturnRequest $return): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:return_items,id',
            'items.*.location_id' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $itemRestocks = collect($request->input('items'))
                ->keyBy('item_id')
                ->map(fn($item) => ['location_id' => $item['location_id']])
                ->toArray();

            $this->returnService->restockItems($return, $itemRestocks, $request->user());

            return response()->json([
                'message' => 'Items restocked successfully',
                'return' => $return->fresh(['items']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to restock items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete return.
     */
    public function completeReturn(Request $request, ReturnRequest $return): JsonResponse
    {
        try {
            $this->returnService->completeReturn($return, $request->user());

            return response()->json([
                'message' => 'Return completed successfully',
                'return' => $return->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to complete return',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pending approval returns.
     */
    public function getPendingApprovals(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $returns = ReturnRequest::where('shop_id', $shop->id)
            ->where('status', 'pending_approval')
            ->with(['items', 'channel'])
            ->orderBy('created_at', 'asc')
            ->paginate($request->input('per_page', 15));

        return response()->json($returns);
    }

    /**
     * Get refund details.
     */
    public function getRefund(Refund $refund): JsonResponse
    {
        $refund->load(['returnRequest', 'channel', 'processedBy']);

        return response()->json([
            'refund' => $refund,
            'status_info' => $refund->getStatusInfo(),
            'can_retry' => $refund->canRetry(),
            'requires_channel_sync' => $refund->requiresChannelSync(),
        ]);
    }

    /**
     * Create standalone refund.
     */
    public function createRefund(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
            'channel_order_id' => 'nullable|exists:channel_orders,id',
            'channel_id' => 'nullable|exists:channels,id',
            'refund_type' => 'required|in:full,partial,shipping_only,tax_only,custom',
            'items_refund' => 'nullable|numeric|min:0',
            'shipping_refund' => 'nullable|numeric|min:0',
            'tax_refund' => 'nullable|numeric|min:0',
            'restocking_fee' => 'nullable|numeric|min:0',
            'refund_method' => 'required|in:original_payment,store_credit,cash,check,bank_transfer,paypal,manual',
            'customer_email' => 'required|email',
            'customer_name' => 'nullable|string',
            'refund_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $refund = $this->refundService->createStandaloneRefund($shop, $request->all());

            return response()->json([
                'message' => 'Refund created successfully',
                'refund' => $refund,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create refund',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process refund.
     */
    public function processRefund(Request $request, Refund $refund): JsonResponse
    {
        try {
            $this->refundService->processRefund($refund, $request->user());

            return response()->json([
                'message' => 'Refund processed successfully',
                'refund' => $refund->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process refund',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry failed refund.
     */
    public function retryRefund(Request $request, Refund $refund): JsonResponse
    {
        if (!$refund->canRetry()) {
            return response()->json([
                'message' => 'Refund cannot be retried',
                'status' => $refund->status,
                'retry_count' => $refund->retry_count,
            ], 400);
        }

        try {
            $this->refundService->retryRefund($refund, $request->user());

            return response()->json([
                'message' => 'Refund retry initiated',
                'refund' => $refund->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retry refund',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get return statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $returnStats = $this->returnService->getStatistics($shop, $request->all());
        $refundStats = $this->refundService->getStatistics($shop, $request->all());

        return response()->json([
            'returns' => $returnStats,
            'refunds' => $refundStats,
        ]);
    }

    /**
     * Delete return request.
     */
    public function deleteReturn(ReturnRequest $return): JsonResponse
    {
        // Only allow deletion of rejected or cancelled returns
        if (!in_array($return->status, ['rejected', 'cancelled'])) {
            return response()->json([
                'message' => 'Only rejected or cancelled returns can be deleted',
                'current_status' => $return->status,
            ], 400);
        }

        $return->delete();

        return response()->json([
            'message' => 'Return deleted successfully',
        ]);
    }
}
