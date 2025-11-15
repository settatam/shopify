<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    //
    public function show(Product $product)
    {
        $this->authorize('view', $product);

        $product->load(['variants' => function($q){ $q->orderBy('id'); }]);
        return response()->json([
            'id' => $product->id,
            'shopify_product_id' => $product->shopify_product_id,
            'title' => $product->title,
            'description' => $product->description,
            'images' => $product->images_json ?? [],
            'vendor' => $product->vendor,
            'brand' => $product->brand,
            'variants' => $product->variants->map(fn($v)=>[
                'id' => $v->id,
                'shopify_variant_id' => $v->shopify_variant_id,
                'sku' => $v->sku,
                'option1' => $v->option1,
                'option2' => $v->option2,
                'option3' => $v->option3,
                'price' => $v->price,
                'quantity' => $v->quantity,
                'barcode' => $v->barcode,
            ])->all()
        ]);
    }
}
