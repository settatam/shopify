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
        Schema::create('smart_publish_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Publish configuration
            $table->json('selected_channels')->nullable(); // Channel IDs to publish to
            $table->boolean('auto_optimize_title')->default(true);
            $table->boolean('auto_optimize_description')->default(true);
            $table->boolean('auto_map_category')->default(true);
            $table->boolean('auto_suggest_attributes')->default(true);
            $table->boolean('auto_optimize_images')->default(true);
            $table->boolean('auto_set_price')->default(true);
            $table->boolean('auto_check_compliance')->default(true);

            // Overall status
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'partial_success',
            ])->default('pending');

            // Step statuses
            $table->enum('title_optimization_status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->enum('description_optimization_status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->enum('category_mapping_status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->enum('attribute_suggestion_status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->enum('image_optimization_status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->enum('pricing_status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->enum('compliance_check_status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');
            $table->enum('publishing_status', ['pending', 'processing', 'completed', 'failed', 'skipped'])->default('pending');

            // Results and data
            $table->json('optimized_content')->nullable(); // Titles, descriptions per channel
            $table->json('mapped_categories')->nullable(); // Categories per channel
            $table->json('suggested_attributes')->nullable(); // Attributes per channel
            $table->json('optimized_images')->nullable(); // Image URLs and metadata
            $table->json('pricing_data')->nullable(); // Prices per channel
            $table->json('compliance_results')->nullable(); // Compliance checks per channel
            $table->json('publish_results')->nullable(); // Publish results per channel

            // Errors and warnings
            $table->json('errors')->nullable(); // List of errors encountered
            $table->json('warnings')->nullable(); // List of warnings
            $table->text('failure_reason')->nullable();

            // Statistics
            $table->integer('channels_attempted')->default(0);
            $table->integer('channels_succeeded')->default(0);
            $table->integer('channels_failed')->default(0);
            $table->integer('total_steps')->default(8);
            $table->integer('completed_steps')->default(0);
            $table->integer('failed_steps')->default(0);
            $table->integer('skipped_steps')->default(0);

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_publish_reports');
    }
};
