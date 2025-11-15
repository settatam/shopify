<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia; use Inertia\Response;
use App\Models\{Product, Channel};
use App\Integrations\Amazon\{AmazonClient, AmazonAuthService, AmazonDefinitionsService};
use Illuminate\Http\Request;

class InertiaAmazonAttributePickerController extends Controller
{
    //
    public function edit(Product $product, Channel $channel): Response
    {
        $mapping = app('App\\Services\\MappingService')::for($channel, $product)->compose();
        $ptype = (string)($mapping['category'] ?? '');
        $defs = $ptype ? app()->makeWith(AmazonDefinitionsService::class, [
            'client' => app()->makeWith(AmazonClient::class, ['channel' => $channel, 'authSvc' => app(AmazonAuthService::class)]),
            'channel' => $channel
        ])->listAttributes($ptype) : ['required'=>[], 'optional'=>[], 'flat'=>[]];


        return Inertia::render('Amazon/AttributePicker', [
            'product' => [ 'id'=>$product->id, 'title'=>$product->title, 'shopify_product_id'=>$product->shopify_product_id ],
            'channel' => [ 'id'=>$channel->id, 'name'=>$channel->name ],
            'productType' => $ptype,
            'attributes' => $defs,
            'mapping' => $mapping['amazon'] ?? [
                    'variation_theme_attr' => 'variationTheme',
                    'parentage_attr' => 'parentage',
                    'relationship_type_attr' => 'relationship_type',
                    'parent_sku_attr' => 'parent_sku',
                    'size_attr' => 'size_name', 'color_attr' => 'color_name',
                    'image' => [ 'main' => 'main_image', 'other_prefix' => 'other_image_url', 'max_others' => 8 ],
                    'attributes' => new \stdClass()
                ],
        ]);
    }

    public function update(Request $r, Product $product, Channel $channel)
    {
        $data = $r->validate([
            'amazon' => 'required|array',
            'amazon.variation_theme_attr' => 'required|string',
            'amazon.parentage_attr' => 'required|string',
            'amazon.relationship_type_attr' => 'required|string',
            'amazon.parent_sku_attr' => 'required|string',
            'amazon.size_attr' => 'nullable|string',
            'amazon.color_attr' => 'nullable|string',
            'amazon.image' => 'required|array',
            'amazon.image.main' => 'required|string',
            'amazon.image.other_prefix' => 'required|string',
            'amazon.image.max_others' => 'integer|min:0|max:20',
            'amazon.attributes' => 'array',
        ]);


        $svc = app('App\\Services\\MappingService')::for($channel, $product);
        $current = $svc->compose();
        $current['amazon'] = array_replace_recursive($current['amazon'] ?? [], $data['amazon']);
        $svc->save($current); // implement save on your MappingService as needed


        return back()->with('success', 'Amazon attribute mapping saved');
    }
}
