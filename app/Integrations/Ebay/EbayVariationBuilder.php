<?php

namespace App\Integrations\Ebay;

use App\Models\{Product, ProductVariant};
class EbayVariationBuilder
{
    /**
     * Build variesBy + variantSKUs + common aspects from Shopify options.
     * $theme like "SizeColor" determines which options are pivoting aspects.
     */
    public static function build(Product $p, string $theme = 'SizeColor'): array
    {
// Map theme to aspect names in order
        $map = [
            'SizeColor' => ['Size','Color'],
            'ColorSize' => ['Color','Size'],
            'Size' => ['Size'],
            'Color' => ['Color'],
            'MaterialSize' => ['Material','Size']
        ];
        $aspects = $map[$theme] ?? ['Size','Color'];


        // Collect values per aspect
        $values = [];
        foreach ($aspects as $idx => $name) { $values[$name] = []; }

        $variantSKUs = [];
        foreach ($p->variants as $v) {
            $sku = $v->sku ?: (string)$v->shopify_variant_id;
            $variantSKUs[] = $sku;
            $optVals = [];
            foreach ($aspects as $i => $name) {
                $val = match ($i) {
                    0 => $v->option1,
                    1 => $v->option2,
                    2 => $v->option3,
                };
                if ($val !== null && $val !== '') {
                    $values[$name][] = (string)$val;
                }
            }
        }


// Unique + preserve order
        foreach ($values as $k => $arr) {
            $values[$k] = array_values(array_unique(array_filter($arr, fn($x) => $x !== null && $x !== '')));
        }


// Build variesBy.specifications
        $specs = [];
        foreach ($aspects as $name) {
            if (!empty($values[$name])) {
                $specs[] = [ 'name' => $name, 'values' => $values[$name] ];
            }
        }


        return [
            'aspects' => $aspects,
            'variesBy' => [ 'specifications' => $specs ],
            'variantSKUs' => array_values(array_unique($variantSKUs)),
        ];
    }
}
