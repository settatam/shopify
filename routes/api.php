<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\EbayPoliciesController;
use App\Http\Controllers\Api\EbayComplianceController;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\EffectiveMappingController;
use App\Http\Controllers\Api\ChannelStateController;
use App\Http\Controllers\Api\ChannelPolicyController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LocationsController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\Api\{ChannelAuthController, MappingsController, PublishController, ShopifyWebhooksController};
use App\Http\Controllers\Api\AIMappingController;
use App\Http\Controllers\Walmart\AuthController as WalmartAuthController;
use App\Http\Controllers\Etsy\AuthController as EtsyAuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::get('/locations', [LocationsController::class, 'index']);
Route::post('/locations', [LocationsController::class, 'store']);


// Channel → included warehouses + safety stock
Route::post('/channels/{channel}/policy', [ChannelPolicyController::class, 'update']);


// Inventory ops per location
Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
Route::post('/inventory/reserve', [InventoryController::class, 'reserve']);
Route::post('/inventory/release', [InventoryController::class, 'release']);
Route::post('/inventory/transfer', [InventoryController::class, 'transfer']);


// Channel → included warehouses + safety stock
Route::post('/channels/{channel}/policy', [ChannelPolicyController::class, 'update']);


// Inventory ops per location
Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
Route::post('/inventory/reserve', [InventoryController::class, 'reserve']);
Route::post('/inventory/release', [InventoryController::class, 'release']);
Route::post('/inventory/transfer', [InventoryController::class, 'transfer']);



Route::get('/channels/{channel}/ebay/supports-variations', [EbayComplianceController::class, 'supportsVariations']);

Route::post('/channels/connect', [ChannelAuthController::class, 'connect']);
Route::post('/channels/{channel}/disconnect', [ChannelAuthController::class, 'disconnect']);


Route::get('/channels/{channel}/categories', [MappingsController::class, 'categories']);
Route::post('/mappings', [MappingsController::class, 'store']);


Route::post('/publish', [PublishController::class, 'publish']);


// Shopify webhooks endpoint
Route::post('/shopify/webhooks', [ShopifyWebhooksController::class, 'handle']);

Route::get('/me/products', fn() => app(MeController::class)->products(auth()->user()->shop));

Route::get('/channels/{channel}/ebay/policies', [EbayPoliciesController::class, 'list']);
Route::post('/channels/{channel}/ebay/policies', [EbayPoliciesController::class, 'saveSelection']);
Route::post('/channels/{channel}/ebay/location', [EbayPoliciesController::class, 'upsertLocation']);

Route::get('/products/{product}', [ProductsController::class, 'show']);
Route::get('/mappings/effective', [EffectiveMappingController::class, 'show']);
Route::get('/channels/{channel}/state', [ChannelStateController::class, 'show']);

// Walmart integration
Route::prefix('walmart')->group(function() {
    Route::post('/connect', [WalmartAuthController::class, 'store'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/disconnect', [WalmartAuthController::class, 'disconnect'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/test', [WalmartAuthController::class, 'test'])->middleware('auth:sanctum');
});

// Etsy integration
Route::prefix('etsy')->group(function() {
    Route::get('/authorize', [EtsyAuthController::class, 'authorize'])->middleware('auth:sanctum');
    Route::get('/callback', [EtsyAuthController::class, 'callback']);
    Route::post('/channels/{channel}/disconnect', [EtsyAuthController::class, 'disconnect'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/test', [EtsyAuthController::class, 'test'])->middleware('auth:sanctum');
});

Route::middleware(['auth:sanctum'])->group(function(){
// Locations
    Route::post('/locations', [LocationsController::class, 'store']);


// Channels (for selector)
    Route::get('/channels', [ChannelsController::class, 'index']);


// Channel Policy
    Route::get('/channels/{channel}/policy', [ChannelPolicyController::class, 'show']);
    Route::put('/channels/{channel}/policy', [ChannelPolicyController::class, 'update']);


// Inventory quick actions
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
    Route::post('/inventory/reserve', [InventoryController::class, 'reserve']);
    Route::post('/inventory/release', [InventoryController::class, 'release']);

    Route::post('/locations', [LocationsController::class, 'store']);


    // Channel Policy
    Route::get('/channels/{channel}/policy', [ChannelPolicyController::class, 'show']);
    Route::put('/channels/{channel}/policy', [ChannelPolicyController::class, 'update']);


    // Inventory quick actions
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
    Route::post('/inventory/reserve', [InventoryController::class, 'reserve']);
    Route::post('/inventory/release', [InventoryController::class, 'release']);

    Route::post('/locations', [LocationsController::class, 'store']);



// Inventory quick actions
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
    Route::post('/inventory/reserve', [InventoryController::class, 'reserve']);
    Route::post('/inventory/release', [InventoryController::class, 'release']);

    Route::middleware(['auth:sanctum'])->group(function(){
        Route::put('/variants/{variant}/pricing', [PricingController::class, 'updateBase']);
        Route::put('/channels/{channel}/variants/{variant}/pricing', [PricingController::class, 'updateOverride']);
        Route::get('/channels/{channel}/variants/{variant}/pricing', [PricingController::class, 'showEffective']);

        Route::put('/variants/{variant}/pricing', [PricingController::class, 'updateBase']);
        Route::put('/channels/{channel}/variants/{variant}/pricing', [PricingController::class, 'updateOverride']);
        Route::get('/channels/{channel}/variants/{variant}/pricing', [PricingController::class, 'showEffective']);

        Route::post('/channels/sync-inventory', [ChannelSyncController::class, 'syncAll']); // optional variantIds[]
        Route::post('/channels/{channel}/sync-inventory', [ChannelSyncController::class, 'syncChannel']); // optional variantIds[]
    });

    // AI Product Mapping
    Route::prefix('ai-mapping')->group(function() {
        Route::post('/products/{product}/generate', [AIMappingController::class, 'generateForProduct']);
        Route::get('/products/{product}/suggestions', [AIMappingController::class, 'getSuggestions']);
        Route::post('/suggestions/{suggestion}/approve', [AIMappingController::class, 'approveSuggestion']);
        Route::post('/suggestions/{suggestion}/reject', [AIMappingController::class, 'rejectSuggestion']);
        Route::put('/suggestions/{suggestion}', [AIMappingController::class, 'updateSuggestion']);
        Route::post('/suggestions/batch-approve', [AIMappingController::class, 'batchApprove']);
        Route::post('/suggestions/batch-reject', [AIMappingController::class, 'batchReject']);
        Route::get('/statistics', [AIMappingController::class, 'getStatistics']);
    });

});
