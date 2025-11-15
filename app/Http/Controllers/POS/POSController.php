<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\PosTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class POSController extends Controller
{
    /**
     * Get available products for POS
     */
    public function getProducts(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $query = Product::where('shop_id', $shop->id)
            ->with(['variants.stockItems']);

        // Search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        // Exclude out of stock (optional)
        if ($request->boolean('exclude_out_of_stock')) {
            $query->whereHas('variants.stockItems', function ($q) {
                $q->where('quantity', '>', 0);
            });
        }

        $products = $query->limit(50)->get();

        // Transform for POS interface
        $productsData = $products->map(function ($product) use ($request) {
            return [
                'id' => $product->id,
                'title' => $product->title,
                'image' => $product->image_url ?? null,
                'variants' => $product->variants->map(function ($variant) use ($request) {
                    $locationId = $request->input('location_id');
                    $stockItem = $locationId
                        ? $variant->stockItems->where('location_id', $locationId)->first()
                        : $variant->stockItems->first();

                    return [
                        'id' => $variant->id,
                        'title' => $variant->title ?? 'Default',
                        'sku' => $variant->sku,
                        'barcode' => $variant->barcode,
                        'price' => (float) $variant->price,
                        'compare_at_price' => $variant->compare_at_price ? (float) $variant->compare_at_price : null,
                        'available_quantity' => $stockItem ? $stockItem->quantity : 0,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'products' => $productsData,
        ]);
    }

    /**
     * Search for a product by SKU or barcode
     */
    public function searchProduct(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $shop = $request->user()->shop;
        $code = $request->input('code');
        $locationId = $request->input('location_id');

        $variant = ProductVariant::whereHas('product', function ($q) use ($shop) {
            $q->where('shop_id', $shop->id);
        })
            ->where(function ($q) use ($code) {
                $q->where('sku', $code)
                    ->orWhere('barcode', $code);
            })
            ->with(['product', 'stockItems'])
            ->first();

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $stockItem = $locationId
            ? $variant->stockItems->where('location_id', $locationId)->first()
            : $variant->stockItems->first();

        return response()->json([
            'success' => true,
            'product' => [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'title' => $variant->product->title,
                'variant_title' => $variant->title ?? 'Default',
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'price' => (float) $variant->price,
                'compare_at_price' => $variant->compare_at_price ? (float) $variant->compare_at_price : null,
                'available_quantity' => $stockItem ? $stockItem->quantity : 0,
                'image' => $variant->product->image_url ?? null,
            ],
        ]);
    }

    /**
     * Create a POS transaction
     */
    public function createTransaction(Request $request): JsonResponse
    {
        $request->validate([
            'cash_register_id' => 'required|exists:cash_registers,id',
            'location_id' => 'required|exists:locations,id',
            'payment_method' => 'required|in:cash,check',
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'amount_tendered' => 'nullable|numeric|min:0',
            'check_number' => 'required_if:payment_method,check|nullable|string',
            'customer_name' => 'nullable|string',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $shop = $request->user()->shop;
        $user = $request->user();

        // Verify cash register is open
        $cashRegister = CashRegister::findOrFail($request->input('cash_register_id'));
        if (!$cashRegister->isOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Cash register is not open',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Calculate totals
            $subtotal = 0;
            $lineItems = [];

            foreach ($request->input('items') as $item) {
                $variant = ProductVariant::with('product')->findOrFail($item['variant_id']);

                // Check inventory availability
                $stockItem = $variant->stockItems()
                    ->where('location_id', $request->input('location_id'))
                    ->first();

                if (!$stockItem || $stockItem->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient inventory for {$variant->product->title}");
                }

                $lineTotal = $item['quantity'] * $item['price'];
                $subtotal += $lineTotal;

                $lineItems[] = [
                    'product_variant_id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'sku' => $variant->sku,
                    'title' => $variant->product->title,
                    'variant_title' => $variant->title ?? 'Default',
                    'quantity' => $item['quantity'],
                    'price' => (float) $item['price'],
                    'line_total' => $lineTotal,
                ];

                // Decrease inventory
                $stockItem->decrement('quantity', $item['quantity']);
            }

            $tax = $request->input('tax', 0);
            $discount = $request->input('discount', 0);
            $total = $subtotal + $tax - $discount;

            // Calculate change for cash payments
            $amountTendered = $request->input('amount_tendered');
            $changeGiven = null;

            if ($request->input('payment_method') === 'cash' && $amountTendered) {
                if ($amountTendered < $total) {
                    throw new \Exception('Amount tendered is less than total');
                }
                $changeGiven = $amountTendered - $total;
            }

            // Create POS transaction
            $transaction = PosTransaction::create([
                'shop_id' => $shop->id,
                'cash_register_id' => $cashRegister->id,
                'location_id' => $request->input('location_id'),
                'payment_method' => $request->input('payment_method'),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'amount_tendered' => $amountTendered,
                'change_given' => $changeGiven,
                'check_number' => $request->input('check_number'),
                'customer_name' => $request->input('customer_name'),
                'customer_email' => $request->input('customer_email'),
                'customer_phone' => $request->input('customer_phone'),
                'notes' => $request->input('notes'),
                'processed_by_user_id' => $user->id,
                'line_items' => $lineItems,
                'completed_at' => now(),
                'status' => 'completed',
            ]);

            // Record sale in cash register
            $cashRegister->recordSale($transaction);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction completed successfully',
                'transaction' => [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'total' => $transaction->total,
                    'change_given' => $transaction->change_given,
                    'receipt_data' => $transaction->getReceiptData(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('POS transaction failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'shop_id' => $shop->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Transaction failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get transaction history
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $query = PosTransaction::where('shop_id', $shop->id)
            ->with(['processedBy', 'cashRegister', 'location']);

        // Filter by cash register
        if ($request->has('cash_register_id')) {
            $query->where('cash_register_id', $request->input('cash_register_id'));
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Get a single transaction
     */
    public function getTransaction(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->shop;

        $transaction = PosTransaction::where('shop_id', $shop->id)
            ->with(['processedBy', 'cashRegister', 'location', 'voidedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'transaction' => $transaction,
            'receipt_data' => $transaction->getReceiptData(),
        ]);
    }

    /**
     * Void a transaction
     */
    public function voidTransaction(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $shop = $request->user()->shop;
        $user = $request->user();

        $transaction = PosTransaction::where('shop_id', $shop->id)
            ->findOrFail($id);

        if ($transaction->isVoided()) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction is already voided',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $transaction->void($user, $request->input('reason'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction voided successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to void POS transaction: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to void transaction: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get sales summary
     */
    public function getSalesSummary(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $query = PosTransaction::where('shop_id', $shop->id)
            ->where('status', 'completed');

        // Filter by cash register
        if ($request->has('cash_register_id')) {
            $query->where('cash_register_id', $request->input('cash_register_id'));
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $summary = [
            'total_sales' => $query->sum('total'),
            'total_transactions' => $query->count(),
            'cash_sales' => $query->clone()->where('payment_method', 'cash')->sum('total'),
            'check_sales' => $query->clone()->where('payment_method', 'check')->sum('total'),
            'total_tax' => $query->sum('tax'),
            'total_discount' => $query->sum('discount'),
            'average_sale' => $query->avg('total'),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }
}
