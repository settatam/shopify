<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashRegisterController extends Controller
{
    /**
     * Get all cash registers
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $registers = CashRegister::where('shop_id', $shop->id)
            ->with(['location', 'openedBy', 'closedBy'])
            ->get();

        return response()->json([
            'success' => true,
            'registers' => $registers,
        ]);
    }

    /**
     * Create a new cash register
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $shop = $request->user()->shop;

        $register = CashRegister::create([
            'shop_id' => $shop->id,
            'location_id' => $request->input('location_id'),
            'name' => $request->input('name'),
            'status' => 'closed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cash register created successfully',
            'register' => $register,
        ]);
    }

    /**
     * Get a single cash register
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->shop;

        $register = CashRegister::where('shop_id', $shop->id)
            ->with(['location', 'openedBy', 'closedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'register' => $register,
        ]);
    }

    /**
     * Update a cash register
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $shop = $request->user()->shop;

        $register = CashRegister::where('shop_id', $shop->id)
            ->findOrFail($id);

        $register->update($request->only(['name', 'location_id']));

        return response()->json([
            'success' => true,
            'message' => 'Cash register updated successfully',
            'register' => $register,
        ]);
    }

    /**
     * Delete a cash register
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->shop;

        $register = CashRegister::where('shop_id', $shop->id)
            ->findOrFail($id);

        if ($register->isOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an open cash register',
            ], 422);
        }

        $register->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cash register deleted successfully',
        ]);
    }

    /**
     * Open a cash register
     */
    public function open(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $shop = $request->user()->shop;
        $user = $request->user();

        $register = CashRegister::where('shop_id', $shop->id)
            ->findOrFail($id);

        if ($register->isOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Cash register is already open',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $register->open(
                $request->input('opening_balance'),
                $user,
                $request->input('notes')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cash register opened successfully',
                'register' => $register->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to open cash register: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to open cash register: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Close a cash register
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'actual_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $shop = $request->user()->shop;
        $user = $request->user();

        $register = CashRegister::where('shop_id', $shop->id)
            ->findOrFail($id);

        if ($register->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cash register is already closed',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $register->close(
                $request->input('actual_balance'),
                $user,
                $request->input('notes')
            );

            DB::commit();

            $discrepancy = $register->getDiscrepancy();

            return response()->json([
                'success' => true,
                'message' => 'Cash register closed successfully',
                'register' => $register->fresh(),
                'discrepancy' => $discrepancy,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to close cash register: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to close cash register: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Record cash in
     */
    public function cashIn(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        $shop = $request->user()->shop;
        $user = $request->user();

        $register = CashRegister::where('shop_id', $shop->id)
            ->findOrFail($id);

        if ($register->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cash register must be open',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $register->cashIn(
                $request->input('amount'),
                $user,
                $request->input('notes'),
                $request->input('reference')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cash in recorded successfully',
                'register' => $register->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to record cash in: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to record cash in: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Record cash out
     */
    public function cashOut(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        $shop = $request->user()->shop;
        $user = $request->user();

        $register = CashRegister::where('shop_id', $shop->id)
            ->findOrFail($id);

        if ($register->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cash register must be open',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $register->cashOut(
                $request->input('amount'),
                $user,
                $request->input('notes'),
                $request->input('reference')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cash out recorded successfully',
                'register' => $register->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to record cash out: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to record cash out: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get cash drawer activities
     */
    public function getActivities(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->shop;

        $register = CashRegister::where('shop_id', $shop->id)
            ->findOrFail($id);

        $activities = $register->drawerActivities()
            ->with(['user', 'posTransaction'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'activities' => $activities,
        ]);
    }

    /**
     * Get register summary
     */
    public function getSummary(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->shop;

        $register = CashRegister::where('shop_id', $shop->id)
            ->with(['location', 'openedBy'])
            ->findOrFail($id);

        $summary = [
            'register' => $register,
            'current_session' => null,
        ];

        if ($register->isOpen()) {
            $sessionStart = $register->opened_at;

            $transactions = $register->posTransactions()
                ->where('status', 'completed')
                ->where('created_at', '>=', $sessionStart)
                ->get();

            $summary['current_session'] = [
                'opened_at' => $sessionStart,
                'opening_balance' => $register->opening_balance,
                'current_balance' => $register->current_balance,
                'expected_balance' => $register->expected_balance,
                'total_sales' => $transactions->sum('total'),
                'cash_sales' => $transactions->where('payment_method', 'cash')->sum('total'),
                'check_sales' => $transactions->where('payment_method', 'check')->sum('total'),
                'transaction_count' => $transactions->count(),
                'cash_count' => $transactions->where('payment_method', 'cash')->count(),
                'check_count' => $transactions->where('payment_method', 'check')->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }
}
