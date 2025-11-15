<?php

namespace App\Events;

use App\Models\ChannelOrder;
use App\Models\PosTransaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSaleEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $shopId;
    public $saleData;

    /**
     * Create a new event instance.
     */
    public function __construct(int $shopId, array $saleData)
    {
        $this->shopId = $shopId;
        $this->saleData = $saleData;
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
        return 'new.sale';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'sale' => $this->saleData,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Create event from ChannelOrder
     */
    public static function fromChannelOrder(ChannelOrder $order): self
    {
        $saleData = [
            'id' => $order->id,
            'type' => 'channel_order',
            'source' => $order->channel?->channel_type ?? 'unknown',
            'order_number' => $order->order_number,
            'total' => (float) $order->total_amount,
            'currency' => $order->currency ?? 'USD',
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'status' => $order->status,
            'fulfillment_status' => $order->fulfillment_status,
            'payment_status' => $order->payment_status,
            'items_count' => count($order->items ?? []),
            'created_at' => $order->created_at->toIso8601String(),
        ];

        return new self($order->shop_id, $saleData);
    }

    /**
     * Create event from PosTransaction
     */
    public static function fromPosTransaction(PosTransaction $transaction): self
    {
        $saleData = [
            'id' => $transaction->id,
            'type' => 'pos_transaction',
            'source' => 'POS',
            'order_number' => $transaction->transaction_number,
            'total' => (float) $transaction->total,
            'currency' => 'USD',
            'customer_name' => $transaction->customer_name,
            'customer_email' => $transaction->customer_email,
            'status' => $transaction->status,
            'fulfillment_status' => 'fulfilled', // POS transactions are immediately fulfilled
            'payment_status' => 'paid',
            'payment_method' => $transaction->payment_method,
            'items_count' => count($transaction->line_items ?? []),
            'created_at' => $transaction->created_at->toIso8601String(),
        ];

        return new self($transaction->shop_id, $saleData);
    }
}
