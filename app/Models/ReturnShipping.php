<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnShipping extends Model
{
    use HasFactory;

    protected $table = 'return_shipping';

    protected $fillable = [
        'return_request_id',
        'shop_id',
        'provider',
        'label_id',
        'tracking_number',
        'carrier_code',
        'service_code',
        'label_url',
        'label_pdf_url',
        'label_base64',
        'label_cost',
        'insurance_cost',
        'total_cost',
        'customer_pays',
        'weight_oz',
        'length_in',
        'width_in',
        'height_in',
        'from_address',
        'to_address',
        'status',
        'tracking_events',
        'last_tracking_update',
        'estimated_delivery_date',
        'actual_delivery_date',
        'label_generated_at',
        'generated_by_id',
        'label_printed',
        'label_printed_at',
        'voided',
        'voided_at',
        'voided_by_id',
        'void_reason',
        'provider_response',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'from_address' => 'array',
        'to_address' => 'array',
        'tracking_events' => 'array',
        'provider_response' => 'array',
        'metadata' => 'array',
        'customer_pays' => 'boolean',
        'label_printed' => 'boolean',
        'voided' => 'boolean',
        'last_tracking_update' => 'datetime',
        'estimated_delivery_date' => 'datetime',
        'actual_delivery_date' => 'datetime',
        'label_generated_at' => 'datetime',
        'label_printed_at' => 'datetime',
        'voided_at' => 'datetime',
        'label_cost' => 'decimal:2',
        'insurance_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'weight_oz' => 'decimal:2',
        'length_in' => 'decimal:2',
        'width_in' => 'decimal:2',
        'height_in' => 'decimal:2',
    ];

    /**
     * Get the return request.
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /**
     * Get the shop.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the user who generated the label.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_id');
    }

    /**
     * Get the user who voided the label.
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_id');
    }

    /**
     * Mark label as printed.
     */
    public function markPrinted(): void
    {
        $this->update([
            'label_printed' => true,
            'label_printed_at' => now(),
        ]);
    }

    /**
     * Update tracking status.
     */
    public function updateTracking(string $status, ?array $events = null): void
    {
        $update = [
            'status' => $status,
            'last_tracking_update' => now(),
        ];

        if ($events) {
            $update['tracking_events'] = $events;
        }

        if ($status === 'delivered') {
            $update['actual_delivery_date'] = now();
        }

        $this->update($update);
    }

    /**
     * Add tracking event.
     */
    public function addTrackingEvent(array $event): void
    {
        $events = $this->tracking_events ?? [];
        $events[] = array_merge($event, ['timestamp' => now()->toIso8601String()]);

        $this->update([
            'tracking_events' => $events,
            'last_tracking_update' => now(),
        ]);
    }

    /**
     * Void the shipping label.
     */
    public function void(User $user, ?string $reason = null): void
    {
        $this->update([
            'voided' => true,
            'voided_at' => now(),
            'voided_by_id' => $user->id,
            'void_reason' => $reason,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Check if label can be voided.
     */
    public function canBeVoided(): bool
    {
        return !$this->voided &&
               !in_array($this->status, ['delivered', 'cancelled']) &&
               $this->label_id;
    }

    /**
     * Check if label is active.
     */
    public function isActive(): bool
    {
        return !$this->voided &&
               in_array($this->status, ['label_created', 'in_transit', 'out_for_delivery']);
    }

    /**
     * Get carrier name.
     */
    public function getCarrierName(): string
    {
        return match($this->carrier_code) {
            'ups' => 'UPS',
            'usps' => 'USPS',
            'fedex' => 'FedEx',
            'dhl' => 'DHL',
            'canada_post' => 'Canada Post',
            'royal_mail' => 'Royal Mail',
            default => strtoupper($this->carrier_code ?? 'Unknown'),
        };
    }

    /**
     * Get status label with color.
     */
    public function getStatusInfo(): array
    {
        return match($this->status) {
            'label_created' => ['label' => 'Label Created', 'color' => 'blue'],
            'in_transit' => ['label' => 'In Transit', 'color' => 'yellow'],
            'out_for_delivery' => ['label' => 'Out for Delivery', 'color' => 'orange'],
            'delivered' => ['label' => 'Delivered', 'color' => 'green'],
            'failed_delivery' => ['label' => 'Failed Delivery', 'color' => 'red'],
            'returned_to_sender' => ['label' => 'Returned to Sender', 'color' => 'purple'],
            'cancelled' => ['label' => 'Cancelled', 'color' => 'gray'],
            default => ['label' => ucfirst($this->status), 'color' => 'gray'],
        };
    }

    /**
     * Get tracking URL for carrier.
     */
    public function getTrackingUrl(): ?string
    {
        if (!$this->tracking_number) {
            return null;
        }

        return match($this->carrier_code) {
            'ups' => "https://www.ups.com/track?tracknum={$this->tracking_number}",
            'usps' => "https://tools.usps.com/go/TrackConfirmAction?tLabels={$this->tracking_number}",
            'fedex' => "https://www.fedex.com/fedextrack/?tracknumbers={$this->tracking_number}",
            'dhl' => "https://www.dhl.com/en/express/tracking.html?AWB={$this->tracking_number}",
            default => null,
        };
    }
}
