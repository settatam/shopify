<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Shop;
use App\Jobs\SyncRefundToChannelJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundService
{
    /**
     * Create a refund from a return request.
     */
    public function createRefundFromReturn(ReturnRequest $returnRequest): Refund
    {
        DB::beginTransaction();

        try {
            $itemsRefund = $returnRequest->items->sum('refund_amount');
            $shippingRefund = $returnRequest->refund_shipping ? $returnRequest->shipping_paid : 0;
            $taxRefund = $returnRequest->refund_shipping ? $returnRequest->tax_paid : 0;
            $restockingFee = $returnRequest->restocking_fee;

            $totalRefund = $itemsRefund + $shippingRefund + $taxRefund - $restockingFee;

            // Deduct return shipping cost if customer doesn't pay
            if (!$returnRequest->customer_pays_return_shipping && $returnRequest->return_shipping_cost) {
                $totalRefund -= $returnRequest->return_shipping_cost;
            }

            $totalRefund = max(0, $totalRefund);

            $refund = Refund::create([
                'shop_id' => $returnRequest->shop_id,
                'return_request_id' => $returnRequest->id,
                'channel_order_id' => $returnRequest->channel_order_id,
                'refund_number' => Refund::generateRefundNumber(),
                'order_number' => $returnRequest->order_number,
                'refund_type' => $this->determineRefundType($itemsRefund, $shippingRefund, $totalRefund),
                'items_refund' => $itemsRefund,
                'shipping_refund' => $shippingRefund,
                'tax_refund' => $taxRefund,
                'restocking_fee' => $restockingFee,
                'total_refund' => $totalRefund,
                'refund_method' => $this->getRefundMethod($returnRequest),
                'customer_email' => $returnRequest->customer_email,
                'customer_name' => $returnRequest->customer_name,
                'channel_id' => $returnRequest->channel_id,
                'status' => 'pending',
                'refund_reason' => $returnRequest->return_reason,
            ]);

            DB::commit();

            Log::info('Refund created from return', [
                'refund_number' => $refund->refund_number,
                'rma_number' => $returnRequest->rma_number,
                'total_refund' => $totalRefund,
            ]);

            return $refund;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create refund from return', [
                'rma_number' => $returnRequest->rma_number,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a standalone refund (not from return).
     */
    public function createStandaloneRefund(Shop $shop, array $data): Refund
    {
        DB::beginTransaction();

        try {
            $totalRefund = ($data['items_refund'] ?? 0)
                + ($data['shipping_refund'] ?? 0)
                + ($data['tax_refund'] ?? 0)
                - ($data['restocking_fee'] ?? 0);

            $refund = Refund::create([
                'shop_id' => $shop->id,
                'channel_order_id' => $data['channel_order_id'] ?? null,
                'refund_number' => Refund::generateRefundNumber(),
                'order_number' => $data['order_number'],
                'refund_type' => $data['refund_type'],
                'items_refund' => $data['items_refund'] ?? 0,
                'shipping_refund' => $data['shipping_refund'] ?? 0,
                'tax_refund' => $data['tax_refund'] ?? 0,
                'restocking_fee' => $data['restocking_fee'] ?? 0,
                'total_refund' => $totalRefund,
                'refund_method' => $data['refund_method'],
                'original_payment_method' => $data['original_payment_method'] ?? null,
                'original_transaction_id' => $data['original_transaction_id'] ?? null,
                'payment_gateway' => $data['payment_gateway'] ?? null,
                'customer_email' => $data['customer_email'],
                'customer_name' => $data['customer_name'] ?? null,
                'channel_id' => $data['channel_id'] ?? null,
                'status' => 'pending',
                'refund_reason' => $data['refund_reason'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
            ]);

            DB::commit();

            Log::info('Standalone refund created', [
                'refund_number' => $refund->refund_number,
                'total_refund' => $totalRefund,
            ]);

            return $refund;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create standalone refund', [
                'order_number' => $data['order_number'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process a refund.
     */
    public function processRefund(Refund $refund, User $user): bool
    {
        $refund->markProcessing();

        try {
            $success = match($refund->refund_method) {
                'original_payment' => $this->processPaymentGatewayRefund($refund),
                'store_credit' => $this->processStoreCreditRefund($refund),
                'cash' => $this->processCashRefund($refund),
                'paypal' => $this->processPayPalRefund($refund),
                default => $this->processManualRefund($refund),
            };

            if ($success) {
                $refund->markCompleted($user);

                // Sync to channel if applicable
                if ($refund->channel_id) {
                    SyncRefundToChannelJob::dispatch($refund)
                        ->delay(now()->addMinutes(2));
                }

                // Mark customer as notified
                $refund->markCustomerNotified();

                Log::info('Refund processed successfully', [
                    'refund_number' => $refund->refund_number,
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $refund->markFailed($e->getMessage());

            Log::error('Refund processing failed', [
                'refund_number' => $refund->refund_number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process refund through payment gateway.
     */
    protected function processPaymentGatewayRefund(Refund $refund): bool
    {
        // This would integrate with actual payment gateways
        // For now, simulate success

        $gateway = $refund->payment_gateway;

        switch ($gateway) {
            case 'stripe':
                return $this->processStripeRefund($refund);
            case 'square':
                return $this->processSquareRefund($refund);
            case 'paypal':
                return $this->processPayPalRefund($refund);
            default:
                Log::warning('Unknown payment gateway', ['gateway' => $gateway]);
                return false;
        }
    }

    /**
     * Process Stripe refund.
     */
    protected function processStripeRefund(Refund $refund): bool
    {
        // Integrate with Stripe API
        // \Stripe\Refund::create([
        //     'charge' => $refund->original_transaction_id,
        //     'amount' => $refund->total_refund * 100, // Convert to cents
        // ]);

        Log::info('Processing Stripe refund', [
            'refund_number' => $refund->refund_number,
            'transaction_id' => $refund->original_transaction_id,
        ]);

        // Simulate success for now
        $refund->update([
            'gateway_refund_id' => 'stripe_' . uniqid(),
            'gateway_response' => json_encode(['status' => 'succeeded']),
        ]);

        return true;
    }

    /**
     * Process Square refund.
     */
    protected function processSquareRefund(Refund $refund): bool
    {
        // Integrate with Square API
        Log::info('Processing Square refund', [
            'refund_number' => $refund->refund_number,
        ]);

        // Simulate success for now
        $refund->update([
            'gateway_refund_id' => 'square_' . uniqid(),
            'gateway_response' => json_encode(['status' => 'COMPLETED']),
        ]);

        return true;
    }

    /**
     * Process PayPal refund.
     */
    protected function processPayPalRefund(Refund $refund): bool
    {
        // Integrate with PayPal API
        Log::info('Processing PayPal refund', [
            'refund_number' => $refund->refund_number,
        ]);

        // Simulate success for now
        $refund->update([
            'gateway_refund_id' => 'paypal_' . uniqid(),
            'gateway_response' => json_encode(['status' => 'COMPLETED']),
        ]);

        return true;
    }

    /**
     * Process store credit refund.
     */
    protected function processStoreCreditRefund(Refund $refund): bool
    {
        // Generate store credit code
        $creditCode = 'SC-' . strtoupper(substr(uniqid(), -10));

        $refund->update([
            'store_credit_code' => $creditCode,
            'store_credit_expires_at' => now()->addYear(),
        ]);

        Log::info('Store credit issued', [
            'refund_number' => $refund->refund_number,
            'credit_code' => $creditCode,
            'amount' => $refund->total_refund,
        ]);

        return true;
    }

    /**
     * Process cash refund.
     */
    protected function processCashRefund(Refund $refund): bool
    {
        // For POS cash refunds - would integrate with cash register
        Log::info('Cash refund processed', [
            'refund_number' => $refund->refund_number,
            'amount' => $refund->total_refund,
        ]);

        return true;
    }

    /**
     * Process manual refund.
     */
    protected function processManualRefund(Refund $refund): bool
    {
        // Manual refunds require staff to handle offline
        Log::info('Manual refund marked for processing', [
            'refund_number' => $refund->refund_number,
            'amount' => $refund->total_refund,
        ]);

        return true;
    }

    /**
     * Retry a failed refund.
     */
    public function retryRefund(Refund $refund, User $user): bool
    {
        if (!$refund->canRetry()) {
            throw new \Exception('Refund cannot be retried');
        }

        $refund->incrementRetry();
        $refund->update(['status' => 'pending']);

        return $this->processRefund($refund, $user);
    }

    /**
     * Cancel a refund.
     */
    public function cancelRefund(Refund $refund): void
    {
        if (!in_array($refund->status, ['pending', 'on_hold'])) {
            throw new \Exception('Only pending or on-hold refunds can be cancelled');
        }

        $refund->update(['status' => 'cancelled']);

        Log::info('Refund cancelled', [
            'refund_number' => $refund->refund_number,
        ]);
    }

    /**
     * Determine refund type based on amounts.
     */
    protected function determineRefundType(float $items, float $shipping, float $total): string
    {
        if ($items > 0 && $shipping > 0) {
            return 'full';
        }

        if ($items > 0) {
            return 'partial';
        }

        if ($shipping > 0) {
            return 'shipping_only';
        }

        return 'custom';
    }

    /**
     * Get refund method from return request.
     */
    protected function getRefundMethod(ReturnRequest $returnRequest): string
    {
        return match($returnRequest->return_type) {
            'store_credit' => 'store_credit',
            default => 'original_payment',
        };
    }

    /**
     * Get refund statistics.
     */
    public function getStatistics(Shop $shop, ?array $filters = null): array
    {
        $query = Refund::where('shop_id', $shop->id);

        if ($filters) {
            if (isset($filters['start_date'])) {
                $query->where('created_at', '>=', $filters['start_date']);
            }
            if (isset($filters['end_date'])) {
                $query->where('created_at', '<=', $filters['end_date']);
            }
        }

        $totalRefunds = $query->count();
        $totalAmount = $query->where('status', 'completed')->sum('total_refund');
        $pending = Refund::where('shop_id', $shop->id)->where('status', 'pending')->count();
        $failed = Refund::where('shop_id', $shop->id)->where('status', 'failed')->count();

        return [
            'total_refunds' => $totalRefunds,
            'total_amount' => $totalAmount,
            'pending' => $pending,
            'failed' => $failed,
        ];
    }
}
