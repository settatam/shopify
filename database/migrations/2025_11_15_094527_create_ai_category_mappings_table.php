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
        Schema::create('ai_category_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->string('channel_type'); // ebay, amazon, etsy, walmart, etc.

            // AI suggested category
            $table->string('suggested_category_id')->nullable();
            $table->string('suggested_category_name')->nullable();
            $table->text('suggested_category_path')->nullable(); // Full breadcrumb path
            $table->decimal('confidence_score', 5, 2)->nullable(); // 0-100
            $table->json('alternative_suggestions')->nullable(); // Other top matches

            // AI reasoning
            $table->text('ai_reasoning')->nullable();
            $table->json('matched_keywords')->nullable(); // Keywords that influenced decision

            // Approval status
            $table->enum('status', ['pending', 'approved', 'rejected', 'modified'])->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();

            // Final category (after approval/modification)
            $table->string('final_category_id')->nullable();
            $table->string('final_category_name')->nullable();
            $table->text('final_category_path')->nullable();

            // Metadata
            $table->json('product_data_used')->nullable(); // Snapshot of product data used
            $table->json('channel_categories')->nullable(); // Available categories at time
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'channel_id']);
            $table->index('status');
            $table->index('channel_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_category_mappings');
    }
};
