<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\AIMappingSuggestion;
use App\Services\AI\AIMappingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIMappingController extends Controller
{
    public function __construct(
        protected AIMappingService $aiMappingService
    ) {}

    /**
     * Generate AI mappings for a product
     */
    public function generateForProduct(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'channel_ids' => 'sometimes|array',
            'channel_ids.*' => 'exists:channels,id',
        ]);

        try {
            $suggestions = $this->aiMappingService->generateMappingsForProduct(
                $product,
                $request->input('channel_ids')
            );

            return response()->json([
                'success' => true,
                'message' => 'AI mappings generated successfully',
                'suggestions' => $suggestions->map(function ($suggestion) {
                    return [
                        'id' => $suggestion->id,
                        'channel' => $suggestion->channel->only(['id', 'name', 'type']),
                        'category' => $suggestion->channel_category_suggestion,
                        'mapping' => $suggestion->suggested_mapping,
                        'confidence' => $suggestion->confidence_score,
                        'reasoning' => $suggestion->ai_reasoning,
                        'warnings' => $suggestion->getWarnings(),
                        'high_confidence' => $suggestion->isHighConfidence(),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate AI mappings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all AI suggestions for a product
     */
    public function getSuggestions(Product $product): JsonResponse
    {
        $suggestions = AIMappingSuggestion::where('product_id', $product->id)
            ->with(['channel', 'approvedBy'])
            ->orderBy('confidence_score', 'desc')
            ->get();

        return response()->json([
            'suggestions' => $suggestions->map(function ($suggestion) {
                return [
                    'id' => $suggestion->id,
                    'status' => $suggestion->status,
                    'channel' => $suggestion->channel->only(['id', 'name', 'type']),
                    'category' => $suggestion->channel_category_suggestion,
                    'mapping' => $suggestion->suggested_mapping,
                    'confidence' => $suggestion->confidence_score,
                    'reasoning' => $suggestion->ai_reasoning,
                    'warnings' => $suggestion->getWarnings(),
                    'high_confidence' => $suggestion->isHighConfidence(),
                    'approved_at' => $suggestion->approved_at?->toIso8601String(),
                    'approved_by' => $suggestion->approvedBy?->only(['id', 'name']),
                ];
            }),
        ]);
    }

    /**
     * Approve a single AI suggestion
     */
    public function approveSuggestion(Request $request, AIMappingSuggestion $suggestion): JsonResponse
    {
        if ($suggestion->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This suggestion has already been processed',
            ], 422);
        }

        $success = $this->aiMappingService->approveSuggestion(
            $suggestion,
            $request->user()?->id
        );

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Suggestion approved and listing created',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to approve suggestion',
        ], 500);
    }

    /**
     * Reject an AI suggestion
     */
    public function rejectSuggestion(AIMappingSuggestion $suggestion): JsonResponse
    {
        if ($suggestion->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This suggestion has already been processed',
            ], 422);
        }

        $suggestion->update(['status' => 'rejected']);

        return response()->json([
            'success' => true,
            'message' => 'Suggestion rejected',
        ]);
    }

    /**
     * Batch approve multiple suggestions
     */
    public function batchApprove(Request $request): JsonResponse
    {
        $request->validate([
            'suggestion_ids' => 'required|array|min:1',
            'suggestion_ids.*' => 'exists:ai_mapping_suggestions,id',
        ]);

        $results = $this->aiMappingService->batchApproveSuggestions(
            $request->input('suggestion_ids'),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => "Approved {$results['approved']} suggestions, {$results['failed']} failed",
            'results' => $results,
        ]);
    }

    /**
     * Batch reject multiple suggestions
     */
    public function batchReject(Request $request): JsonResponse
    {
        $request->validate([
            'suggestion_ids' => 'required|array|min:1',
            'suggestion_ids.*' => 'exists:ai_mapping_suggestions,id',
        ]);

        $updated = AIMappingSuggestion::whereIn('id', $request->input('suggestion_ids'))
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        return response()->json([
            'success' => true,
            'message' => "Rejected {$updated} suggestions",
            'count' => $updated,
        ]);
    }

    /**
     * Update an AI suggestion before approving
     */
    public function updateSuggestion(Request $request, AIMappingSuggestion $suggestion): JsonResponse
    {
        $request->validate([
            'mapping' => 'sometimes|array',
            'category_id' => 'sometimes|exists:channel_categories,id',
        ]);

        if ($suggestion->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a processed suggestion',
            ], 422);
        }

        $updates = [];

        if ($request->has('mapping')) {
            $updates['suggested_mapping'] = $request->input('mapping');
        }

        if ($request->has('category_id')) {
            $category = \App\Models\ChannelCategory::find($request->input('category_id'));
            $updates['channel_category_suggestion'] = [
                'id' => $category->id,
                'name' => $category->name,
                'path' => $category->category_path,
            ];
        }

        $suggestion->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Suggestion updated successfully',
            'suggestion' => $suggestion->fresh(),
        ]);
    }

    /**
     * Get AI mapping statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $shopId = $request->user()->shop_id;

        $stats = [
            'total_suggestions' => AIMappingSuggestion::whereHas('product', function ($q) use ($shopId) {
                $q->where('shop_id', $shopId);
            })->count(),
            'pending' => AIMappingSuggestion::whereHas('product', function ($q) use ($shopId) {
                $q->where('shop_id', $shopId);
            })->where('status', 'pending')->count(),
            'approved' => AIMappingSuggestion::whereHas('product', function ($q) use ($shopId) {
                $q->where('shop_id', $shopId);
            })->where('status', 'approved')->count(),
            'rejected' => AIMappingSuggestion::whereHas('product', function ($q) use ($shopId) {
                $q->where('shop_id', $shopId);
            })->where('status', 'rejected')->count(),
            'high_confidence' => AIMappingSuggestion::whereHas('product', function ($q) use ($shopId) {
                $q->where('shop_id', $shopId);
            })->where('confidence_score', '>=', 0.8)->count(),
            'average_confidence' => AIMappingSuggestion::whereHas('product', function ($q) use ($shopId) {
                $q->where('shop_id', $shopId);
            })->avg('confidence_score'),
        ];

        return response()->json($stats);
    }
}
