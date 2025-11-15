<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\VariantChannelPricing;
use http\Exception\InvalidArgumentException;

class PricingService
{
    /** Update base pricing on the variant (min/max/MSRP). */
    public function setBasePricing(ProductVariant $variant, ?float $min, ?float $max, ?float $msrp): ProductVariant
    {
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('price_min cannot be greater than price_max');
        }
        if ($min !== null) $variant->price_min = round($min, 2);
        if ($max !== null) $variant->price_max = round($max, 2);
        if ($msrp !== null) $variant->msrp = round($msrp, 2);
        $variant->save();
        return $variant->refresh();
    }

    /** Upsert a channel override; null values mean "fallback to base". */
    public function setChannelOverride(int $channelId, ProductVariant $variant, array $data): VariantChannelPricing
    {
        $payload = [];
        foreach (['price_min','price_max','msrp'] as $k) {
            if (array_key_exists($k, $data)) {
                $payload[$k] = $data[$k] !== null ? round((float)$data[$k], 2) : null;
            }
        }
        if (($payload['price_min'] ?? null) !== null && ($payload['price_max'] ?? null) !== null) {
            if ($payload['price_min'] > $payload['price_max']) {
                throw new InvalidArgumentException('Override price_min cannot be greater than price_max');
            }
        }
        return VariantChannelPricing::updateOrCreate(
            ['variant_id' => $variant->id, 'channel_id' => $channelId],
            $payload
        );
    }

    /** Compute effective pricing using an override row if present. */
    public function getEffectivePricing(?VariantChannelPricing $override, ProductVariant $variant): array
    {
        return [
            'price_min' => $override->price_min ?? $variant->price_min,
            'price_max' => $override->price_max ?? $variant->price_max,
            'msrp' => $override->msrp ?? $variant->msrp,
        ];
    }
}
