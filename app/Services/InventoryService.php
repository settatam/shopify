<?php

namespace App\Services;

use App\Models\{Shop, Product, ProductVariant, StockItem, StockLedger};
use Illuminate\Support\Facades\DB;
use App\Jobs\SyncInventoryJob;

class InventoryService
{
    /**
     * Adjust on-hand for a variant at a location.
     * $event: receive|ship|return|adjust|transfer_in|transfer_out
     */
    public function adjust(int $shopId, int $variantId, int $locationId, int $qty, ?float $unitCost = null, string $reason = 'adjust', array $meta = []): StockItem
    {
        if ($qty === 0) return $this->getOrCreate($shopId, $variantId, $locationId);


        return DB::transaction(function () use ($shopId,$variantId,$locationId,$qty,$unitCost,$reason,$meta) {
            $item = $this->getOrCreate($shopId, $variantId, $locationId, lock: true);
            $newOnHand = $item->on_hand + $qty;
            if ($newOnHand < 0) throw new InvalidArgumentException('Insufficient on-hand for adjustment.');


// Moving Average Cost update on positive receipt only
            if ($unitCost !== null && $qty > 0) {
                $totalCostBefore = $item->on_hand * (float)$item->avg_cost;
                $totalCostAfter = $totalCostBefore + ($qty * $unitCost);
                $newQty = max($item->on_hand + $qty, 0);
                $item->avg_cost = $newQty > 0 ? round($totalCostAfter / $newQty, 4) : 0;
            }


            $item->on_hand = $newOnHand;
            $item->save();


            $this->log($shopId,$variantId,$locationId,$reason,$qty,$unitCost,$item->avg_cost,$meta);
            return $item->refresh();
        });
    }

    /** Reserve available stock for an open order */
    public function reserve(int $shopId, int $variantId, int $locationId, int $qty, string $referenceType = 'order', string $referenceId = '', array $meta = []): StockItem
    {
        return DB::transaction(function () use ($shopId,$variantId,$locationId,$qty,$referenceType,$referenceId,$meta) {
            $item = $this->getOrCreate($shopId, $variantId, $locationId, lock: true);
            if (($item->on_hand - $item->reserved) < $qty) {
                throw new InvalidArgumentException('Insufficient available to reserve.');
            }
            $item->reserved += $qty;
            $item->save();
            $this->log($shopId,$variantId,$locationId,'reserve',$qty,null,$item->avg_cost, $meta + compact('referenceType','referenceId'));
            return $item->refresh();
        });
    }



    /** Release a previous reservation */
    public function release(int $shopId, int $variantId, int $locationId, int $qty, string $referenceType = 'order', string $referenceId = '', array $meta = []): StockItem
    {
        return DB::transaction(function () use ($shopId,$variantId,$locationId,$qty,$referenceType,$referenceId,$meta) {
            $item = $this->getOrCreate($shopId, $variantId, $locationId, lock: true);
            if ($item->reserved < $qty) throw new InvalidArgumentException('Insufficient reserved to release.');
            $item->reserved -= $qty;
            $item->save();
            $this->log($shopId,$variantId,$locationId,'release',-$qty,null,$item->avg_cost, $meta + compact('referenceType','referenceId'));
            return $item->refresh();
        });
    }

    public function transfer(int $shopId, int $variantId, int $fromLocationId, int $toLocationId, int $qty, array $meta = []): void
    {
        if ($qty <= 0) throw new InvalidArgumentException('Transfer qty must be > 0');
        DB::transaction(function () use ($shopId,$variantId,$fromLocationId,$toLocationId,$qty,$meta) {
            $from = $this->getOrCreate($shopId, $variantId, $fromLocationId, lock: true);
            if ($from->on_hand < $qty) throw new InvalidArgumentException('Insufficient on-hand to transfer.');


            $to = $this->getOrCreate($shopId, $variantId, $toLocationId, lock: true);


// Decrement source
            $from->on_hand -= $qty; $from->save();
            $this->log($shopId,$variantId,$fromLocationId,'transfer_out',-$qty,$from->avg_cost,$from->avg_cost,$meta);


// Increment destination at source avg cost; update MAV at dest
            $unitCost = (float)$from->avg_cost;
            $totalCostBefore = $to->on_hand * (float)$to->avg_cost;
            $totalCostAfter = $totalCostBefore + ($qty * $unitCost);
            $newQty = $to->on_hand + $qty;
            $to->on_hand = $newQty;
            $to->avg_cost = $newQty > 0 ? round($totalCostAfter / $newQty, 4) : $unitCost;
            $to->save();
            $this->log($shopId,$variantId,$toLocationId,'transfer_in',$qty,$unitCost,$to->avg_cost,$meta);
        });
    }

