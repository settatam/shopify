<?php

namespace App\Integrations\Ebay;

use App\Models\Channel;

class EbayComplianceService
{
    public function __construct(protected EbayClient $client) {}

    /** Check if a category supports variations (Listing Structure Policies). */
    public function supportsVariations(string $marketplaceId, string $categoryId): bool
    {
        $r = $this->client->request('GET', '/sell/metadata/v1/marketplace/'.$marketplaceId.'/get_listing_structure_policies', [
            'query' => ['filter' => 'categoryIds:{'.$categoryId.'}']
        ]);
        $data = json_decode($r->getBody(), true);
        $pol = $data['listingStructurePolicies'][0] ?? null;
        return (bool)($pol['variationSupported'] ?? false);
    }

    /** Optional: return item condition metadata for a category. */
    public function itemConditionPolicies(string $marketplaceId, string $categoryId): array
    {
        $r = $this->client->request('GET', '/sell/metadata/v1/marketplace/'.$marketplaceId.'/get_item_condition_policies', [
            'query' => ['filter' => 'categoryIds:{'.$categoryId.'}']
        ]);
        return json_decode($r->getBody(), true);
    }
}
