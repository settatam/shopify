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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('shopify_variant_id')->index();
            $table->string('sku')->nullable()->index();
            $table->string('barcode')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 10)->nullable();
            $table->string('option1')->nullable();
            $table->string('option2')->nullable();
            $table->string('option3')->nullable();

            $table->string('mpn')->nullable()->after('barcode');           // manufacturer part number
            $table->string('gtin')->nullable()->after('mpn');              // holds UPC/EAN/ISBN/etc
            $table->enum('gtin_type', ['UPC','EAN','ISBN','GTIN14','JAN','NONE'])
                  ->default('NONE')->after('gtin');
            $table->string('isbn')->nullable()->after('gtin_type');

            // Condition & taxation
            $table->string('condition')->nullable()->after('isbn');        // new, used_like_new, refurbished, etc.
            $table->string('tax_code')->nullable()->after('condition');

            // Physical dimensions (item-level)
            $table->decimal('length', 10, 3)->nullable()->after('weight_unit');
            $table->decimal('width', 10, 3)->nullable()->after('length');
            $table->decimal('height', 10, 3)->nullable()->after('width');
            $table->string('dim_unit', 10)->nullable()->after('height');   // cm, in

            // Pricing controls / repricer bounds
            $table->decimal('msrp', 10, 2)->nullable()->after('compare_at_price');
            $table->decimal('min_price', 10, 2)->nullable()->after('msrp');
            $table->decimal('max_price', 10, 2)->nullable()->after('min_price');

            // Ops
            $table->unsignedInteger('fulfillment_latency')->nullable()->after('max_price'); // handling time (days)
            $table->json('attributes_json')->nullable()->after('fulfillment_latency'); // per-variant extras

            // Fast lookups
            $table->index(['mpn']);
            $table->index(['gtin']);
            $table->index(['isbn']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
