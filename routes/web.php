<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Inertia\FeedsConsoleController;
use App\Http\Middleware\VerifyShopifyFrame;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

//Shopify Area

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Shopify OAuth routes (no auth required)
Route::get('/shopify/install', [\App\Http\Controllers\Shopify\AuthController::class, 'install'])->name('shopify.install');
Route::get('/shopify/auth/callback', [\App\Http\Controllers\Shopify\AuthController::class, 'callback'])->name('shopify.callback');

// Shopify webhooks (verified via middleware)
Route::post('/shopify/webhooks', [\App\Http\Controllers\Shopify\WebhooksController::class, 'handle'])->name('shopify.webhooks');

// Shopify embedded app pages (requires authentication)
Route::middleware('shopify.auth')->group(function () {
    Route::get('/app', [\App\Http\Controllers\Shopify\AuthController::class, 'appHome'])->name('app.home');
    // Add any other embedded pages here
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Inertia screens
    Route::get('/mappings/{product}/{channel}', [MappingsEditorController::class, 'edit'])->name('mappings.edit');
    Route::get('/products/{product}/preview/{channel}', [VariationPreviewController::class, 'show'])->name('preview');


//    // Actions (POST) can reuse your existing API controllers
//    Route::post('/mappings', [MappingsController::class, 'store'])->name('mappings.store');
//    Route::post('/publish', [PublishController::class, 'publish'])->name('publish.queue');

    Route::get('/channels/{channel}/amazon/settings', [AmazonSettingsController::class, 'edit'])->name('amazon.settings');
    Route::post('/channels/{channel}/amazon/settings', [AmazonSettingsController::class, 'update'])->name('amazon.settings.update');

    Route::get('/feeds', [FeedsConsoleController::class, 'index'])->name('feeds.index');
    Route::get('/feeds/{batch}', [FeedsConsoleController::class, 'show'])->name('feeds.show');

    Route::get('/inventory/:variantId', [InventoryApi::class, 'show']);
    Route::post('/inventory/reserve', [InventoryApi::class, 'reserve']);
    Route::post('/inventory/release', [InventoryApi::class, 'release']);

    Route::get('/locations', [\App\Http\Controllers\LocationController::class, 'index'])->name('locations.index');
    Route::get('/channels/{channel}/policy', [\App\Http\Controllers\Api\ChannelPolicyController::class, 'edit'])->name('channels.policy.edit');
    Route::get('/products/{product}/variants/{variant}/inventory', [\App\Http\Controllers\InventoryDisplayController::class, 'showVariantInventory'])->name('variants.inventory');
});

require __DIR__.'/auth.php';
