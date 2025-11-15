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
        Schema::create('ai_optimizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->string('channel_type'); // ebay, amazon, etsy, walmart, etc.
            $table->enum('optimization_type', ['title', 'description', 'both'])->default('both');

            // Original content
            $table->text('original_title')->nullable();
            $table->longText('original_description')->nullable();

            // AI optimized content
            $table->text('optimized_title')->nullable();
            $table->longText('optimized_description')->nullable();

            // AI metadata
            $table->decimal('quality_score', 5, 2)->nullable(); // 0-100
            $table->text('ai_reasoning')->nullable();
            $table->json('improvements_made')->nullable(); // List of improvements
            $table->json('keywords_added')->nullable(); // SEO keywords added
            $table->json('channel_guidelines')->nullable(); // Guidelines followed

            // Character counts
            $table->integer('original_title_length')->nullable();
            $table->integer('optimized_title_length')->nullable();
            $table->integer('original_description_length')->nullable();
            $table->integer('optimized_description_length')->nullable();

            // Approval status
            $table->enum('status', ['pending', 'approved', 'rejected', 'modified'])->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();

            // Final content (after approval/modification)
            $table->text('final_title')->nullable();
            $table->longText('final_description')->nullable();

            // Metadata
            $table->json('product_data_used')->nullable(); // Snapshot of product data
            $table->text('rejection_reason')->nullable();
            $table->boolean('applied_to_product')->default(false);
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'channel_id']);
            $table->index('status');
            $table->index('channel_type');
            $table->index('optimization_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_optimizations');
    }
};
