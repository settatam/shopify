<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_request_id',
        'product_id',
        'variant_id',
        'channel_order_item_id',
        'sku',
        'product_name',
        'variant_name',
        'quantity_ordered',
        'quantity_returned',
        'unit_price',
        'total_price',
        'tax_amount',
        'return_reason',
        'return_reason_details',
        'condition_received',
        'inspection_notes',
        'disposition',
        'restocked',
        'restocked_at',
        'restocked_by_id',
        'location_id',
        'refundable',
        'refund_amount',
        'restocking_fee',
        'refund_notes',
        'exchange_variant_id',
        'exchange_quantity',
        'exchange_fulfilled',
        'exchange_fulfilled_at',
        'images',
        'custom_fields',
    ];

    protected $casts = [
        'images' => 'array',
        'custom_fields' => 'array',
        'restocked' => 'boolean',
        'restocked_at' => 'datetime',
        'refundable' => 'boolean',
        'exchange_fulfilled' => 'boolean',
        'exchange_fulfilled_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'restocking_fee' => 'decimal:2',
    ];

    /**
     * Get the return request.
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * Get the channel order item.
     */
    public function channelOrderItem(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderItem::class);
    }

    /**
     * Get the user who restocked the item.
     */
    public function restockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restocked_by_id');
    }

    /**
     * Get the location where item was restocked.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the exchange variant.
     */
    public function exchangeVariant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'exchange_variant_id');
    }

    /**
     * Mark item as restocked.
     */
    public function markRestocked(User $user, Location $location): void
    {
        $this->update([
            'disposition' => 'restock',
            'restocked' => true,
            'restocked_at' => now(),
            'restocked_by_id' => $user->id,
            'location_id' => $location->id,
        ]);
    }

    /**
     * Set disposition.
     */
    public function setDisposition(string $disposition, ?string $notes = null): void
    {
        $update = ['disposition' => $disposition];

        if ($notes) {
            $update['inspection_notes'] = $notes;
        }

        $this->update($update);
    }

    /**
     * Calculate refund amount for this item.
     */
    public function calculateRefundAmount(): float
    {
        if (!$this->refundable) {
            return 0;
        }

        $amount = $this->total_price + $this->tax_amount;
        $amount -= $this->restocking_fee;

        return max(0, $amount);
    }

    /**
     * Check if item can be restocked.
     */
    public function canBeRestocked(): bool
    {
        return in_array($this->condition_received, ['new_unopened', 'new_opened', 'lightly_used']) &&
               $this->disposition === 'restock' &&
               !$this->restocked;
    }

    /**
     * Check if item requires inspection.
     */
    public function requiresInspection(): bool
    {
        return in_array($this->return_reason, ['defective', 'wrong_item', 'damaged_in_shipping', 'quality_issue']);
    }

    /**
     * Get return reason label.
     */
    public function getReturnReasonLabel(): string
    {
        return match($this->return_reason) {
            'defective' => 'Defective Product',
            'wrong_item' => 'Wrong Item Received',
            'not_as_described' => 'Not As Described',
            'damaged_in_shipping' => 'Damaged in Shipping',
            'changed_mind' => 'Changed Mind',
            'size_fit_issue' => 'Size/Fit Issue',
            'quality_issue' => 'Quality Issue',
            'missing_parts' => 'Missing Parts',
            'arrived_late' => 'Arrived Late',
            'duplicate_order' => 'Duplicate Order',
            default => 'Other',
        };
    }

    /**
     * Get condition label.
     */
    public function getConditionLabel(): string
    {
        return match($this->condition_received) {
            'new_unopened' => 'New - Unopened',
            'new_opened' => 'New - Opened',
            'lightly_used' => 'Lightly Used',
            'heavily_used' => 'Heavily Used',
            'damaged' => 'Damaged',
            'defective' => 'Defective',
            'missing_parts' => 'Missing Parts',
            default => 'Unknown',
        };
    }

    /**
     * Get disposition label.
     */
    public function getDispositionLabel(): string
    {
        return match($this->disposition) {
            'restock' => 'Restock',
            'restock_as_used' => 'Restock as Used',
            'refurbish' => 'Send for Refurbishing',
            'dispose' => 'Dispose',
            'return_to_vendor' => 'Return to Vendor',
            'quarantine' => 'Quarantine',
            'pending' => 'Pending Decision',
            default => ucfirst($this->disposition),
        };
    }
}