    protected function getOrCreate(int $shopId, int $variantId, int $locationId, bool $lock = false): StockItem
    {
        $q = StockItem::query()->where(compact('shop_id','variant_id','location_id'));
        if ($lock) $q->lockForUpdate();
        $item = $q->first();
        if ($item) return $item;
        return StockItem::create([
            'shop_id' => $shopId,
            'variant_id' => $variantId,
            'location_id' => $locationId,
        ]);
    }

    protected function log(int $shopId, int $variantId, int $locationId, string $type, int $qty, ?float $unitCost, ?float $avgAfter, array $meta = []): void
    {
        StockLedger::create([
            'shop_id' => $shopId,
            'variant_id' => $variantId,
            'location_id' => $locationId,
            'type' => $type,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'avg_cost_after' => $avgAfter,
            'meta' => $meta,
        ]);
    }

    /** Aggregate available across all locations (on_hand − reserved). */
    public function aggregateAvailable(int $variantId): int
    {
        $sum = DB::table('stock_items')
            ->where('product_variant_id', $variantId)
            ->selectRaw('SUM(on_hand - reserved) as avail')
            ->value('avail');

        return (int) ($sum ?? 0);
    }

    /**
     * Build a normalized physical snapshot + identifiers and pricing bounds.
     * Prefers variant fields; falls back to product package defaults.
     */
    public function effectivePhysical(Product $product, ProductVariant $variant, string $weightTo = 'kg', string $dimTo = 'cm'): array
    {
        // Weight
        $w  = $variant->weight ?? $product->package_weight;
        $wu = $variant->weight_unit ?? $product->package_weight_unit; // kg|g|lb|oz
        $normWeight = $this->convertWeight($w, $wu, $weightTo);

        // Dims
        $len = $variant->length ?? $product->package_length;
        $wid = $variant->width  ?? $product->package_width;
        $hei = $variant->height ?? $product->package_height;
        $du  = $variant->dim_unit ?? $product->package_dim_unit; // cm|in
        [$nL, $nW, $nH] = $this->convertDims([$len, $wid, $hei], $du, $dimTo);

        return [
            'weight' => $normWeight, 'weight_unit' => $weightTo,
            'length' => $nL, 'width' => $nW, 'height' => $nH, 'dim_unit' => $dimTo,
            'fulfillment_latency' => $variant->fulfillment_latency,
            'identifiers' => [
                'sku'       => $variant->sku,
                'mpn'       => $variant->mpn,
                'gtin'      => $variant->gtin,
                'gtin_type' => $variant->gtin_type,
                'isbn'      => $variant->isbn,
            ],
            'pricing_bounds' => [
                'min'  => $variant->min_price,
                'max'  => $variant->max_price,
                'msrp' => $variant->msrp ?? $product->msrp,
            ],
        ];
    }

    // --- unit conversion helpers ---

    private function convertWeight(?float $value, ?string $from, string $to): ?float
    {
        if ($value === null || !$from) return $value;
        $from = strtolower($from); $to = strtolower($to);

        $kg = match ($from) {
            'kg' => $value,
            'g'  => $value / 1000,
            'lb' => $value * 0.45359237,
            'oz' => $value * 0.028349523125,
            default => $value,
        };

        return match ($to) {
            'kg' => $kg,
            'g'  => $kg * 1000,
            'lb' => $kg / 0.45359237,
            'oz' => $kg / 0.028349523125,
            default => $kg,
        };
    }

    /** @param array{0:?float,1:?float,2:?float} $dims */
    private function convertDims(array $dims, ?string $from, string $to): array
    {
        [$L, $W, $H] = $dims;
        if ($from === null) return [$L, $W, $H];

        $from = strtolower($from); $to = strtolower($to);

        $cmFactor = match ($from) { 'cm' => 1.0, 'in' => 2.54, default => 1.0 };
        $Lcm = $L !== null ? $L * $cmFactor : null;
        $Wcm = $W !== null ? $W * $cmFactor : null;
        $Hcm = $H !== null ? $H * $cmFactor : null;

        $outFactor = match ($to) { 'cm' => 1.0, 'in' => 1 / 2.54, default => 1.0 };
        return [
            $Lcm !== null ? $Lcm * $outFactor : null,
            $Wcm !== null ? $Wcm * $outFactor : null,
            $Hcm !== null ? $Hcm * $outFactor : null,
        ];
    }
}
