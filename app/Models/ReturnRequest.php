<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'channel_order_id',
        'rma_number',
        'order_number',
        'channel_id',
        'external_order_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'return_reason',
        'return_reason_details',
        'images',
        'status',
        'return_type',
        'items_subtotal',
        'shipping_paid',
        'tax_paid',
        'total_paid',
        'refund_amount',
        'restocking_fee',
        'refund_shipping',
        'requires_approval',
        'approved_by_id',
        'approved_at',
        'rejected_by_id',
        'rejected_at',
        'rejection_reason',
        'return_address',
        'return_carrier',
        'return_tracking_number',
        'return_label_url',
        'return_shipping_cost',
        'customer_pays_return_shipping',
        'inspected_by_id',
        'inspected_at',
        'inspection_notes',
        'inspection_result',
        'completed_by_id',
        'completed_at',
        'return_by_date',
        'received_at',
        'customer_notified_at',
        'notification_history',
        'custom_fields',
        'internal_notes',
    ];

    protected $casts = [
        'images' => 'array',
        'notification_history' => 'array',
        'custom_fields' => 'array',
        'refund_shipping' => 'boolean',
        'requires_approval' => 'boolean',
        'customer_pays_return_shipping' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'inspected_at' => 'datetime',
        'completed_at' => 'datetime',
        'return_by_date' => 'datetime',
        'received_at' => 'datetime',
        'customer_notified_at' => 'datetime',
        'items_subtotal' => 'decimal:2',
        'shipping_paid' => 'decimal:2',
        'tax_paid' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'restocking_fee' => 'decimal:2',
        'return_shipping_cost' => 'decimal:2',
    ];

    /**
     * Get the shop that owns the return request.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the channel order.
     */
    public function channelOrder(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class);
    }

    /**
     * Get the channel.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the items being returned.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    /**
     * Get the refund for this return.
     */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    /**
     * Get the return shipping information.
     */
    public function shipping(): HasOne
    {
        return $this->hasOne(ReturnShipping::class);
    }

    /**
     * Get the user who approved the return.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * Get the user who rejected the return.
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    /**
     * Get the user who inspected the return.
     */
    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_id');
    }

    /**
     * Get the user who completed the return.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /**
     * Generate a unique RMA number.
     */
    public static function generateRmaNumber(): string
    {
        do {
            $rmaNumber = 'RMA-' . strtoupper(substr(uniqid(), -8));
        } while (static::where('rma_number', $rmaNumber)->exists());

        return $rmaNumber;
    }

    /**
     * Approve the return request.
     */
    public function approve(User $user): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by_id' => $user->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject the return request.
     */
    public function reject(User $user, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by_id' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Mark the return as received.
     */
    public function markReceived(): void
    {
        $this->update([
            'status' => 'received',
            'received_at' => now(),
        ]);
    }

    /**
     * Start inspection process.
     */
    public function startInspection(User $user): void
    {
        $this->update([
            'status' => 'inspecting',
            'inspected_by_id' => $user->id,
            'inspected_at' => now(),
        ]);
    }

    /**
     * Complete the inspection.
     */
    public function completeInspection(string $result, ?string $notes = null): void
    {
        $this->update([
            'inspection_result' => $result,
            'inspection_notes' => $notes,
        ]);
    }

    /**
     * Mark the return as completed.
     */
    public function markCompleted(User $user): void
    {
        $this->update([
            'status' => 'completed',
            'completed_by_id' => $user->id,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if return is overdue.
     */
    public function isOverdue(): bool
    {
        if (!$this->return_by_date) {
            return false;
        }

        return now()->isAfter($this->return_by_date) &&
               !in_array($this->status, ['received', 'completed', 'cancelled']);
    }

    /**
     * Check if return can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'pending_approval';
    }

    /**
     * Check if return can be rejected.
     */
    public function canBeRejected(): bool
    {
        return in_array($this->status, ['requested', 'pending_approval']);
    }

    /**
     * Check if return requires inspection.
     */
    public function requiresInspection(): bool
    {
        return in_array($this->return_reason, ['defective', 'wrong_item', 'damaged_in_shipping', 'quality_issue']);
    }

    /**
     * Calculate total refund amount.
     */
    public function calculateRefundAmount(): float
    {
        $amount = $this->items_subtotal;

        if ($this->refund_shipping) {
            $amount += $this->shipping_paid;
        }

        $amount += $this->tax_paid;
        $amount -= $this->restocking_fee;

        if (!$this->customer_pays_return_shipping && $this->return_shipping_cost) {
            $amount -= $this->return_shipping_cost;
        }

        return max(0, $amount);
    }

    /**
     * Get the total items count.
     */
    public function getTotalItemsCount(): int
    {
        return $this->items()->sum('quantity_returned');
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
     * Get status label with color.
     */
    public function getStatusInfo(): array
    {
        return match($this->status) {
            'requested' => ['label' => 'Requested', 'color' => 'blue'],
            'pending_approval' => ['label' => 'Pending Approval', 'color' => 'yellow'],
            'approved' => ['label' => 'Approved', 'color' => 'green'],
            'rejected' => ['label' => 'Rejected', 'color' => 'red'],
            'label_generated' => ['label' => 'Label Generated', 'color' => 'purple'],
            'in_transit' => ['label' => 'In Transit', 'color' => 'blue'],
            'received' => ['label' => 'Received', 'color' => 'indigo'],
            'inspecting' => ['label' => 'Inspecting', 'color' => 'orange'],
            'completed' => ['label' => 'Completed', 'color' => 'green'],
            'cancelled' => ['label' => 'Cancelled', 'color' => 'gray'],
            default => ['label' => ucfirst($this->status), 'color' => 'gray'],
        };
    }
}
