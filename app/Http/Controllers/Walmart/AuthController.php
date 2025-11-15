<?php

namespace App\Http\Controllers\Walmart;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\Walmart\WalmartClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AuthController extends Controller
{
    /**
     * Show Walmart connection form
     */
    public function connect(Request $request)
    {
        return inertia('Walmart/Connect', [
            'shopId' => $request->user()->shop_id,
        ]);
    }

    /**
     * Save Walmart credentials and test connection
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'name' => 'sometimes|string',
        ]);

        $shop = $request->user()->shop;

        // Test credentials by trying to get a token
        try {
            $testChannel = new Channel([
                'shop_id' => $shop->id,
                'type' => 'walmart',
                'auth_json' => [
                    'client_id' => $request->input('client_id'),
                    'client_secret' => $request->input('client_secret'),
                ],
            ]);

            $client = new WalmartClient($testChannel);
            // This will attempt to get a token and throw if credentials are invalid
            $client->getItems(['limit' => 1]);

            // Credentials are valid, save the channel
            $channel = Channel::updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'type' => 'walmart',
                ],
                [
                    'name' => $request->input('name', 'Walmart Marketplace'),
                    'status' => 'connected',
                    'auth_json' => [
                        'client_id' => $request->input('client_id'),
                        'client_secret' => $request->input('client_secret'),
                        'connected_at' => now()->toIso8601String(),
                    ],
                ]
            );

            // Fetch and store taxonomy (categories)
            $this->fetchTaxonomy($channel);

            return response()->json([
                'success' => true,
                'message' => 'Walmart account connected successfully',
                'channel' => $channel->only(['id', 'name', 'type', 'status']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to Walmart: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Disconnect Walmart account
     */
    public function disconnect(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'walmart') {
            return response()->json([
                'success' => false,
                'message' => 'This is not a Walmart channel',
            ], 422);
        }

        if ($channel->shop_id !== $request->user()->shop_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $channel->update([
            'status' => 'disconnected',
            'auth_json' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Walmart account disconnected',
        ]);
    }

    /**
     * Test Walmart connection
     */
    public function test(Request $request, Channel $channel): JsonResponse
    {
        if ($channel->type !== 'walmart' || $channel->status !== 'connected') {
            return response()->json([
                'success' => false,
                'message' => 'Walmart channel not connected',
            ], 422);
        }

        try {
            $client = new WalmartClient($channel);
            $items = $client->getItems(['limit' => 5]);

            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'data' => [
                    'item_count' => count($items['elements'] ?? []),
                    'total_count' => $items['totalCount'] ?? 0,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Fetch and store Walmart taxonomy
     */
    protected function fetchTaxonomy(Channel $channel): void
    {
        try {
            $client = new WalmartClient($channel);
            $taxonomy = $client->getTaxonomy();

            // Store categories in channel_categories table
            if (isset($taxonomy['categories'])) {
                foreach ($taxonomy['categories'] as $category) {
                    \App\Models\ChannelCategory::updateOrCreate(
                        [
                            'channel_id' => $channel->id,
                            'external_id' => $category['id'] ?? $category['categoryId'],
                        ],
                        [
                            'name' => $category['name'] ?? '',
                            'category_path' => $category['path'] ?? '',
                            'parent_id' => $category['parentId'] ?? null,
                            'attributes_json' => [
                                'required' => [],
                                'optional' => [],
                            ],
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch Walmart taxonomy: ' . $e->getMessage());
        }
    }
}
