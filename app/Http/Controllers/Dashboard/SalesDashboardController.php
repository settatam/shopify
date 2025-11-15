<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ChannelOrder;
use App\Models\PosTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesDashboardController extends Controller
{
    /**
     * Get dashboard overview data
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $filters = $this->getFilters($request);

        return response()->json([
            'stats' => $this->getStats($shop->id, $filters),
            'chart_data' => $this->getChartData($shop->id, $filters),
            'recent_sales' => $this->getRecentSales($shop->id, $filters, 10),
            'breakdown' => $this->getBreakdown($shop->id, $filters),
        ]);
    }

    /**
     * Get overall statistics
     */
    protected function getStats(int $shopId, array $filters): array
    {
        // Get channel orders stats
        $channelOrdersQuery = ChannelOrder::where('shop_id', $shopId);
        $this->applyFilters($channelOrdersQuery, $filters, 'channel_order');

        $channelOrdersStats = $channelOrdersQuery->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(AVG(total_amount), 0) as avg_order_value
        ')->first();

        // Get POS transactions stats
        $posQuery = PosTransaction::where('shop_id', $shopId)
            ->where('status', '!=', 'voided');
        $this->applyFilters($posQuery, $filters, 'pos_transaction');

        $posStats = $posQuery->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(total), 0) as total_revenue,
            COALESCE(AVG(total), 0) as avg_order_value
        ')->first();

        // Combine stats
        $totalOrders = ($channelOrdersStats->count ?? 0) + ($posStats->count ?? 0);
        $totalRevenue = ($channelOrdersStats->total_revenue ?? 0) + ($posStats->total_revenue ?? 0);
        $avgOrderValue = $totalOrders > 0
            ? $totalRevenue / $totalOrders
            : 0;

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'avg_order_value' => round($avgOrderValue, 2),
            'channel_orders' => $channelOrdersStats->count ?? 0,
            'pos_transactions' => $posStats->count ?? 0,
        ];
    }

    /**
     * Get chart data for visualization
     */
    protected function getChartData(int $shopId, array $filters): array
    {
        $groupBy = $this->getGroupByFormat($filters);

        // Get channel orders chart data
        $channelOrdersQuery = ChannelOrder::where('shop_id', $shopId);
        $this->applyFilters($channelOrdersQuery, $filters, 'channel_order');

        $channelData = $channelOrdersQuery
            ->selectRaw("
                {$groupBy['select']} as period,
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as revenue
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Get POS transactions chart data
        $posQuery = PosTransaction::where('shop_id', $shopId)
            ->where('status', '!=', 'voided');
        $this->applyFilters($posQuery, $filters, 'pos_transaction');

        $posData = $posQuery
            ->selectRaw("
                {$groupBy['select']} as period,
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as revenue
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Merge and format data
        $combined = collect();

        foreach ($channelData as $item) {
            $period = $item->period;
            $combined[$period] = [
                'period' => $this->formatPeriod($period, $groupBy['format']),
                'orders' => $item->count,
                'revenue' => $item->revenue,
            ];
        }

        foreach ($posData as $item) {
            $period = $item->period;
            if (isset($combined[$period])) {
                $combined[$period]['orders'] += $item->count;
                $combined[$period]['revenue'] += $item->revenue;
            } else {
                $combined[$period] = [
                    'period' => $this->formatPeriod($period, $groupBy['format']),
                    'orders' => $item->count,
                    'revenue' => $item->revenue,
                ];
            }
        }

        return $combined->values()->map(function ($item) {
            $item['revenue'] = round($item['revenue'], 2);
            return $item;
        })->toArray();
    }

    /**
     * Get recent sales
     */
    protected function getRecentSales(int $shopId, array $filters, int $limit = 10): array
    {
        $sales = [];

        // Get channel orders
        $channelOrdersQuery = ChannelOrder::where('shop_id', $shopId)
            ->with('channel');
        $this->applyFilters($channelOrdersQuery, $filters, 'channel_order');

        $channelOrders = $channelOrdersQuery
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'type' => 'channel_order',
                    'source' => $order->channel?->channel_type ?? 'unknown',
                    'order_number' => $order->order_number,
                    'total' => (float) $order->total_amount,
                    'currency' => $order->currency ?? 'USD',
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'status' => $order->status,
                    'fulfillment_status' => $order->fulfillment_status,
                    'payment_status' => $order->payment_status,
                    'items_count' => count($order->items ?? []),
                    'created_at' => $order->created_at->toIso8601String(),
                    'created_at_human' => $order->created_at->diffForHumans(),
                ];
            });

        // Get POS transactions
        $posQuery = PosTransaction::where('shop_id', $shopId)
            ->where('status', '!=', 'voided');
        $this->applyFilters($posQuery, $filters, 'pos_transaction');

        $posTransactions = $posQuery
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => 'pos_transaction',
                    'source' => 'POS',
                    'order_number' => $transaction->transaction_number,
                    'total' => (float) $transaction->total,
                    'currency' => 'USD',
                    'customer_name' => $transaction->customer_name,
                    'customer_email' => $transaction->customer_email,
                    'status' => $transaction->status,
                    'fulfillment_status' => 'fulfilled',
                    'payment_status' => 'paid',
                    'payment_method' => $transaction->payment_method,
                    'items_count' => count($transaction->line_items ?? []),
                    'created_at' => $transaction->created_at->toIso8601String(),
                    'created_at_human' => $transaction->created_at->diffForHumans(),
                ];
            });

        // Merge and sort by date
        $sales = $channelOrders->merge($posTransactions)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->toArray();

        return $sales;
    }

    /**
     * Get breakdown by source/channel
     */
    protected function getBreakdown(int $shopId, array $filters): array
    {
        $breakdown = [];

        // Get channel orders breakdown
        $channelOrdersQuery = ChannelOrder::where('shop_id', $shopId)
            ->with('channel');
        $this->applyFilters($channelOrdersQuery, $filters, 'channel_order');

        $channelBreakdown = $channelOrdersQuery
            ->join('channels', 'channel_orders.channel_id', '=', 'channels.id')
            ->selectRaw('
                channels.channel_type as source,
                COUNT(*) as count,
                COALESCE(SUM(channel_orders.total_amount), 0) as revenue
            ')
            ->groupBy('channels.channel_type')
            ->get();

        foreach ($channelBreakdown as $item) {
            $breakdown[$item->source] = [
                'source' => ucfirst($item->source),
                'count' => $item->count,
                'revenue' => round($item->revenue, 2),
            ];
        }

        // Get POS transactions breakdown
        $posQuery = PosTransaction::where('shop_id', $shopId)
            ->where('status', '!=', 'voided');
        $this->applyFilters($posQuery, $filters, 'pos_transaction');

        $posBreakdown = $posQuery
            ->selectRaw('
                payment_method,
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as revenue
            ')
            ->groupBy('payment_method')
            ->get();

        foreach ($posBreakdown as $item) {
            $key = 'POS (' . ucfirst($item->payment_method) . ')';
            $breakdown[$key] = [
                'source' => $key,
                'count' => $item->count,
                'revenue' => round($item->revenue, 2),
            ];
        }

        return array_values($breakdown);
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, array $filters, string $type): void
    {
        // Date range filter
        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if ($type === 'channel_order') {
            // Marketplace/channel filter
            if (isset($filters['channel_type'])) {
                $query->whereHas('channel', function ($q) use ($filters) {
                    $q->where('channel_type', $filters['channel_type']);
                });
            }

            // Fulfillment status filter
            if (isset($filters['fulfillment_status'])) {
                $query->where('fulfillment_status', $filters['fulfillment_status']);
            }

            // Payment status filter
            if (isset($filters['payment_status'])) {
                $query->where('payment_status', $filters['payment_status']);
            }

            // Order status filter
            if (isset($filters['order_status'])) {
                $query->where('status', $filters['order_status']);
            }
        } elseif ($type === 'pos_transaction') {
            // Payment method filter
            if (isset($filters['payment_method'])) {
                $query->where('payment_method', $filters['payment_method']);
            }

            // Status filter
            if (isset($filters['order_status'])) {
                $query->where('status', $filters['order_status']);
            }
        }
    }

    /**
     * Extract filters from request
     */
    protected function getFilters(Request $request): array
    {
        $filters = [];

        // Date range
        if ($request->has('start_date')) {
            $filters['start_date'] = Carbon::parse($request->input('start_date'))->startOfDay();
        } else {
            // Default to last 30 days
            $filters['start_date'] = Carbon::now()->subDays(30)->startOfDay();
        }

        if ($request->has('end_date')) {
            $filters['end_date'] = Carbon::parse($request->input('end_date'))->endOfDay();
        } else {
            $filters['end_date'] = Carbon::now()->endOfDay();
        }

        // Channel/marketplace type
        if ($request->has('channel_type') && $request->input('channel_type') !== 'all') {
            $filters['channel_type'] = $request->input('channel_type');
        }

        // Fulfillment status
        if ($request->has('fulfillment_status') && $request->input('fulfillment_status') !== 'all') {
            $filters['fulfillment_status'] = $request->input('fulfillment_status');
        }

        // Payment status
        if ($request->has('payment_status') && $request->input('payment_status') !== 'all') {
            $filters['payment_status'] = $request->input('payment_status');
        }

        // Order status
        if ($request->has('order_status') && $request->input('order_status') !== 'all') {
            $filters['order_status'] = $request->input('order_status');
        }

        // Payment method (POS)
        if ($request->has('payment_method') && $request->input('payment_method') !== 'all') {
            $filters['payment_method'] = $request->input('payment_method');
        }

        return $filters;
    }

    /**
     * Get group by format based on date range
     */
    protected function getGroupByFormat(array $filters): array
    {
        $start = $filters['start_date'];
        $end = $filters['end_date'];
        $diffDays = $start->diffInDays($end);

        if ($diffDays <= 1) {
            // Hourly for 1 day or less
            return [
                'select' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
                'format' => 'hourly',
            ];
        } elseif ($diffDays <= 31) {
            // Daily for up to 31 days
            return [
                'select' => "DATE(created_at)",
                'format' => 'daily',
            ];
        } elseif ($diffDays <= 365) {
            // Weekly for up to 1 year
            return [
                'select' => "DATE_FORMAT(created_at, '%Y-%u')",
                'format' => 'weekly',
            ];
        } else {
            // Monthly for more than 1 year
            return [
                'select' => "DATE_FORMAT(created_at, '%Y-%m')",
                'format' => 'monthly',
            ];
        }
    }

    /**
     * Format period for display
     */
    protected function formatPeriod(string $period, string $format): string
    {
        switch ($format) {
            case 'hourly':
                return Carbon::parse($period)->format('M j, g A');
            case 'daily':
                return Carbon::parse($period)->format('M j, Y');
            case 'weekly':
                $parts = explode('-', $period);
                return 'Week ' . $parts[1] . ', ' . $parts[0];
            case 'monthly':
                return Carbon::parse($period . '-01')->format('M Y');
            default:
                return $period;
        }
    }
}
