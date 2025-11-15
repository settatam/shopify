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
use App\Http\Controllers\QuickBooks\AuthController as QuickBooksAuthController;
use App\Http\Controllers\Xero\AuthController as XeroAuthController;
use App\Http\Controllers\Twilio\SettingsController as TwilioSettingsController;
use App\Http\Controllers\Square\AuthController as SquareAuthController;
use App\Http\Controllers\Square\WebhookController as SquareWebhookController;
use App\Http\Controllers\ShipStation\SettingsController as ShipStationSettingsController;
use App\Http\Controllers\ShipStation\WebhookController as ShipStationWebhookController;
use App\Http\Controllers\POS\POSController;
use App\Http\Controllers\POS\CashRegisterController;
use App\Http\Controllers\Dejavoo\SettingsController as DejavooSettingsController;
use App\Http\Controllers\Dejavoo\PaymentController as DejavooPaymentController;
use App\Http\Controllers\Dashboard\SalesDashboardController;
use App\Http\Controllers\ZohoInventory\AuthController as ZohoInventoryAuthController;
use App\Http\Controllers\Api\AICategoryMappingController;
use App\Http\Controllers\Api\AIOptimizationController;

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

// QuickBooks integration
Route::prefix('quickbooks')->group(function() {
    Route::get('/authorize', [QuickBooksAuthController::class, 'authorize'])->middleware('auth:sanctum');
    Route::get('/callback', [QuickBooksAuthController::class, 'callback']);
    Route::post('/channels/{channel}/disconnect', [QuickBooksAuthController::class, 'disconnect'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/test', [QuickBooksAuthController::class, 'test'])->middleware('auth:sanctum');
    Route::get('/channels/{channel}/accounts', [QuickBooksAuthController::class, 'getAccounts'])->middleware('auth:sanctum');
});

// Xero integration
Route::prefix('xero')->group(function() {
    Route::get('/authorize', [XeroAuthController::class, 'authorize'])->middleware('auth:sanctum');
    Route::get('/callback', [XeroAuthController::class, 'callback']);
    Route::post('/channels/{channel}/disconnect', [XeroAuthController::class, 'disconnect'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/test', [XeroAuthController::class, 'test'])->middleware('auth:sanctum');
    Route::get('/channels/{channel}/accounts', [XeroAuthController::class, 'getAccounts'])->middleware('auth:sanctum');
});

// Twilio notifications
Route::prefix('twilio')->middleware('auth:sanctum')->group(function() {
    Route::get('/settings', [TwilioSettingsController::class, 'index']);
    Route::post('/credentials', [TwilioSettingsController::class, 'updateCredentials']);
    Route::post('/test-connection', [TwilioSettingsController::class, 'testConnection']);
    Route::post('/send-test', [TwilioSettingsController::class, 'sendTest']);
    Route::post('/templates', [TwilioSettingsController::class, 'updateTemplates']);
    Route::get('/history', [TwilioSettingsController::class, 'getHistory']);
    Route::post('/disconnect', [TwilioSettingsController::class, 'disconnect']);
});

// Square POS integration
Route::prefix('square')->group(function() {
    Route::get('/authorize', [SquareAuthController::class, 'authorize'])->middleware('auth:sanctum');
    Route::get('/callback', [SquareAuthController::class, 'callback']);
    Route::post('/channels/{channel}/disconnect', [SquareAuthController::class, 'disconnect'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/test', [SquareAuthController::class, 'test'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/sync-catalog', [SquareAuthController::class, 'syncCatalog'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/sync-inventory', [SquareAuthController::class, 'syncInventory'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/fetch-orders', [SquareAuthController::class, 'fetchOrders'])->middleware('auth:sanctum');
    Route::get('/channels/{channel}/locations', [SquareAuthController::class, 'getLocations'])->middleware('auth:sanctum');
    Route::post('/webhook', [SquareWebhookController::class, 'handle'])->withoutMiddleware(['auth:sanctum']);
});

// ShipStation shipping integration
Route::prefix('shipstation')->middleware('auth:sanctum')->group(function() {
    Route::get('/settings', [ShipStationSettingsController::class, 'index']);
    Route::post('/credentials', [ShipStationSettingsController::class, 'updateCredentials']);
    Route::post('/settings', [ShipStationSettingsController::class, 'updateSettings']);
    Route::post('/test-connection', [ShipStationSettingsController::class, 'testConnection']);
    Route::get('/carriers', [ShipStationSettingsController::class, 'getCarriers']);
    Route::get('/services', [ShipStationSettingsController::class, 'getServices']);
    Route::get('/warehouses', [ShipStationSettingsController::class, 'getWarehouses']);
    Route::get('/stores', [ShipStationSettingsController::class, 'getStores']);
    Route::post('/disconnect', [ShipStationSettingsController::class, 'disconnect']);
    Route::post('/rates', [ShipStationSettingsController::class, 'getRates']);
    Route::post('/create-label', [ShipStationSettingsController::class, 'createLabel']);
});

// ShipStation webhooks (unauthenticated)
Route::post('/shipstation/webhook', [ShipStationWebhookController::class, 'handle']);

// POS (Point of Sale) - Cash and check sales
Route::prefix('pos')->middleware('auth:sanctum')->group(function() {
    // Cash Registers
    Route::get('/registers', [CashRegisterController::class, 'index']);
    Route::post('/registers', [CashRegisterController::class, 'store']);
    Route::get('/registers/{id}', [CashRegisterController::class, 'show']);
    Route::put('/registers/{id}', [CashRegisterController::class, 'update']);
    Route::delete('/registers/{id}', [CashRegisterController::class, 'destroy']);
    Route::post('/registers/{id}/open', [CashRegisterController::class, 'open']);
    Route::post('/registers/{id}/close', [CashRegisterController::class, 'close']);
    Route::post('/registers/{id}/cash-in', [CashRegisterController::class, 'cashIn']);
    Route::post('/registers/{id}/cash-out', [CashRegisterController::class, 'cashOut']);
    Route::get('/registers/{id}/activities', [CashRegisterController::class, 'getActivities']);
    Route::get('/registers/{id}/summary', [CashRegisterController::class, 'getSummary']);

    // POS Transactions
    Route::get('/products', [POSController::class, 'getProducts']);
    Route::post('/search-product', [POSController::class, 'searchProduct']);
    Route::post('/transactions', [POSController::class, 'createTransaction']);
    Route::get('/transactions', [POSController::class, 'getTransactions']);
    Route::get('/transactions/{id}', [POSController::class, 'getTransaction']);
    Route::post('/transactions/{id}/void', [POSController::class, 'voidTransaction']);
    Route::get('/sales-summary', [POSController::class, 'getSalesSummary']);
});

// Dejavoo payment processing
Route::prefix('dejavoo')->middleware('auth:sanctum')->group(function() {
    // Settings
    Route::get('/settings', [DejavooSettingsController::class, 'index']);
    Route::post('/credentials', [DejavooSettingsController::class, 'updateCredentials']);
    Route::post('/settings', [DejavooSettingsController::class, 'updateSettings']);
    Route::post('/test-connection', [DejavooSettingsController::class, 'testConnection']);
    Route::post('/batch-close', [DejavooSettingsController::class, 'batchClose']);
    Route::post('/disconnect', [DejavooSettingsController::class, 'disconnect']);

    // Payment Processing
    Route::post('/process-payment', [DejavooPaymentController::class, 'processPayment']);
    Route::post('/void-payment', [DejavooPaymentController::class, 'voidPayment']);
    Route::post('/refund-payment', [DejavooPaymentController::class, 'refundPayment']);
    Route::post('/tip-adjustment', [DejavooPaymentController::class, 'tipAdjustment']);
    Route::post('/get-status', [DejavooPaymentController::class, 'getStatus']);
    Route::post('/cancel', [DejavooPaymentController::class, 'cancel']);
});

// Sales Dashboard with real-time updates
Route::middleware('auth:sanctum')->group(function() {
    Route::get('/dashboard/sales', [SalesDashboardController::class, 'index']);
});

// Zoho Inventory integration
Route::prefix('zoho-inventory')->group(function() {
    Route::get('/authorize', [ZohoInventoryAuthController::class, 'authorize'])->middleware('auth:sanctum');
    Route::get('/callback', [ZohoInventoryAuthController::class, 'callback']);
    Route::post('/channels/{channel}/disconnect', [ZohoInventoryAuthController::class, 'disconnect'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/test', [ZohoInventoryAuthController::class, 'test'])->middleware('auth:sanctum');
    Route::get('/channels/{channel}/organizations', [ZohoInventoryAuthController::class, 'getOrganizations'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/switch-organization', [ZohoInventoryAuthController::class, 'switchOrganization'])->middleware('auth:sanctum');
    Route::get('/channels/{channel}/warehouses', [ZohoInventoryAuthController::class, 'getWarehouses'])->middleware('auth:sanctum');
    Route::post('/channels/{channel}/settings', [ZohoInventoryAuthController::class, 'updateSettings'])->middleware('auth:sanctum');
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

    // AI Category Mapping
    Route::prefix('ai-category-mapping')->group(function() {
        Route::post('/products/{product}/generate', [AICategoryMappingController::class, 'generateForProduct']);
        Route::get('/products/{product}/mappings', [AICategoryMappingController::class, 'getMappings']);
        Route::get('/pending', [AICategoryMappingController::class, 'getPendingMappings']);
        Route::post('/{mapping}/approve', [AICategoryMappingController::class, 'approveMapping']);
        Route::post('/{mapping}/reject', [AICategoryMappingController::class, 'rejectMapping']);
        Route::post('/{mapping}/modify', [AICategoryMappingController::class, 'modifyMapping']);
        Route::post('/batch-approve', [AICategoryMappingController::class, 'batchApprove']);
        Route::post('/batch-reject', [AICategoryMappingController::class, 'batchReject']);
        Route::post('/batch-generate', [AICategoryMappingController::class, 'batchGenerate']);
        Route::get('/statistics', [AICategoryMappingController::class, 'getStatistics']);
        Route::delete('/{mapping}', [AICategoryMappingController::class, 'deleteMapping']);
    });

    // AI Content Optimization (Title & Description)
    Route::prefix('ai-optimization')->group(function() {
        Route::post('/products/{product}/generate', [AIOptimizationController::class, 'generateForProduct']);
        Route::get('/products/{product}/optimizations', [AIOptimizationController::class, 'getOptimizations']);
        Route::get('/pending', [AIOptimizationController::class, 'getPendingOptimizations']);
        Route::post('/{optimization}/approve', [AIOptimizationController::class, 'approveOptimization']);
        Route::post('/{optimization}/reject', [AIOptimizationController::class, 'rejectOptimization']);
        Route::post('/{optimization}/modify', [AIOptimizationController::class, 'modifyOptimization']);
        Route::post('/{optimization}/apply', [AIOptimizationController::class, 'applyToProduct']);
        Route::post('/batch-approve', [AIOptimizationController::class, 'batchApprove']);
        Route::post('/batch-reject', [AIOptimizationController::class, 'batchReject']);
        Route::post('/batch-generate', [AIOptimizationController::class, 'batchGenerate']);
        Route::get('/statistics', [AIOptimizationController::class, 'getStatistics']);
        Route::delete('/{optimization}', [AIOptimizationController::class, 'deleteOptimization']);
        Route::post('/products/{product}/preview', [AIOptimizationController::class, 'previewOptimization']);
    });

});
