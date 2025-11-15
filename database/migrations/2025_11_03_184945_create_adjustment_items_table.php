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
        Schema::create('adjustment_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('adjustment_id')->constrained('adjustments')->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained('product_variants');
            $t->integer('qty_delta');
            $t->decimal('unit_cost', 12, 4)->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustment_items');
    }
};
