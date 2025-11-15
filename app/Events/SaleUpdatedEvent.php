<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $shopId;
    public $saleId;
    public $saleType;
    public $updates;

    /**
     * Create a new event instance.
     */
    public function __construct(int $shopId, int $saleId, string $saleType, array $updates)
    {
        $this->shopId = $shopId;
        $this->saleId = $saleId;
        $this->saleType = $saleType; // 'channel_order' or 'pos_transaction'
        $this->updates = $updates;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sales.' . $this->shopId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sale.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'sale_id' => $this->saleId,
            'sale_type' => $this->saleType,
            'updates' => $this->updates,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
