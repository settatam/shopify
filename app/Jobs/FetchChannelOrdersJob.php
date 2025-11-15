<?php

namespace App\Jobs;

use App\Integrations\ChannelRegistry;
use App\Models\{Channel, ChannelOrder, ChannelOrderItem};
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class FetchChannelOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public int $channelId, public ?string $sinceIso = null) {}


    public function handle(ChannelRegistry $registry)
    {
        $channel = Channel::findOrFail($this->channelId);
        $since = $this->sinceIso ? CarbonImmutable::parse($this->sinceIso) : now()->subMinutes(30);

        foreach ($registry->for($channel)->fetchOrders($since) as $ord) {
            $co = ChannelOrder::updateOrCreate(
                ['channel_id' => $channel->id, 'external_order_id' => $ord['id']],
                [
                    'marketplace_status' => $ord['status'] ?? null,
                    'total' => $ord['total'] ?? null,
                    'currency' => $ord['currency'] ?? 'USD',
                    'raw_json' => $ord,
                    'placed_at' => $ord['placed_at'] ?? null,
                ]
            );
            foreach ($ord['items'] ?? [] as $it) {
                ChannelOrderItem::updateOrCreate(
                    ['channel_order_id' => $co->id, 'external_line_id' => $it['id'] ?? null],
                    ['sku' => $it['sku'] ?? null, 'qty' => $it['qty'] ?? 1, 'price' => $it['price'] ?? null, 'raw_json' => $it]
                );
            }
        }
    }
}
