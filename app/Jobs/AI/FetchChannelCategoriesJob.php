<?php

namespace App\Jobs\AI;

use App\Models\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchChannelCategoriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 180, 300];
    public $timeout = 300;

    protected Channel $channel;

    /**
     * Create a new job instance.
     */
    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $categories = $this->fetchCategories();

            // Cache categories for 24 hours
            $cacheKey = "channel_categories_{$this->channel->id}";
            Cache::put($cacheKey, $categories, now()->addHours(24));

            Log::info('Channel categories fetched and cached', [
                'channel_id' => $this->channel->id,
                'channel_type' => $this->channel->channel_type,
                'category_count' => count($categories),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch channel categories', [
                'channel_id' => $this->channel->id,
                'channel_type' => $this->channel->channel_type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch categories based on channel type
     */
    protected function fetchCategories(): array
    {
        switch ($this->channel->channel_type) {
            case 'ebay':
                return $this->fetchEbayCategories();
            case 'amazon':
                return $this->fetchAmazonCategories();
            case 'etsy':
                return $this->fetchEtsyCategories();
            case 'walmart':
                return $this->fetchWalmartCategories();
            case 'shopify':
                return $this->fetchShopifyCategories();
            default:
                return [];
        }
    }

    /**
     * Fetch eBay categories
     */
    protected function fetchEbayCategories(): array
    {
        // Use existing eBay client
        $client = new \App\Services\Ebay\EbayClient($this->channel);

        // Fetch root categories
        $categories = [];
        $rootCategories = $client->getCategories();

        foreach ($rootCategories as $category) {
            $categories[] = [
                'id' => $category['CategoryID'],
                'name' => $category['CategoryName'],
                'path' => $category['CategoryName'],
                'parent_id' => $category['CategoryParentID'] ?? null,
                'level' => $category['CategoryLevel'] ?? 1,
            ];

            // Recursively fetch subcategories if needed
            if (isset($category['CategoryID'])) {
                $subcategories = $this->fetchEbaySubcategories($client, $category['CategoryID'], $category['CategoryName']);
                $categories = array_merge($categories, $subcategories);
            }
        }

        return $categories;
    }

    /**
     * Fetch eBay subcategories
     */
    protected function fetchEbaySubcategories($client, string $parentId, string $parentPath, int $level = 2): array
    {
        if ($level > 4) { // Limit depth to avoid too many API calls
            return [];
        }

        $categories = [];
        try {
            $subcategories = $client->getCategories($parentId);

            foreach ($subcategories as $category) {
                $path = $parentPath . ' > ' . $category['CategoryName'];
                $categories[] = [
                    'id' => $category['CategoryID'],
                    'name' => $category['CategoryName'],
                    'path' => $path,
                    'parent_id' => $parentId,
                    'level' => $level,
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch eBay subcategories', [
                'parent_id' => $parentId,
                'error' => $e->getMessage(),
            ]);
        }

        return $categories;
    }

    /**
     * Fetch Amazon categories (browse nodes)
     */
    protected function fetchAmazonCategories(): array
    {
        // Amazon browse nodes are typically provided via API or predefined
        // For now, return a basic structure - this should be enhanced
        return [
            ['id' => '1', 'name' => 'Books', 'path' => 'Books'],
            ['id' => '172282', 'name' => 'Electronics', 'path' => 'Electronics'],
            ['id' => '11091801', 'name' => 'PC', 'path' => 'Electronics > Computers & Accessories > PC'],
            // Add more as needed or fetch from Amazon API
        ];
    }

    /**
     * Fetch Etsy categories (taxonomy)
     */
    protected function fetchEtsyCategories(): array
    {
        $client = new \App\Services\Etsy\EtsyClient($this->channel);

        try {
            $result = $client->getTaxonomy();
            $taxonomies = $result['results'] ?? [];

            $categories = [];
            foreach ($taxonomies as $taxonomy) {
                $categories[] = [
                    'id' => (string) $taxonomy['id'],
                    'name' => $taxonomy['name'],
                    'path' => $this->buildEtsyPath($taxonomy),
                    'parent_id' => $taxonomy['parent_id'] ?? null,
                    'level' => $taxonomy['level'] ?? 1,
                ];
            }

            return $categories;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Etsy taxonomy', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Build Etsy category path
     */
    protected function buildEtsyPath(array $taxonomy): string
    {
        $path = $taxonomy['name'];

        if (isset($taxonomy['path']) && is_array($taxonomy['path'])) {
            $path = implode(' > ', $taxonomy['path']) . ' > ' . $taxonomy['name'];
        }

        return $path;
    }

    /**
     * Fetch Walmart categories
     */
    protected function fetchWalmartCategories(): array
    {
        // Walmart categories are typically provided via their taxonomy API
        // This is a placeholder - implement based on Walmart API
        return [
            ['id' => '0', 'name' => 'All Categories', 'path' => 'All Categories'],
            ['id' => '944', 'name' => 'Electronics', 'path' => 'Electronics'],
            ['id' => '1105910', 'name' => 'Clothing', 'path' => 'Clothing'],
            // Add more as needed
        ];
    }

    /**
     * Fetch Shopify categories
     */
    protected function fetchShopifyCategories(): array
    {
        // Shopify collections can serve as categories
        $client = new \App\Services\Shopify\ShopifyClient($this->channel);

        try {
            $collections = $client->getCollections();

            $categories = [];
            foreach ($collections['collections'] ?? [] as $collection) {
                $categories[] = [
                    'id' => (string) $collection['id'],
                    'name' => $collection['title'],
                    'path' => $collection['title'],
                ];
            }

            return $categories;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Shopify collections', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
