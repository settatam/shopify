<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SmartPublishReport;
use App\Jobs\SmartPublishJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SmartPublishController extends Controller
{
    /**
     * Initiate smart publish for a product
     */
    public function publish(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'channel_ids' => 'required|array|min:1',
            'channel_ids.*' => 'exists:channels,id',
            'auto_optimize_title' => 'boolean',
            'auto_optimize_description' => 'boolean',
            'auto_map_category' => 'boolean',
            'auto_suggest_attributes' => 'boolean',
            'auto_optimize_images' => 'boolean',
            'auto_set_price' => 'boolean',
            'auto_check_compliance' => 'boolean',
        ]);

        $options = [
            'auto_optimize_title' => $request->input('auto_optimize_title', true),
            'auto_optimize_description' => $request->input('auto_optimize_description', true),
            'auto_map_category' => $request->input('auto_map_category', true),
            'auto_suggest_attributes' => $request->input('auto_suggest_attributes', true),
            'auto_optimize_images' => $request->input('auto_optimize_images', true),
            'auto_set_price' => $request->input('auto_set_price', true),
            'auto_check_compliance' => $request->input('auto_check_compliance', true),
        ];

        // Dispatch job
        SmartPublishJob::dispatch(
            $product,
            $request->input('channel_ids'),
            $request->user(),
            $options
        );

        return response()->json([
            'success' => true,
            'message' => 'Smart publish initiated. Processing in background.',
            'product_id' => $product->id,
        ]);
    }

    /**
     * Get smart publish report
     */
    public function getReport(SmartPublishReport $report): JsonResponse
    {
        $report->load(['product', 'user']);

        return response()->json([
            'success' => true,
            'report' => [
                'id' => $report->id,
                'product' => [
                    'id' => $report->product->id,
                    'title' => $report->product->title,
                ],
                'user' => [
                    'id' => $report->user->id,
                    'name' => $report->user->name,
                ],
                'status' => $report->status,
                'progress' => $report->getProgressPercentage(),
                'success_rate' => $report->getSuccessRate(),
                'channels' => [
                    'attempted' => $report->channels_attempted,
                    'succeeded' => $report->channels_succeeded,
                    'failed' => $report->channels_failed,
                ],
                'steps' => [
                    'total' => $report->total_steps,
                    'completed' => $report->completed_steps,
                    'failed' => $report->failed_steps,
                    'skipped' => $report->skipped_steps,
                ],
                'step_statuses' => [
                    'title_optimization' => $report->title_optimization_status,
                    'description_optimization' => $report->description_optimization_status,
                    'category_mapping' => $report->category_mapping_status,
                    'attribute_suggestion' => $report->attribute_suggestion_status,
                    'image_optimization' => $report->image_optimization_status,
                    'pricing' => $report->pricing_status,
                    'compliance_check' => $report->compliance_check_status,
                    'publishing' => $report->publishing_status,
                ],
                'results' => [
                    'optimized_content' => $report->optimized_content,
                    'mapped_categories' => $report->mapped_categories,
                    'suggested_attributes' => $report->suggested_attributes,
                    'optimized_images' => $report->optimized_images,
                    'pricing_data' => $report->pricing_data,
                    'compliance_results' => $report->compliance_results,
                    'publish_results' => $report->publish_results,
                ],
                'errors' => $report->errors,
                'warnings' => $report->warnings,
                'failure_reason' => $report->failure_reason,
                'timing' => [
                    'started_at' => $report->started_at,
                    'completed_at' => $report->completed_at,
                    'duration_seconds' => $report->duration_seconds,
                ],
                'created_at' => $report->created_at,
            ],
        ]);
    }

    /**
     * Get reports for a product
     */
    public function getProductReports(Request $request, Product $product): JsonResponse
    {
        $query = SmartPublishReport::where('product_id', $product->id)
            ->with(['user']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $reports = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /**
     * Get user's recent reports
     */
    public function getUserReports(Request $request): JsonResponse
    {
        $query = SmartPublishReport::where('user_id', $request->user()->id)
            ->with(['product']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('days')) {
            $query->recent((int) $request->input('days'));
        }

        $reports = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /**
     * Get statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $stats = [
            'total' => SmartPublishReport::where('user_id', $userId)->count(),
            'processing' => SmartPublishReport::where('user_id', $userId)->processing()->count(),
            'completed' => SmartPublishReport::where('user_id', $userId)->where('status', 'completed')->count(),
            'partial_success' => SmartPublishReport::where('user_id', $userId)->where('status', 'partial_success')->count(),
            'failed' => SmartPublishReport::where('user_id', $userId)->where('status', 'failed')->count(),
            'total_channels_attempted' => SmartPublishReport::where('user_id', $userId)->sum('channels_attempted'),
            'total_channels_succeeded' => SmartPublishReport::where('user_id', $userId)->sum('channels_succeeded'),
            'total_channels_failed' => SmartPublishReport::where('user_id', $userId)->sum('channels_failed'),
            'average_duration' => SmartPublishReport::where('user_id', $userId)
                ->whereNotNull('duration_seconds')
                ->avg('duration_seconds'),
            'recent_reports' => SmartPublishReport::where('user_id', $userId)
                ->recent(7)
                ->with(['product'])
                ->latest()
                ->take(10)
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'product_id' => $r->product_id,
                    'product_title' => $r->product->title,
                    'status' => $r->status,
                    'channels_succeeded' => $r->channels_succeeded,
                    'channels_attempted' => $r->channels_attempted,
                    'created_at' => $r->created_at,
                ]),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats,
        ]);
    }

    /**
     * Preview what smart publish will do (dry run)
     */
    public function preview(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'channel_ids' => 'required|array|min:1',
            'channel_ids.*' => 'exists:channels,id',
        ]);

        $channelIds = $request->input('channel_ids');

        $preview = [
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'description' => substr($product->description ?? '', 0, 100) . '...',
                'current_price' => $product->variants->first()?->price,
            ],
            'channels' => $channelIds,
            'steps' => [
                [
                    'name' => 'Title Optimization',
                    'description' => 'AI will optimize titles for each marketplace',
                    'enabled' => true,
                ],
                [
                    'name' => 'Description Optimization',
                    'description' => 'AI will create compelling descriptions',
                    'enabled' => true,
                ],
                [
                    'name' => 'Category Mapping',
                    'description' => 'AI will select the best category',
                    'enabled' => true,
                ],
                [
                    'name' => 'Attribute Suggestions',
                    'description' => 'System will suggest required attributes',
                    'enabled' => true,
                ],
                [
                    'name' => 'Image Optimization',
                    'description' => 'Images will be resized and optimized',
                    'enabled' => true,
                ],
                [
                    'name' => 'Competitive Pricing',
                    'description' => 'Price will be set based on competition',
                    'enabled' => true,
                ],
                [
                    'name' => 'Compliance Check',
                    'description' => 'Ensure listing meets marketplace rules',
                    'enabled' => true,
                ],
                [
                    'name' => 'Multi-Channel Publishing',
                    'description' => 'Publish to all selected channels',
                    'enabled' => true,
                ],
            ],
            'estimated_duration' => '2-5 minutes',
        ];

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    /**
     * Cancel a processing report
     */
    public function cancel(SmartPublishReport $report): JsonResponse
    {
        if (!$report->isProcessing()) {
            return response()->json([
                'success' => false,
                'message' => 'Report is not currently processing',
            ], 400);
        }

        // In production, you would kill the job here
        // For now, just mark as failed

        $report->markFailed('Cancelled by user');

        return response()->json([
            'success' => true,
            'message' => 'Smart publish cancelled',
        ]);
    }

    /**
     * Delete a report
     */
    public function delete(SmartPublishReport $report): JsonResponse
    {
        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully',
        ]);
    }

    /**
     * Retry a failed publish
     */
    public function retry(SmartPublishReport $report): JsonResponse
    {
        if ($report->isProcessing()) {
            return response()->json([
                'success' => false,
                'message' => 'Report is currently processing',
            ], 400);
        }

        // Dispatch new job with same parameters
        SmartPublishJob::dispatch(
            $report->product,
            $report->selected_channels,
            $report->user,
            [
                'auto_optimize_title' => $report->auto_optimize_title,
                'auto_optimize_description' => $report->auto_optimize_description,
                'auto_map_category' => $report->auto_map_category,
                'auto_suggest_attributes' => $report->auto_suggest_attributes,
                'auto_optimize_images' => $report->auto_optimize_images,
                'auto_set_price' => $report->auto_set_price,
                'auto_check_compliance' => $report->auto_check_compliance,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Smart publish retry initiated',
        ]);
    }
}
