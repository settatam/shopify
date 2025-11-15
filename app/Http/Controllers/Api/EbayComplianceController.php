<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Integrations\Ebay\EbayAuthService;
use App\Integrations\Ebay\EbayClient;
use App\Integrations\Ebay\EbayComplianceService;
use Illuminate\Http\Request;

class EbayComplianceController extends Controller
{
    //
    public function supportsVariations(Request $r, Channel $channel)
    {
        $data = $r->validate(['marketplace_id' => 'required', 'category_id' => 'required']);
        $svc = app()->makeWith(EbayComplianceService::class, [
            'client' => app()->makeWith(EbayClient::class, ['channel' => $channel, 'auth' => app(EbayAuthService::class)])
        ]);
        return ['supports' => $svc->supportsVariations($data['marketplace_id'], $data['category_id'])];
    }
}
