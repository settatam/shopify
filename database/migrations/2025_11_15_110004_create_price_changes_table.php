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
        Schema::create('price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->nullable()->constrained()->onDelete('cascade');

            // Price change data
            $table->decimal('old_price', 10, 2);
            $table->decimal('new_price', 10, 2);
            $table->decimal('price_difference', 10, 2);
            $table->decimal('price_difference_percent', 5, 2);
            $table->string('currency', 3)->default('USD');

            // Change source
            $table->enum('change_source', [
                'manual',              // Manual user change
                'automated_rule',      // Applied by pricing rule
                'bulk_update',         // Bulk price update
                'import',              // Imported from file
                'api',                 // API call
                'competitor_match',    // Matched competitor price
            ]);

            $table->foreignId('pricing_rule_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Reason and context
            $table->text('reason')->nullable();
            $table->json('context')->nullable(); // Additional context like competitor prices at time of change

            // Approval workflow
            $table->enum('status', ['pending', 'approved', 'rejected', 'applied', 'reverted'])->default('applied');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();

            // Performance tracking
            $table->decimal('sales_before', 10, 2)->nullable(); // Sales 7 days before
            $table->decimal('sales_after', 10, 2')->nullable(); // Sales 7 days after
            $table->integer('units_sold_before')->nullable();
            $table->integer('units_sold_after')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'created_at']);
            $table->index(['variant_id', 'created_at']);
            $table->index(['channel_id', 'created_at']);
            $table->index('change_source');
            $table->index('status');
            $table->index('pricing_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_changes');
    }
};
