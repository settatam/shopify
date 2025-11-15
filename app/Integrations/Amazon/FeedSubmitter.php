<?php

namespace App\Integrations\Amazon;
use App\Integrations\Amazon\Xml\{ProductFeedBuilder, RelationshipFeedBuilder, PriceFeedBuilder, InventoryFeedBuilder, ImageFeedBuilder};
use App\Jobs\Feeds\AmazonSubmitXmlFeed;

//Typical orchestration per product family
//
class FeedSubmitter
{

    public static function submitter
    {

        $xml1 = ProductFeedBuilder::build($channel, $product, $mapping);
        dispatch(new AmazonSubmitXmlFeed($channel->id, 'POST_PRODUCT_DATA', $xml1));


        // 2) Relationships
        $xml2 = RelationshipFeedBuilder::build($channel, $product, $parentSku, $childSkus);
        dispatch(new AmazonSubmitXmlFeed($channel->id, 'POST_PRODUCT_RELATIONSHIP_DATA', $xml2));


        // 3) Images
        $xml3 = ImageFeedBuilder::build($channel, $product, $product->variants);
        dispatch(new AmazonSubmitXmlFeed($channel->id, 'POST_PRODUCT_IMAGE_DATA', $xml3));


        // 4) Price & 5) Inventory (can be frequent)
        $xml4 = PriceFeedBuilder::build($channel, $product->variants);
        dispatch(new AmazonSubmitXmlFeed($channel->id, 'POST_PRODUCT_PRICING_DATA', $xml4));


        $xml5 = InventoryFeedBuilder::build($channel, $product->variants, latency: 1);
        dispatch(new AmazonSubmitXmlFeed($channel->id, 'POST_INVENTORY_AVAILABILITY_DATA', $xml5));
    }
}
