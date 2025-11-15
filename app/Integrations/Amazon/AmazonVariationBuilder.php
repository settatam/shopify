<?php

namespace App\Integrations\Amazon;
use App\Models\{Product, ProductVariant};

class AmazonVariationBuilder
{

    public static function themeToAspects(string $theme): array
    {
        return match ($theme) {
            'SizeColor' => ['Size','Color'],
            'ColorSize' => ['Color','Size'],
            'Size' => ['Size'],
            'Color' => ['Color'],
            default => ['Size','Color']
        };
    }

    /** Build a stable parent SKU; override if you prefer vendor style */
    public static function parentSku(Product $p, int $channelId): string
    {
        return 'PARENT-'.$channelId.'-'.$p->id;
    }

    /** Map Shopify variant option values to (size,color) according to theme order */
    public static function extractOptionValues(ProductVariant $v, string $theme): array
    {
        $aspects = self::themeToAspects($theme);
        $vals = [];
        foreach ($aspects as $idx => $name) {
            $vals[$name] = match ($idx) { 0 => $v->option1, 1 => $v->option2, 2 => $v->option3, default => null };
        }
        return [
            'size' => $vals['Size'] ?? null,
            'color' => $vals['Color'] ?? null,
        ];
    }
}
