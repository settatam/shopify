<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Channel;
use App\Models\AICategoryMapping;
use App\Services\AI\AICategoryMapper;
use App\Jobs\AI\GenerateCategoryMappingsJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AICategoryMappingController extends Controller
{
    protected AICategoryMapper $categoryMapper;

    public function __construct(AICategoryMapper $categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Generate AI category mapping for a product
     */
    public function generateForProduct(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'channel_id' => 'required|exists:channels,id',
            'categories' => 'required|array',
        ]);

        try {
            $channel = Channel::findOrFail($request->input('channel_id'));
            $categories = $request->input('categories');

            // Check if mapping already exists
            $existingMapping = AICategoryMapping::where('product_id', $product->id)
                ->where('channel_id', $channel->id)
                ->latest()
                ->first();

            if ($existingMapping && $existingMapping->isPending()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mapping already exists',
                    'mapping' => $existingMapping,
                ]);
            }

            // Generate mapping
            $mapping = $this->categoryMapper->mapProductCategory($product, $channel, $categories);

            // Create AI category mapping record
            $aiMapping = AICategoryMapping::create([
                'product_id' => $product->id,
                'channel_id' => $channel->id,
                'channel_type' => $channel->channel_type,
                'suggested_category_id' => $mapping['suggested_category_id'],
                'suggested_category_name' => $mapping['suggested_category_name'],
                'suggested_category_path' => $mapping['suggested_category_path'],
                'confidence_score' => $mapping['confidence_score'],
                'alternative_suggestions' => $mapping['alternative_suggestions'],
                'ai_reasoning' => $mapping['ai_reasoning'],
                'matched_keywords' => $mapping['matched_keywords'],
                'product_data_used' => $this->categoryMapper->prepareProductData($product),
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category mapping generated successfully',
                'mapping' => $aiMapping->load(['product', 'channel']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate category mapping', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate category mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get category mappings for a product
     */
    public function getMappings(Request $request, Product $product): JsonResponse
    {
        $query = AICategoryMapping::where('product_id', $product->id)
            ->with(['channel', 'reviewedBy']);

        if ($request->has('channel_id')) {
            $query->where('channel_id', $request->input('channel_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $mappings = $query->latest()->get();

        return response()->json([
            'success' => true,
            'mappings' => $mappings,
        ]);
    }

    /**
     * Get pending category mappings
     */
    public function getPendingMappings(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $query = AICategoryMapping::query()
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

        $mappings = $query->latest()->paginate(50);

        return response()->json([
            'success' => true,
            'mappings' => $mappings,
        ]);
    }

    /**
     * Approve a category mapping
     */
    public function approvMapping(Request $request, AICategoryMapping $mapping): JsonResponse
    {
        try {
            $mapping->approve($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Category mapping approved successfully',
                'mapping' => $mapping->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a category mapping
     */
    public function rejectMapping(Request $request, AICategoryMapping $mapping): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $mapping->reject($request->user(), $request->input('reason'));

            return response()->json([
                'success' => true,
                'message' => 'Category mapping rejected successfully',
                'mapping' => $mapping->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Modify and approve a category mapping
     */
    public function modifyMapping(Request $request, AICategoryMapping $mapping): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|string',
            'category_name' => 'required|string',
            'category_path' => 'nullable|string',
        ]);

        try {
            $mapping->modify(
                $request->user(),
                $request->input('category_id'),
                $request->input('category_name'),
                $request->input('category_path')
            );

            return response()->json([
                'success' => true,
                'message' => 'Category mapping modified successfully',
                'mapping' => $mapping->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to modify mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch approve mappings
     */
    public function batchApprove(Request $request): JsonResponse
    {
        $request->validate([
            'mapping_ids' => 'required|array',
            'mapping_ids.*' => 'exists:ai_category_mappings,id',
        ]);

        try {
            $mappings = AICategoryMapping::whereIn('id', $request->input('mapping_ids'))->get();

            foreach ($mappings as $mapping) {
                $mapping->approve($request->user());
            }

            return response()->json([
                'success' => true,
                'message' => count($mappings) . ' mappings approved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch approve: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch reject mappings
     */
    public function batchReject(Request $request): JsonResponse
    {
        $request->validate([
            'mapping_ids' => 'required|array',
            'mapping_ids.*' => 'exists:ai_category_mappings,id',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $mappings = AICategoryMapping::whereIn('id', $request->input('mapping_ids'))->get();
            $reason = $request->input('reason');

            foreach ($mappings as $mapping) {
                $mapping->reject($request->user(), $reason);
            }

            return response()->json([
                'success' => true,
                'message' => count($mappings) . ' mappings rejected successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch reject: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate mappings for multiple products (async)
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'channel_id' => 'required|exists:channels,id',
            'categories' => 'required|array',
        ]);

        try {
            $channel = Channel::findOrFail($request->input('channel_id'));
            $productIds = $request->input('product_ids');
            $categories = $request->input('categories');

            // Dispatch job for batch processing
            GenerateCategoryMappingsJob::dispatch($productIds, $channel, $categories);

            return response()->json([
                'success' => true,
                'message' => 'Category mapping generation started for ' . count($productIds) . ' products',
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
            'total' => AICategoryMapping::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->count(),
            'pending' => AICategoryMapping::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->pending()->count(),
            'approved' => AICategoryMapping::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->approved()->count(),
            'rejected' => AICategoryMapping::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->where('status', 'rejected')->count(),
            'by_channel' => [],
        ];

        // Stats by channel
        $byChannel = AICategoryMapping::whereHas('product', function ($q) use ($shop) {
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

        return response()->json([
            'success' => true,
            'statistics' => $stats,
        ]);
    }

    /**
     * Delete a mapping
     */
    public function deleteMapping(AICategoryMapping $mapping): JsonResponse
    {
        try {
            $mapping->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category mapping deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete mapping: ' . $e->getMessage(),
            ], 500);
        }
    }
}
