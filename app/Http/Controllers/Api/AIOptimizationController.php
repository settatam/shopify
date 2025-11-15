<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Channel;
use App\Models\AIOptimization;
use App\Services\AI\AIContentOptimizer;
use App\Jobs\AI\GenerateContentOptimizationsJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AIOptimizationController extends Controller
{
    protected AIContentOptimizer $optimizer;

    public function __construct(AIContentOptimizer $optimizer)
    {
        $this->optimizer = $optimizer;
    }

    /**
     * Generate AI optimization for a product
     */
    public function generateForProduct(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'channel_id' => 'required|exists:channels,id',
            'type' => 'required|in:title,description,both',
        ]);

        try {
            $channel = Channel::findOrFail($request->input('channel_id'));
            $type = $request->input('type');

            // Check if optimization already exists
            $existingOptimization = AIOptimization::where('product_id', $product->id)
                ->where('channel_id', $channel->id)
                ->where('optimization_type', $type)
                ->latest()
                ->first();

            if ($existingOptimization && $existingOptimization->isPending()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Optimization already exists',
                    'optimization' => $existingOptimization,
                ]);
            }

            // Generate optimization
            $optimization = $this->optimizer->optimizeContent($product, $channel, $type);

            // Create AI optimization record
            $aiOptimization = AIOptimization::create([
                'product_id' => $product->id,
                'channel_id' => $channel->id,
                'channel_type' => $channel->channel_type,
                'optimization_type' => $type,
                'original_title' => $product->title,
                'original_description' => $product->description,
                'optimized_title' => $optimization['optimized_title'],
                'optimized_description' => $optimization['optimized_description'],
                'quality_score' => $optimization['quality_score'],
                'ai_reasoning' => $optimization['ai_reasoning'],
                'improvements_made' => $optimization['improvements_made'],
                'keywords_added' => $optimization['keywords_added'],
                'channel_guidelines' => $optimization['channel_guidelines'],
                'original_title_length' => mb_strlen($product->title ?? ''),
                'original_description_length' => mb_strlen($product->description ?? ''),
                'optimized_title_length' => $optimization['optimized_title_length'] ?? null,
                'optimized_description_length' => $optimization['optimized_description_length'] ?? null,
                'product_data_used' => $this->optimizer->prepareProductData($product),
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Content optimization generated successfully',
                'optimization' => $aiOptimization->load(['product', 'channel']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate content optimization', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate optimization: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get optimizations for a product
     */
    public function getOptimizations(Request $request, Product $product): JsonResponse
    {
        $query = AIOptimization::where('product_id', $product->id)
            ->with(['channel', 'reviewedBy']);

        if ($request->has('channel_id')) {
            $query->where('channel_id', $request->input('channel_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('type')) {
            $query->where('optimization_type', $request->input('type'));
        }

        $optimizations = $query->latest()->get();

        return response()->json([
            'success' => true,
            'optimizations' => $optimizations,
        ]);
    }

    /**
     * Get pending optimizations
     */
    public function getPendingOptimizations(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $query = AIOptimization::query()
            ->with(['product', 'channel'])
            ->whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })
            ->pending();

        if ($request->has('channel_type')) {
            $query->forChannelType($request->input('channel_type'));
        }

        if ($request->has('channel_id')) {
            $query->where('channel_id', $request->input('channel_id'));
        }

        if ($request->has('optimization_type')) {
            $query->optimizationType($request->input('optimization_type'));
        }

        $optimizations = $query->latest()->paginate(50);

        return response()->json([
            'success' => true,
            'optimizations' => $optimizations,
        ]);
    }

    /**
     * Approve an optimization
     */
    public function approveOptimization(Request $request, AIOptimization $optimization): JsonResponse
    {
        try {
            $optimization->approve($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Optimization approved successfully',
                'optimization' => $optimization->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve optimization: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject an optimization
     */
    public function rejectOptimization(Request $request, AIOptimization $optimization): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $optimization->reject($request->user(), $request->input('reason'));

            return response()->json([
                'success' => true,
                'message' => 'Optimization rejected successfully',
                'optimization' => $optimization->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject optimization: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Modify and approve an optimization
     */
    public function modifyOptimization(Request $request, AIOptimization $optimization): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        try {
            $optimization->modify(
                $request->user(),
                $request->input('title'),
                $request->input('description')
            );

            return response()->json([
                'success' => true,
                'message' => 'Optimization modified successfully',
                'optimization' => $optimization->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to modify optimization: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply optimization to product
     */
    public function applyToProduct(AIOptimization $optimization): JsonResponse
    {
        try {
            if (!$optimization->isApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved optimizations can be applied',
                ], 422);
            }

            $optimization->applyToProduct();

            return response()->json([
                'success' => true,
                'message' => 'Optimization applied to product successfully',
                'optimization' => $optimization->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply optimization: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch approve optimizations
     */
    public function batchApprove(Request $request): JsonResponse
    {
        $request->validate([
            'optimization_ids' => 'required|array',
            'optimization_ids.*' => 'exists:ai_optimizations,id',
        ]);

        try {
            $optimizations = AIOptimization::whereIn('id', $request->input('optimization_ids'))->get();

            foreach ($optimizations as $optimization) {
                $optimization->approve($request->user());
            }

            return response()->json([
                'success' => true,
                'message' => count($optimizations) . ' optimizations approved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch approve: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch reject optimizations
     */
    public function batchReject(Request $request): JsonResponse
    {
        $request->validate([
            'optimization_ids' => 'required|array',
            'optimization_ids.*' => 'exists:ai_optimizations,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $optimizations = AIOptimization::whereIn('id', $request->input('optimization_ids'))->get();
            $reason = $request->input('reason');

            foreach ($optimizations as $optimization) {
                $optimization->reject($request->user(), $reason);
            }

            return response()->json([
                'success' => true,
                'message' => count($optimizations) . ' optimizations rejected successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch reject: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate optimizations for multiple products (async)
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'channel_id' => 'required|exists:channels,id',
            'type' => 'required|in:title,description,both',
        ]);

        try {
            $channel = Channel::findOrFail($request->input('channel_id'));
            $productIds = $request->input('product_ids');
            $type = $request->input('type');

            // Dispatch job for batch processing
            GenerateContentOptimizationsJob::dispatch($productIds, $channel, $type);

            return response()->json([
                'success' => true,
                'message' => 'Content optimization generation started for ' . count($productIds) . ' products',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start batch generation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $stats = [
            'total' => AIOptimization::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->count(),
            'pending' => AIOptimization::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->pending()->count(),
            'approved' => AIOptimization::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->approved()->count(),
            'rejected' => AIOptimization::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->where('status', 'rejected')->count(),
            'applied' => AIOptimization::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->where('applied_to_product', true)->count(),
            'by_channel' => [],
            'by_type' => [],
        ];

        // Stats by channel
        $byChannel = AIOptimization::whereHas('product', function ($q) use ($shop) {
            $q->where('shop_id', $shop->id);
        })
            ->selectRaw('channel_type, status, COUNT(*) as count')
            ->groupBy('channel_type', 'status')
            ->get();

        foreach ($byChannel as $stat) {
            if (!isset($stats['by_channel'][$stat->channel_type])) {
                $stats['by_channel'][$stat->channel_type] = [
                    'total' => 0,
                    'pending' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                ];
            }
            $stats['by_channel'][$stat->channel_type]['total'] += $stat->count;
            $stats['by_channel'][$stat->channel_type][$stat->status] = $stat->count;
        }

        // Stats by type
        $byType = AIOptimization::whereHas('product', function ($q) use ($shop) {
            $q->where('shop_id', $shop->id);
        })
            ->selectRaw('optimization_type, COUNT(*) as count')
            ->groupBy('optimization_type')
            ->get();

        foreach ($byType as $stat) {
            $stats['by_type'][$stat->optimization_type] = $stat->count;
        }

        return response()->json([
            'success' => true,
            'statistics' => $stats,
        ]);
    }

    /**
     * Delete an optimization
     */
    public function deleteOptimization(AIOptimization $optimization): JsonResponse
    {
        try {
            $optimization->delete();

            return response()->json([
                'success' => true,
                'message' => 'Optimization deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete optimization: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview optimization without saving
     */
    public function previewOptimization(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'channel_id' => 'required|exists:channels,id',
            'type' => 'required|in:title,description,both',
        ]);

        try {
            $channel = Channel::findOrFail($request->input('channel_id'));
            $type = $request->input('type');

            $optimization = $this->optimizer->previewOptimization($product, $channel, $type);

            return response()->json([
                'success' => true,
                'preview' => $optimization,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview: ' . $e->getMessage(),
            ], 500);
        }
    }
}
