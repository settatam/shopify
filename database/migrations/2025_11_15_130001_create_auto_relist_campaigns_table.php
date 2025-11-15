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
        Schema::create('auto_relist_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();

            // Campaign settings
            $table->boolean('active')->default(true);
            $table->json('channel_ids')->nullable(); // Which channels to monitor

            // Detection criteria
            $table->integer('min_days_listed')->default(7); // How long before considering "dead"
            $table->integer('max_views')->default(10); // Maximum views before considered dead
            $table->integer('max_sales')->default(0); // Maximum sales (usually 0)
            $table->decimal('min_view_to_sale_ratio', 5, 2)->nullable(); // e.g., 100 views but 0 sales
            $table->integer('check_frequency_hours')->default(24); // How often to check

            // Actions to take
            $table->boolean('auto_rewrite_title')->default(true);
            $table->boolean('auto_fix_description')->default(true);
            $table->boolean('auto_fix_category')->default(true);
            $table->boolean('auto_add_attributes')->default(true);
            $table->boolean('auto_swap_images')->default(true);
            $table->boolean('auto_adjust_price')->default(true);
            $table->boolean('auto_relist')->default(true);
            $table->boolean('require_approval')->default(true); // Require manual approval before relisting

            // Pricing adjustments
            $table->enum('price_adjustment_strategy', ['decrease', 'increase', 'market_based', 'none'])->default('decrease');
            $table->decimal('price_adjustment_percent', 5, 2)->default(10.0); // 10% decrease by default
            $table->decimal('min_price_floor', 10, 2)->nullable(); // Don't go below this
            $table->decimal('max_price_ceiling', 10, 2)->nullable(); // Don't go above this

            // Timing optimization
            $table->boolean('optimize_relist_time')->default(true);
            $table->time('preferred_relist_time')->nullable(); // Specific time of day
            $table->json('preferred_relist_days')->nullable(); // Days of week [1-7]

            // Limits
            $table->integer('max_relists_per_day')->nullable();
            $table->integer('max_relists_per_product')->default(3); // Max times to relist same product

            // Statistics
            $table->integer('products_detected')->default(0);
            $table->integer('products_relisted')->default(0);
            $table->integer('pending_approval')->default(0);
            $table->timestamp('last_run_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['shop_id', 'active']);
            $table->index('last_run_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_relist_campaigns');
    }
};
