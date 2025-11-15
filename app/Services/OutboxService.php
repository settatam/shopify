<?php

namespace App\Services;

use App\Models\IntegrationLog;

class OutboxService
{
    public static function record(string $subject, array $payload, ?int $shopId = null, ?int $channelId = null): void
    {
        IntegrationLog::create([
            'shop_id' => $shopId,
            'channel_id' => $channelId,
            'subject' => $subject,
            'level' => 'info',
            'context_json' => $payload,
        ]);
    }
}
