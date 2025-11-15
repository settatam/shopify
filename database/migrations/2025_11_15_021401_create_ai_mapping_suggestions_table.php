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
        Schema::create('ai_mapping_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->json('suggested_mapping'); // AI-generated mapping
            $table->json('channel_category_suggestion')->nullable(); // Suggested category
            $table->text('ai_reasoning')->nullable(); // Why AI chose this mapping
            $table->float('confidence_score')->nullable(); // 0-1 confidence score
            $table->json('metadata')->nullable(); // Additional AI metadata
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['product_id', 'channel_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_mapping_suggestions');
    }
};
