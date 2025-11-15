<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DetectDeadListingsJob;
use App\Jobs\ProcessAutoRelistJob;
use App\Models\AutoRelistAction;
use App\Models\AutoRelistCampaign;
use App\Models\Channel;
use App\Services\AutoRelistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AutoRelistController extends Controller
{
    public function __construct(
        protected AutoRelistService $service
    ) {}

    /**
     * Get all campaigns for the authenticated shop.
     */
    public function getCampaigns(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $campaigns = AutoRelistCampaign::where('shop_id', $shop->id)
            ->with('channel')
            ->when($request->has('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($campaigns);
    }

    /**
     * Get a specific campaign.
     */
    public function getCampaign(AutoRelistCampaign $campaign): JsonResponse
    {
        $campaign->load(['channel', 'actions' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json([
            'campaign' => $campaign,
            'statistics' => [
                'success_rate' => $campaign->getSuccessRate(),
                'can_relist_more_today' => $campaign->canRelistMoreToday(),
                'is_due_for_run' => $campaign->isDueForRun(),
            ],
        ]);
    }

    /**
     * Create a new campaign.
     */
    public function createCampaign(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'channel_id' => 'nullable|exists:channels,id',
            'min_days_listed' => 'required|integer|min:1',
            'max_views' => 'nullable|integer|min:0',
            'max_sales' => 'nullable|integer|min:0',
            'min_view_to_sale_ratio' => 'nullable|numeric|min:0',
            'auto_rewrite_title' => 'boolean',
            'auto_fix_description' => 'boolean',
            'auto_fix_category' => 'boolean',
            'auto_add_attributes' => 'boolean',
            'auto_swap_images' => 'boolean',
            'auto_adjust_price' => 'boolean',
            'price_adjustment_strategy' => 'in:decrease,increase,market_based,none',
            'price_adjustment_percent' => 'nullable|numeric|min:0|max:100',
            'optimize_relist_time' => 'boolean',
            'preferred_relist_time' => 'nullable|date_format:H:i',
            'preferred_relist_days' => 'nullable|array',
            'preferred_relist_days.*' => 'integer|min:0|max:6',
            'requires_approval' => 'boolean',
            'check_frequency_hours' => 'required|integer|min:1',
            'max_relists_per_day' => 'nullable|integer|min:1',
            'max_relists_per_product' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify channel belongs to shop if provided
        if ($request->channel_id) {
            $channel = Channel::where('id', $request->channel_id)
                ->where('shop_id', $shop->id)
                ->first();

            if (!$channel) {
                return response()->json([
                    'message' => 'Channel not found or does not belong to your shop',
                ], 404);
            }
        }

        DB::beginTransaction();
        try {
            $campaign = AutoRelistCampaign::create([
                'shop_id' => $shop->id,
                'channel_id' => $request->channel_id,
                'name' => $request->name,
                'min_days_listed' => $request->min_days_listed,
                'max_views' => $request->max_views,
                'max_sales' => $request->max_sales,
                'min_view_to_sale_ratio' => $request->min_view_to_sale_ratio,
                'auto_rewrite_title' => $request->boolean('auto_rewrite_title', true),
                'auto_fix_description' => $request->boolean('auto_fix_description', true),
                'auto_fix_category' => $request->boolean('auto_fix_category', true),
                'auto_add_attributes' => $request->boolean('auto_add_attributes', true),
                'auto_swap_images' => $request->boolean('auto_swap_images', true),
                'auto_adjust_price' => $request->boolean('auto_adjust_price', true),
                'price_adjustment_strategy' => $request->input('price_adjustment_strategy', 'market_based'),
                'price_adjustment_percent' => $request->price_adjustment_percent,
                'optimize_relist_time' => $request->boolean('optimize_relist_time', true),
                'preferred_relist_time' => $request->preferred_relist_time,
                'preferred_relist_days' => $request->preferred_relist_days,
                'requires_approval' => $request->boolean('requires_approval', true),
                'check_frequency_hours' => $request->check_frequency_hours,
                'max_relists_per_day' => $request->max_relists_per_day,
                'max_relists_per_product' => $request->max_relists_per_product,
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Schedule first run if active
            if ($campaign->is_active) {
                DetectDeadListingsJob::dispatch($campaign)
                    ->delay(now()->addMinutes(5)); // Start in 5 minutes
            }

            DB::commit();

            return response()->json([
                'message' => 'Campaign created successfully',
                'campaign' => $campaign->load('channel'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create campaign',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing campaign.
     */
    public function updateCampaign(Request $request, AutoRelistCampaign $campaign): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'channel_id' => 'nullable|exists:channels,id',
            'min_days_listed' => 'integer|min:1',
            'max_views' => 'nullable|integer|min:0',
            'max_sales' => 'nullable|integer|min:0',
            'min_view_to_sale_ratio' => 'nullable|numeric|min:0',
            'auto_rewrite_title' => 'boolean',
            'auto_fix_description' => 'boolean',
            'auto_fix_category' => 'boolean',
            'auto_add_attributes' => 'boolean',
            'auto_swap_images' => 'boolean',
            'auto_adjust_price' => 'boolean',
            'price_adjustment_strategy' => 'in:decrease,increase,market_based,none',
            'price_adjustment_percent' => 'nullable|numeric|min:0|max:100',
            'optimize_relist_time' => 'boolean',
            'preferred_relist_time' => 'nullable|date_format:H:i',
            'preferred_relist_days' => 'nullable|array',
            'preferred_relist_days.*' => 'integer|min:0|max:6',
            'requires_approval' => 'boolean',
            'check_frequency_hours' => 'integer|min:1',
            'max_relists_per_day' => 'nullable|integer|min:1',
            'max_relists_per_product' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify channel belongs to shop if provided
        if ($request->has('channel_id') && $request->channel_id) {
            $channel = Channel::where('id', $request->channel_id)
                ->where('shop_id', $campaign->shop_id)
                ->first();

            if (!$channel) {
                return response()->json([
                    'message' => 'Channel not found or does not belong to your shop',
                ], 404);
            }
        }

        $wasActive = $campaign->is_active;
        $campaign->update($request->only([
            'name', 'channel_id', 'min_days_listed', 'max_views', 'max_sales',
            'min_view_to_sale_ratio', 'auto_rewrite_title', 'auto_fix_description',
            'auto_fix_category', 'auto_add_attributes', 'auto_swap_images',
            'auto_adjust_price', 'price_adjustment_strategy', 'price_adjustment_percent',
            'optimize_relist_time', 'preferred_relist_time', 'preferred_relist_days',
            'requires_approval', 'check_frequency_hours', 'max_relists_per_day',
            'max_relists_per_product', 'is_active',
        ]));

        // Schedule run if newly activated
        if (!$wasActive && $campaign->is_active) {
            DetectDeadListingsJob::dispatch($campaign)
                ->delay(now()->addMinutes(5));
        }

        return response()->json([
            'message' => 'Campaign updated successfully',
            'campaign' => $campaign->load('channel'),
        ]);
    }

    /**
     * Delete a campaign.
     */
    public function deleteCampaign(AutoRelistCampaign $campaign): JsonResponse
    {
        $campaign->delete();

        return response()->json([
            'message' => 'Campaign deleted successfully',
        ]);
    }

    /**
     * Run a campaign manually.
     */
    public function runCampaign(AutoRelistCampaign $campaign): JsonResponse
    {
        if (!$campaign->is_active) {
            return response()->json([
                'message' => 'Campaign is not active',
            ], 400);
        }

        DetectDeadListingsJob::dispatch($campaign);

        return response()->json([
            'message' => 'Campaign queued for execution',
            'campaign' => $campaign,
        ]);
    }

    /**
     * Get pending approval actions.
     */
    public function getPendingApprovals(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $actions = AutoRelistAction::whereHas('campaign', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })
            ->where('status', 'pending_approval')
            ->with(['campaign', 'product', 'channel'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($actions);
    }

    /**
     * Get action details.
     */
    public function getAction(AutoRelistAction $action): JsonResponse
    {
        $action->load(['campaign', 'product', 'channel', 'approvedBy', 'rejectedBy']);

        return response()->json([
            'action' => $action,
            'changes_summary' => $action->getChangesSummary(),
            'improvement' => $action->getImprovementPercentage(),
            'is_successful' => $action->isSuccessful(),
        ]);
    }

    /**
     * Get actions for a product.
     */
    public function getProductActions(Request $request, int $productId): JsonResponse
    {
        $shop = $request->user()->shop;

        $actions = AutoRelistAction::whereHas('campaign', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })
            ->where('product_id', $productId)
            ->with(['campaign', 'channel'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($actions);
    }

    /**
     * Approve an action.
     */
    public function approveAction(Request $request, AutoRelistAction $action): JsonResponse
    {
        if ($action->status !== 'pending_approval') {
            return response()->json([
                'message' => 'Action is not pending approval',
                'current_status' => $action->status,
            ], 400);
        }

        $action->approve($request->user());

        // Queue for processing
        ProcessAutoRelistJob::dispatch($action);

        return response()->json([
            'message' => 'Action approved and queued for processing',
            'action' => $action->load(['campaign', 'product', 'channel']),
        ]);
    }

    /**
     * Reject an action.
     */
    public function rejectAction(Request $request, AutoRelistAction $action): JsonResponse
    {
        if ($action->status !== 'pending_approval') {
            return response()->json([
                'message' => 'Action is not pending approval',
                'current_status' => $action->status,
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $action->reject($request->user(), $request->input('reason'));

        return response()->json([
            'message' => 'Action rejected',
            'action' => $action->load(['campaign', 'product', 'channel']),
        ]);
    }

    /**
     * Bulk approve actions.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action_ids' => 'required|array',
            'action_ids.*' => 'integer|exists:auto_relist_actions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $shop = $request->user()->shop;
        $approved = 0;
        $skipped = 0;

        foreach ($request->action_ids as $actionId) {
            $action = AutoRelistAction::whereHas('campaign', function ($query) use ($shop) {
                    $query->where('shop_id', $shop->id);
                })
                ->find($actionId);

            if ($action && $action->status === 'pending_approval') {
                $action->approve($request->user());
                ProcessAutoRelistJob::dispatch($action);
                $approved++;
            } else {
                $skipped++;
            }
        }

        return response()->json([
            'message' => 'Bulk approval completed',
            'approved' => $approved,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Get statistics for auto-relist campaigns.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $stats = [
            'total_campaigns' => AutoRelistCampaign::where('shop_id', $shop->id)->count(),
            'active_campaigns' => AutoRelistCampaign::where('shop_id', $shop->id)
                ->where('is_active', true)
                ->count(),
            'total_actions' => AutoRelistAction::whereHas('campaign', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })->count(),
            'pending_approval' => AutoRelistAction::whereHas('campaign', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })
                ->where('status', 'pending_approval')
                ->count(),
            'completed_relists' => AutoRelistAction::whereHas('campaign', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })
                ->where('status', 'completed')
                ->count(),
            'successful_relists' => AutoRelistAction::whereHas('campaign', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })
                ->where('status', 'completed')
                ->where('sales_after', '>', 0)
                ->count(),
        ];

        // Calculate average improvement
        $completedActions = AutoRelistAction::whereHas('campaign', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })
            ->where('status', 'completed')
            ->whereNotNull('views_after')
            ->get();

        $improvements = $completedActions->map(function ($action) {
            return $action->getImprovementPercentage();
        })->filter()->values();

        $stats['average_improvement'] = $improvements->isNotEmpty()
            ? round($improvements->average(), 2)
            : null;

        // Recent activity
        $stats['recent_actions'] = AutoRelistAction::whereHas('campaign', function ($query) use ($shop) {
                $query->where('shop_id', $shop->id);
            })
            ->with(['product', 'channel', 'campaign'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json($stats);
    }

    /**
     * Delete an action.
     */
    public function deleteAction(AutoRelistAction $action): JsonResponse
    {
        // Only allow deletion of rejected or failed actions
        if (!in_array($action->status, ['rejected', 'failed'])) {
            return response()->json([
                'message' => 'Can only delete rejected or failed actions',
                'current_status' => $action->status,
            ], 400);
        }

        $action->delete();

        return response()->json([
            'message' => 'Action deleted successfully',
        ]);
    }
}
