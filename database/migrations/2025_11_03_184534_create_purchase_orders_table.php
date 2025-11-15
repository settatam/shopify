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
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $t->foreignId('location_id')->constrained('locations'); // destination
            $t->string('po_number')->unique();
            $t->string('status')->default('draft'); // draft, sent, partially_received, received, closed, cancelled
            $t->decimal('subtotal', 14, 4)->default(0);
            $t->decimal('tax_total', 14, 4)->default(0);
            $t->decimal('shipping_total', 14, 4)->default(0);
            $t->decimal('grand_total', 14, 4)->default(0);
            $t->timestamp('eta')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
