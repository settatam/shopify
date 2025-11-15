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
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('variant_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('channel_order_item_id')->nullable()->constrained()->onDelete('set null');

            // Item Details
            $table->string('sku')->nullable();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->integer('quantity_ordered');
            $table->integer('quantity_returned');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);

            // Return Specific Details
            $table->enum('return_reason', [
                'defective',
                'wrong_item',
                'not_as_described',
                'damaged_in_shipping',
                'changed_mind',
                'size_fit_issue',
                'quality_issue',
                'missing_parts',
                'arrived_late',
                'duplicate_order',
                'other'
            ]);
            $table->text('return_reason_details')->nullable();

            // Inspection
            $table->enum('condition_received', [
                'new_unopened',
                'new_opened',
                'lightly_used',
                'heavily_used',
                'damaged',
                'defective',
                'missing_parts'
            ])->nullable();
            $table->text('inspection_notes')->nullable();

            // Disposition - What to do with returned item
            $table->enum('disposition', [
                'restock',             // Put back in inventory
                'restock_as_used',     // Put in inventory as used/open box
                'refurbish',           // Send for refurbishing
                'dispose',             // Throw away
                'return_to_vendor',    // Send back to supplier
                'quarantine',          // Hold for further review
                'pending'              // Not yet decided
            ])->default('pending');

            // Restocking
            $table->boolean('restocked')->default(false);
            $table->timestamp('restocked_at')->nullable();
            $table->foreignId('restocked_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('set null'); // Where restocked

            // Refund Information
            $table->boolean('refundable')->default(true);
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->decimal('restocking_fee', 10, 2)->default(0);
            $table->text('refund_notes')->nullable();

            // Exchange Information (if applicable)
            $table->foreignId('exchange_variant_id')->nullable()->constrained('variants')->onDelete('set null');
            $table->integer('exchange_quantity')->nullable();
            $table->boolean('exchange_fulfilled')->default(false);
            $table->timestamp('exchange_fulfilled_at')->nullable();

            // Images
            $table->json('images')->nullable(); // Customer or staff photos of item

            // Metadata
            $table->json('custom_fields')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['return_request_id', 'product_id']);
            $table->index('disposition');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
