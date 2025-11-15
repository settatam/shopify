<?php

namespace App\Http\Controllers\Dejavoo;

use App\Http\Controllers\Controller;
use App\Models\PosTransaction;
use App\Services\Dejavoo\DejavooClient;
use App\Events\NewSaleEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Process a card payment through Dejavoo
     */
    public function processPayment(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'pos_transaction_id' => 'nullable|exists:pos_transactions,id',
            'reference_number' => 'nullable|string',
            'invoice_number' => 'nullable|string',
            'tip_amount' => 'nullable|numeric|min:0',
            'cashback_amount' => 'nullable|numeric|min:0',
        ]);

        $shop = $request->user()->shop;
        $user = $request->user();

        DB::beginTransaction();

        try {
            $client = new DejavooClient($shop);

            // Prepare transaction options
            $options = [];
            if ($request->has('invoice_number')) {
                $options['invoice_number'] = $request->input('invoice_number');
            }
            if ($request->has('tip_amount')) {
                $options['tip_amount'] = $request->input('tip_amount');
            }
            if ($request->has('cashback_amount')) {
                $options['cashback_amount'] = $request->input('cashback_amount');
            }

            // Process sale through Dejavoo
            $response = $client->sale(
                $request->input('amount'),
                $request->input('reference_number'),
                $options
            );

            $parsed = $client->parseResponse($response);

            // Check if approved
            if (!$parsed['approved']) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'declined' => true,
                    'message' => $parsed['message'],
                    'response_code' => $parsed['response_code'],
                ], 422);
            }

            // If this is linked to a POS transaction, update it
            if ($request->has('pos_transaction_id')) {
                $posTransaction = PosTransaction::findOrFail($request->input('pos_transaction_id'));

                // Update payment method and add Dejavoo payment data
                $posTransaction->update([
                    'payment_method' => 'card',
                    'payment_data' => $client->buildPaymentData($response),
                ]);

                // Broadcast new sale event for real-time dashboard
                NewSaleEvent::fromPosTransaction($posTransaction)->dispatch();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'approved' => true,
                'message' => 'Payment approved',
                'transaction_id' => $parsed['transaction_id'],
                'auth_code' => $parsed['auth_code'],
                'card_type' => $parsed['card_type'],
                'last_four' => $parsed['last_four'],
                'amount' => $parsed['amount'],
                'tip_amount' => $parsed['tip_amount'],
                'total_amount' => $parsed['total_amount'],
                'entry_mode' => $parsed['entry_mode'],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Dejavoo payment failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'amount' => $request->input('amount'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Void a Dejavoo transaction
     */
    public function voidPayment(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|string',
            'pos_transaction_id' => 'nullable|exists:pos_transactions,id',
        ]);

        $shop = $request->user()->shop;

        try {
            $client = new DejavooClient($shop);

            $response = $client->void($request->input('transaction_id'));
            $parsed = $client->parseResponse($response);

            if (!$parsed['approved']) {
                return response()->json([
                    'success' => false,
                    'message' => $parsed['message'],
                ], 422);
            }

            // If linked to POS transaction, void it too
            if ($request->has('pos_transaction_id')) {
                $posTransaction = PosTransaction::findOrFail($request->input('pos_transaction_id'));
                $posTransaction->void(
                    $request->user(),
                    'Card payment voided through Dejavoo'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction voided successfully',
                'data' => $parsed,
            ]);
        } catch (\Exception $e) {
            Log::error('Dejavoo void failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Void failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Refund a Dejavoo transaction
     */
    public function refundPayment(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_id' => 'nullable|string',
        ]);

        $shop = $request->user()->shop;

        try {
            $client = new DejavooClient($shop);

            $response = $client->refund(
                $request->input('amount'),
                $request->input('transaction_id')
            );

            $parsed = $client->parseResponse($response);

            if (!$parsed['approved']) {
                return response()->json([
                    'success' => false,
                    'message' => $parsed['message'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'transaction_id' => $parsed['transaction_id'],
                'auth_code' => $parsed['auth_code'],
                'amount' => $parsed['amount'],
            ]);
        } catch (\Exception $e) {
            Log::error('Dejavoo refund failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Process tip adjustment
     */
    public function tipAdjustment(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|string',
            'tip_amount' => 'required|numeric|min:0.01',
        ]);

        $shop = $request->user()->shop;

        try {
            $client = new DejavooClient($shop);

            $response = $client->tipAdjustment(
                $request->input('transaction_id'),
                $request->input('tip_amount')
            );

            $parsed = $client->parseResponse($response);

            if (!$parsed['approved']) {
                return response()->json([
                    'success' => false,
                    'message' => $parsed['message'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tip adjusted successfully',
                'tip_amount' => $parsed['tip_amount'],
                'total_amount' => $parsed['total_amount'],
            ]);
        } catch (\Exception $e) {
            Log::error('Dejavoo tip adjustment failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Tip adjustment failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get transaction status
     */
    public function getStatus(Request $request): JsonResponse
    {
        $request->validate([
            'reference_number' => 'required|string',
        ]);

        $shop = $request->user()->shop;

        try {
            $client = new DejavooClient($shop);

            $response = $client->getStatus($request->input('reference_number'));
            $parsed = $client->parseResponse($response);

            return response()->json([
                'success' => true,
                'status' => $parsed,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel current transaction in progress
     */
    public function cancel(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        try {
            $client = new DejavooClient($shop);

            $response = $client->cancel();
            $parsed = $client->parseResponse($response);

            return response()->json([
                'success' => true,
                'message' => 'Transaction cancelled',
                'data' => $parsed,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cancel failed: ' . $e->getMessage(),
            ], 422);
        }
    }
}
