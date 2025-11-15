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
        Schema::create('price_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('pricing_rule_id')->nullable()->constrained()->onDelete('set null');

            // Current and suggested prices
            $table->decimal('current_price', 10, 2);
            $table->decimal('suggested_price', 10, 2);
            $table->decimal('price_difference', 10, 2);
            $table->decimal('price_difference_percent', 5, 2);
            $table->string('currency', 3)->default('USD');

            // Reasoning
            $table->text('reasoning');
            $table->json('analysis_data')->nullable(); // Competitor prices, margins, etc.

            // Competitor context
            $table->decimal('lowest_competitor_price', 10, 2)->nullable();
            $table->decimal('highest_competitor_price', 10, 2)->nullable();
            $table->decimal('average_competitor_price', 10, 2)->nullable();
            $table->integer('competitors_checked')->default(0);

            // Margin analysis
            $table->decimal('current_margin_percent', 5, 2)->nullable();
            $table->decimal('suggested_margin_percent', 5, 2)->nullable();
            $table->decimal('current_margin_amount', 10, 2)->nullable();
            $table->decimal('suggested_margin_amount', 10, 2)->nullable();

            // Confidence and priority
            $table->decimal('confidence_score', 5, 2)->default(0); // 0-100
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // Status
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Expiration
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'status']);
            $table->index(['variant_id', 'status']);
            $table->index(['channel_id', 'status']);
            $table->index('status');
            $table->index('priority');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_suggestions');
    }
};
