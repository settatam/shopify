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
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('channel_type')->nullable(); // Apply to specific channel type

            // Rule identification
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('priority')->default(0); // Higher priority rules apply first

            // Rule type and strategy
            $table->enum('rule_type', [
                'match_competitor',           // Match competitor price
                'beat_competitor',            // Beat competitor by X%
                'beat_competitor_fixed',      // Beat competitor by fixed amount
                'stay_below_competitor',      // Stay X% below competitor
                'stay_above_competitor',      // Stay X% above competitor
                'maintain_margin',            // Maintain specific margin %
                'market_based',               // Based on market average
                'time_based',                 // Different prices at different times
                'inventory_based',            // Adjust based on inventory levels
                'custom',                     // Custom formula
            ]);

            // Strategy parameters (JSON for flexibility)
            $table->json('strategy_config')->nullable(); // Store strategy-specific settings

            // Competitor-based rules
            $table->foreignId('target_competitor_id')->nullable()->constrained('competitor_products')->onDelete('set null');
            $table->enum('competitor_selection', ['lowest', 'highest', 'average', 'specific'])->nullable();

            // Price adjustment parameters
            $table->decimal('adjustment_value', 10, 2)->nullable(); // Amount or percentage
            $table->enum('adjustment_type', ['percentage', 'fixed'])->nullable();

            // Price boundaries
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->decimal('min_margin_percent', 5, 2)->nullable(); // Minimum profit margin %
            $table->decimal('max_margin_percent', 5, 2)->nullable(); // Maximum profit margin %

            // Cost basis for margin calculations
            $table->decimal('cost_basis', 10, 2)->nullable(); // Product cost for margin calculation

            // Conditions
            $table->json('conditions')->nullable(); // Additional conditions (time, inventory, etc.)

            // Automation settings
            $table->boolean('active')->default(true);
            $table->boolean('auto_apply')->default(false); // Automatically apply price changes
            $table->boolean('require_approval')->default(true); // Require manual approval

            // Schedule
            $table->time('active_from_time')->nullable(); // Daily schedule
            $table->time('active_to_time')->nullable();
            $table->json('active_days')->nullable(); // Days of week [1,2,3,4,5,6,7]

            // Limits and safety
            $table->decimal('max_price_change_percent', 5, 2)->nullable(); // Max change in one update
            $table->decimal('max_price_change_amount', 10, 2)->nullable();
            $table->integer('max_changes_per_day')->nullable();

            // Tracking
            $table->timestamp('last_applied_at')->nullable();
            $table->integer('times_applied')->default(0);
            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'active']);
            $table->index(['variant_id', 'active']);
            $table->index(['channel_id', 'active']);
            $table->index('priority');
            $table->index('rule_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
