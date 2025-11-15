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
        Schema::create('competitor_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_product_id')->constrained()->onDelete('cascade');

            // Price data
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();

            // Stock data
            $table->boolean('in_stock')->default(true);
            $table->integer('stock_quantity')->nullable();

            // Price change tracking
            $table->decimal('previous_price', 10, 2)->nullable();
            $table->decimal('price_change', 10, 2)->nullable(); // Difference from previous
            $table->decimal('price_change_percent', 5, 2)->nullable(); // Percentage change

            // Metadata
            $table->json('additional_data')->nullable(); // Ratings, reviews, seller rank, etc.
            $table->timestamp('scraped_at');

            $table->timestamps();

            // Indexes
            $table->index('competitor_product_id');
            $table->index('scraped_at');
            $table->index('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitor_prices');
    }
};
