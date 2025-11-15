<?php

namespace App\Observers;

use App\Models\ChannelOrder;
use App\Events\NewSaleEvent;

class ChannelOrderObserver
{
    /**
     * Handle the ChannelOrder "created" event.
     */
    public function created(ChannelOrder $order): void
    {
        // Broadcast new sale event for real-time dashboard
        NewSaleEvent::fromChannelOrder($order)->dispatch();
    }
}
