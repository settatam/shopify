<?php

namespace App\Services;

use App\Models\Product;

class MediaService
{
    /** Return up to $limit HTTPS image URLs (deduped) for product */
    public static function productImages(Product $p, int $limit = 12): array
    {
        $list = is_array($p->images_json) ? $p->images_json : [];
        $clean = array_values(array_unique(array_filter($list, fn($u) => is_string($u) && str_starts_with($u, 'http'))));
        return array_slice($clean, 0, $limit);
    }

}
