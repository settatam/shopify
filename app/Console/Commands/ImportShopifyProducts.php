<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Console\Command;

class ImportShopifyProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:import-products {shopId} {--since=}';
    protected $description = 'Import products/variants from Shopify for a shop';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shop = Shop::findOrFail($this->argument('shopId'));
        $client = new ShopifyClient($shop);


        $params = ['limit' => 250, 'fields' => 'id,title,body_html,variants,options,images,vendor'];
        if ($since = $this->option('since')) $params['updated_at_min'] = $since;


        $page = 1; $count = 0;
        do {
            $products = $client->products($params + ['page' => $page]);
            foreach ($products as $p) {
                $prod = Product::updateOrCreate(
                    ['shop_id' => $shop->id, 'shopify_product_id' => $p['id']],
                    [
                        'title' => $p['title'] ?? '',
                        'description' => $p['body_html'] ?? '',
                        'vendor' => $p['vendor'] ?? null,
                        'brand' => $p['vendor'] ?? null,
                        'images_json' => collect($p['images'] ?? [])->pluck('src')->take(20)->values()->all(),
                        'status' => 'active'
                    ]
                );


                foreach ($p['variants'] ?? [] as $v) {
                    ProductVariant::updateOrCreate(
                        ['product_id' => $prod->id, 'shopify_variant_id' => $v['id']],
                        [
                            'sku' => $v['sku'] ?? null,
                            'barcode' => $v['barcode'] ?? null,
                            'price' => $v['price'] ?? null,
                            'compare_at_price' => $v['compare_at_price'] ?? null,
                            'quantity' => $v['inventory_quantity'] ?? 0,
                            'weight' => $v['weight'] ?? null,
                            'weight_unit' => $v['weight_unit'] ?? null,
                            'option1' => $v['option1'] ?? null,
                            'option2' => $v['option2'] ?? null,
                            'option3' => $v['option3'] ?? null,
                        ]
                    );
                }
                $count++;
            }
            $page++;
        } while (!empty($products));


        $this->info("Imported/updated {$count} products");
    }
}
