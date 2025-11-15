<?php

namespace App\Integrations\Amazon;

use App\Models\{Channel, ChannelCategory};

class AmazonDefinitionsService
{
    public function __construct(protected AmazonClient $client, protected Channel $channel) {}

    public function listProductTypes(): array
    {
        $mids = implode(',', $this->channel->auth_json['marketplace_ids'] ?? []);
        $r = $this->client->request('GET', '/definitions/2020-09-01/productTypes', [ 'query' => ['marketplaceIds' => $mids] ]);
        return json_decode($r->getBody(), true)['productTypes'] ?? [];
    }

    public function getProductTypeSchema(string $productType): array
    {
        $mids = implode(',', $this->channel->auth_json['marketplace_ids'] ?? []);
        $r = $this->client->request('GET', '/definitions/2020-09-01/productTypes/'.$productType, [ 'query' => ['marketplaceIds' => $mids, 'schemaVersion' => 'LATEST'] ]);
        return json_decode($r->getBody(), true);
    }

    /** Cache into channel_categories for your mapping UI */
    public function cacheTypes(): void
    {
        foreach ($this->listProductTypes() as $t) {
            ChannelCategory::updateOrCreate(
                ['channel_id' => $this->channel->id, 'external_category_id' => $t['name']],
                ['name' => $t['name'], 'attributes_json' => $t]
            );
        }
    }

    public function listAttributes(string $productType): array
    {
        $schema = $this->getProductTypeSchema($productType);
        $props = $schema['properties'] ?? [];
        $req = $schema['required'] ?? [];
        $flatten = function(array $props, string $prefix = '') use (&$flatten) {
            $out = [];
            foreach ($props as $name => $def) {
                $full = ltrim($prefix.$name, '.');
                $type = $def['type'] ?? ($def['anyOf'][0]['type'] ?? null);
                $enum = $def['enum'] ?? null;
                $out[$full] = [ 'name' => $full, 'type' => $type, 'enum' => $enum ];
                if (!empty($def['properties'])) {
                    $child = $flatten($def['properties'], $full.'.');
                    $out = $out + $child;
                }
            }
            return $out;
        };
        $flat = $flatten($props);
        $required = array_values(array_filter(array_keys($flat), fn($k) => in_array(explode('.', $k)[0], $req)));
        $optional = array_values(array_diff(array_keys($flat), $required));
        return ['required' => $required, 'optional' => $optional, 'flat' => $flat];
    }

    public function resolveVariationKeys(string $productType, string $theme): array
    {
        $schema = $this->getProductTypeSchema($productType);
// Heuristic: find a theme in schema that matches or contains the same attributes
        $sizeKey = 'size_name'; $colorKey = 'color_name'; $themeKey = 'variationTheme';
// TODO: parse $schema to find actual property names; fall back to defaults
        return [ 'size' => $sizeKey, 'color' => $colorKey, 'theme' => $themeKey ];
    }
}
