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
        Schema::create('feed_batch_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('feed_batch_id')->constrained('feed_batches')->cascadeOnDelete();
            $t->string('sku');
            $t->string('operation')->nullable(); // e.g., price, quantity, attrs
            $t->json('payload_json');
            $t->string('result_code')->nullable();
            $t->text('result_message')->nullable();
            $t->json('result_json')->nullable();
            $t->timestamps();
            $t->index(['feed_batch_id','sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_batch_items');
    }
};
