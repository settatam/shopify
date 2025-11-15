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
        Schema::create('channel_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_order_id')->constrained('channel_orders')->cascadeOnDelete();
            $table->string('external_line_id')->nullable();
            $table->string('sku')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_order_items');
    }
};
