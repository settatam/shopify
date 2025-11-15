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
        Schema::create('feed_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $t->string('feed_type');
            $t->json('marketplace_ids');
            $t->string('content_type')->default('application/json; charset=UTF-8');
            $t->string('feed_document_id')->nullable();
            $t->string('feed_id')->nullable();
            $t->string('status')->default('NEW'); // NEW, UPLOADED, SUBMITTED, IN_PROGRESS, DONE, ERROR
            $t->unsignedInteger('submitted_count')->default(0);
            $t->unsignedInteger('processed_count')->default(0);
            $t->string('result_feed_document_id')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_batches');
    }
};
