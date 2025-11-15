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
        Schema::create('auto_relist_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('auto_relist_campaigns')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');

            // Detection data
            $table->integer('days_listed');
            $table->integer('total_views')->default(0);
            $table->integer('total_sales')->default(0);
            $table->decimal('view_to_sale_ratio', 10, 2)->nullable();
            $table->timestamp('detected_at');

            // Status
            $table->enum('status', [
                'detected',
                'analyzing',
                'pending_approval',
                'approved',
                'rejected',
                'processing',
                'completed',
                'failed',
            ])->default('detected');

            // Original listing data
            $table->text('original_title')->nullable();
            $table->longText('original_description')->nullable();
            $table->string('original_category_id')->nullable();
            $table->json('original_attributes')->nullable();
            $table->json('original_images')->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->timestamp('original_listed_at')->nullable();

            // Optimized listing data
            $table->text('new_title')->nullable();
            $table->longText('new_description')->nullable();
            $table->string('new_category_id')->nullable();
            $table->json('new_attributes')->nullable();
            $table->json('new_images')->nullable(); // Reordered images
            $table->decimal('new_price', 10, 2)->nullable();
            $table->timestamp('relist_scheduled_at')->nullable();

            // AI analysis
            $table->json('ai_analysis')->nullable(); // What AI detected as issues
            $table->json('changes_made')->nullable(); // List of changes applied
            $table->text('optimization_reasoning')->nullable();

            // Results
            $table->integer('views_before')->default(0);
            $table->integer('views_after')->default(0);
            $table->integer('sales_before')->default(0);
            $table->integer('sales_after')->default(0);
            $table->decimal('conversion_rate_before', 5, 2)->nullable();
            $table->decimal('conversion_rate_after', 5, 2)->nullable();

            // Metadata
            $table->text('failure_reason')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('relisted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['campaign_id', 'status']);
            $table->index(['product_id', 'channel_id']);
            $table->index('status');
            $table->index('detected_at');
            $table->index('relisted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_relist_actions');
    }
};
