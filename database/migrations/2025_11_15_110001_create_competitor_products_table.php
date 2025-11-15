<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('competitor_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->string('channel_type'); // ebay, amazon, etsy, walmart, shopify, custom

            // Competitor identification
            $table->string('competitor_name')->nullable();
            $table->string('competitor_url')->nullable();
            $table->string('competitor_product_id')->nullable(); // ASIN, eBay Item ID, etc.
            $table->string('competitor_product_url');

            // Product matching
            $table->string('competitor_product_title')->nullable();
            $table->text('competitor_product_description')->nullable();
            $table->decimal('match_confidence', 5, 2)->default(100); // 0-100%

            // Current price data
            $table->decimal('current_price', 10, 2)->nullable();
            $table->string('current_currency', 3)->default('USD');
            $table->decimal('current_shipping_cost', 10, 2)->nullable();
            $table->decimal('current_total_cost', 10, 2)->nullable();

            // Stock status
            $table->boolean('in_stock')->default(true);
            $table->integer('stock_quantity')->nullable();

            // Tracking settings
            $table->boolean('active')->default(true);
            $table->integer('check_frequency_minutes')->default(60); // How often to check
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_price_change_at')->nullable();

            // Metadata
            $table->json('additional_data')->nullable(); // Store extra info like ratings, seller info, etc.
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'channel_id']);
            $table->index('channel_type');
            $table->index('active');
            $table->index('last_checked_at');
            $table->index('competitor_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitor_products');
    }
};
