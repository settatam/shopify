<?php

namespace App\Services\Pricing;

use App\Models\CompetitorProduct;
use App\Models\CompetitorPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorPriceScraper
{
    /**
     * Scrape price for a competitor product
     */
    public function scrapePrice(CompetitorProduct $competitor): ?array
    {
        try {
            $priceData = match ($competitor->channel_type) {
                'ebay' => $this->scrapeEbayPrice($competitor),
                'amazon' => $this->scrapeAmazonPrice($competitor),
                'etsy' => $this->scrapeEtsyPrice($competitor),
                'walmart' => $this->scrapeWalmartPrice($competitor),
                'shopify' => $this->scrapeShopifyPrice($competitor),
                default => $this->scrapeGenericPrice($competitor),
            };

            if ($priceData) {
                $this->recordPrice($competitor, $priceData);
                $competitor->updateCurrentPrice($priceData);
            }

            return $priceData;
        } catch (\Exception $e) {
            Log::error('Failed to scrape competitor price', [
                'competitor_id' => $competitor->id,
                'url' => $competitor->competitor_product_url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Scrape eBay price using eBay Finding API or web scraping
     */
    protected function scrapeEbayPrice(CompetitorProduct $competitor): ?array
    {
        // If we have an item ID, use eBay API
        if ($competitor->competitor_product_id) {
            return $this->scrapeEbayPriceViaApi($competitor);
        }

        // Otherwise, scrape from URL
        return $this->scrapeEbayPriceViaWeb($competitor);
    }

    /**
     * Scrape eBay price via API
     */
    protected function scrapeEbayPriceViaApi(CompetitorProduct $competitor): ?array
    {
        $channel = $competitor->channel;
        $credentials = json_decode($channel->credentials, true);

        if (!isset($credentials['app_id'])) {
            return null;
        }

        try {
            $response = Http::get('https://svcs.ebay.com/services/search/FindingService/v1', [
                'OPERATION-NAME' => 'findItemsAdvanced',
                'SERVICE-VERSION' => '1.0.0',
                'SECURITY-APPNAME' => $credentials['app_id'],
                'RESPONSE-DATA-FORMAT' => 'JSON',
                'REST-PAYLOAD' => '',
                'itemFilter(0).name' => 'ListingType',
                'itemFilter(0).value' => 'FixedPrice',
                'keywords' => $competitor->competitor_product_id,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $items = $data['findItemsAdvancedResponse'][0]['searchResult'][0]['item'] ?? [];

                if (empty($items)) {
                    return null;
                }

                $item = $items[0]; // Get first matching item

                return [
                    'price' => (float) $item['sellingStatus'][0]['currentPrice'][0]['__value__'],
                    'currency' => $item['sellingStatus'][0]['currentPrice'][0]['@currencyId'] ?? 'USD',
                    'shipping_cost' => isset($item['shippingInfo'][0]['shippingServiceCost'][0]['__value__'])
                        ? (float) $item['shippingInfo'][0]['shippingServiceCost'][0]['__value__']
                        : 0,
                    'in_stock' => true,
                    'stock_quantity' => null,
                ];
            }
        } catch (\Exception $e) {
            Log::error('eBay API scraping failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Scrape eBay price via web scraping
     */
    protected function scrapeEbayPriceViaWeb(CompetitorProduct $competitor): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->get($competitor->competitor_product_url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // Extract price using regex (simplified - in production use a proper HTML parser)
            if (preg_match('/<span[^>]*itemprop="price"[^>]*>.*?(\d+\.?\d*)<\/span>/i', $html, $matches)) {
                return [
                    'price' => (float) $matches[1],
                    'currency' => 'USD', // Would need to extract from page
                    'shipping_cost' => 0, // Would need to extract from page
                    'in_stock' => !str_contains($html, 'Out of stock'),
                    'stock_quantity' => null,
                ];
            }
        } catch (\Exception $e) {
            Log::error('eBay web scraping failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Scrape Amazon price
     */
    protected function scrapeAmazonPrice(CompetitorProduct $competitor): ?array
    {
        // Amazon requires Product Advertising API or web scraping with caution
        // This is a simplified example - in production, use Amazon PA API

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->get($competitor->competitor_product_url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // Extract price (simplified regex - in production use proper parser)
            if (preg_match('/<span[^>]*class="[^"]*a-price-whole[^"]*"[^>]*>(\d+)<\/span>/', $html, $matches)) {
                $dollars = (int) $matches[1];
                $cents = 0;

                if (preg_match('/<span[^>]*class="[^"]*a-price-fraction[^"]*"[^>]*>(\d+)<\/span>/', $html, $centsMatch)) {
                    $cents = (int) $centsMatch[1];
                }

                return [
                    'price' => (float) ($dollars + ($cents / 100)),
                    'currency' => 'USD',
                    'shipping_cost' => 0, // Prime shipping is often free
                    'in_stock' => !str_contains($html, 'Currently unavailable'),
                    'stock_quantity' => null,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Amazon web scraping failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Scrape Etsy price
     */
    protected function scrapeEtsyPrice(CompetitorProduct $competitor): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->get($competitor->competitor_product_url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // Extract price from Etsy page
            if (preg_match('/<p[^>]*class="[^"]*wt-text-title-03[^"]*"[^>]*>.*?\$(\d+\.?\d*)<\/p>/i', $html, $matches)) {
                return [
                    'price' => (float) $matches[1],
                    'currency' => 'USD',
                    'shipping_cost' => 0, // Would need to extract from page
                    'in_stock' => !str_contains($html, 'Sold out'),
                    'stock_quantity' => null,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Etsy web scraping failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Scrape Walmart price
     */
    protected function scrapeWalmartPrice(CompetitorProduct $competitor): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->get($competitor->competitor_product_url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // Extract price from Walmart page
            if (preg_match('/<span[^>]*itemprop="price"[^>]*>(\d+\.?\d*)<\/span>/i', $html, $matches)) {
                return [
                    'price' => (float) $matches[1],
                    'currency' => 'USD',
                    'shipping_cost' => 0,
                    'in_stock' => !str_contains($html, 'Out of stock'),
                    'stock_quantity' => null,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Walmart web scraping failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Scrape Shopify store price
     */
    protected function scrapeShopifyPrice(CompetitorProduct $competitor): ?array
    {
        try {
            // Shopify stores have a .json endpoint for products
            $url = $competitor->competitor_product_url;

            // Try to get JSON data first
            if (str_contains($url, '/products/')) {
                $jsonUrl = preg_replace('/\?.*$/', '', $url) . '.json';

                $response = Http::get($jsonUrl);

                if ($response->successful()) {
                    $data = $response->json();
                    $product = $data['product'] ?? null;

                    if ($product && isset($product['variants'][0])) {
                        $variant = $product['variants'][0];

                        return [
                            'price' => (float) $variant['price'],
                            'currency' => 'USD', // Would need to determine from store
                            'shipping_cost' => 0,
                            'in_stock' => $variant['available'] ?? true,
                            'stock_quantity' => $variant['inventory_quantity'] ?? null,
                        ];
                    }
                }
            }

            // Fallback to web scraping
            $response = Http::get($url);

            if ($response->successful()) {
                $html = $response->body();

                if (preg_match('/<span[^>]*class="[^"]*price[^"]*"[^>]*>.*?\$(\d+\.?\d*)<\/span>/i', $html, $matches)) {
                    return [
                        'price' => (float) $matches[1],
                        'currency' => 'USD',
                        'shipping_cost' => 0,
                        'in_stock' => !str_contains($html, 'Sold out'),
                        'stock_quantity' => null,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Shopify web scraping failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Generic price scraper for custom URLs
     */
    protected function scrapeGenericPrice(CompetitorProduct $competitor): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->get($competitor->competitor_product_url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // Try common price patterns
            $patterns = [
                '/<span[^>]*class="[^"]*price[^"]*"[^>]*>.*?\$?(\d+\.?\d*)<\/span>/i',
                '/<div[^>]*class="[^"]*price[^"]*"[^>]*>.*?\$?(\d+\.?\d*)<\/div>/i',
                '/<p[^>]*class="[^"]*price[^"]*"[^>]*>.*?\$?(\d+\.?\d*)<\/p>/i',
                '/\$(\d+\.?\d*)/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    return [
                        'price' => (float) $matches[1],
                        'currency' => 'USD',
                        'shipping_cost' => 0,
                        'in_stock' => true,
                        'stock_quantity' => null,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Generic web scraping failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Record price in history
     */
    protected function recordPrice(CompetitorProduct $competitor, array $priceData): CompetitorPrice
    {
        $previousPrice = $competitor->priceHistory()->latest('scraped_at')->first();

        return CompetitorPrice::create([
            'competitor_product_id' => $competitor->id,
            'price' => $priceData['price'],
            'currency' => $priceData['currency'] ?? 'USD',
            'shipping_cost' => $priceData['shipping_cost'] ?? null,
            'total_cost' => $priceData['price'] + ($priceData['shipping_cost'] ?? 0),
            'in_stock' => $priceData['in_stock'] ?? true,
            'stock_quantity' => $priceData['stock_quantity'] ?? null,
            'previous_price' => $previousPrice?->price,
            'price_change' => $previousPrice
                ? $priceData['price'] - $previousPrice->price
                : 0,
            'price_change_percent' => $previousPrice && $previousPrice->price > 0
                ? (($priceData['price'] - $previousPrice->price) / $previousPrice->price) * 100
                : 0,
            'additional_data' => $priceData['additional_data'] ?? null,
            'scraped_at' => now(),
        ]);
    }

    /**
     * Batch scrape multiple competitors
     */
    public function batchScrape(array $competitorIds): array
    {
        $results = [];

        foreach ($competitorIds as $competitorId) {
            $competitor = CompetitorProduct::find($competitorId);

            if (!$competitor) {
                $results[$competitorId] = ['success' => false, 'error' => 'Competitor not found'];
                continue;
            }

            $priceData = $this->scrapePrice($competitor);

            $results[$competitorId] = [
                'success' => $priceData !== null,
                'price_data' => $priceData,
            ];

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 second delay
        }

        return $results;
    }
}
