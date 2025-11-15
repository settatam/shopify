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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->unsignedBigInteger('shopify_product_id')->index();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('brand')->nullable();
            $table->string('vendor')->nullable();
            $table->string('status')->default('active');
            $table->string('product_type')->nullable()->after('vendor');
            $table->json('tags_json')->nullable()->after('product_type');
            $table->json('images_json')->nullable()->after('tags_json');
            $table->json('attributes_json')->nullable()->after('images_json'); // arbitrary key/vals

            $table->string('manufacturer')->nullable()->after('brand');
            $table->string('country_of_origin', 3)->nullable()->after('manufacturer'); // ISO2/3
            $table->string('hs_code', 12)->nullable()->after('country_of_origin');
            $table->decimal('msrp', 10, 2)->nullable()->after('status');

            // Default package dims/weight (variants may override)
            $table->decimal('package_length', 10, 3)->nullable()->after('msrp');
            $table->decimal('package_width', 10, 3)->nullable()->after('package_length');
            $table->decimal('package_height', 10, 3)->nullable()->after('package_width');
            $table->string('package_dim_unit', 10)->nullable()->after('package_height'); // cm, in
            $table->decimal('package_weight', 10, 3)->nullable()->after('package_dim_unit');
            $table->string('package_weight_unit', 10)->nullable()->after('package_weight'); // g, kg, lb, oz

            // Channel/compliance hints
            $table->json('bullet_points_json')->nullable()->after('attributes_json');
            $table->text('search_terms')->nullable()->after('bullet_points_json'); // Amazon generic keywords
            $table->json('compliance_json')->nullable()->after('search_terms'); // hazmat, battery, prop65, age_restrictions

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
