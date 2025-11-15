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
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_order_id')->nullable()->constrained()->onDelete('set null');
            $table->string('rma_number')->unique(); // Return Merchandise Authorization number
            $table->string('order_number')->index();
            $table->foreignId('channel_id')->nullable()->constrained()->onDelete('set null');
            $table->string('external_order_id')->nullable(); // External order ID from marketplace (eBay, Amazon, etc.)

            // Customer Information
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();

            // Return Details
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
            $table->json('images')->nullable(); // Customer-uploaded images of issue

            // Status Workflow
            $table->enum('status', [
                'requested',           // Customer initiated return
                'pending_approval',    // Awaiting merchant review
                'approved',            // Return approved
                'rejected',            // Return rejected
                'label_generated',     // Return shipping label created
                'in_transit',          // Package in transit to merchant
                'received',            // Package received by merchant
                'inspecting',          // Items being inspected
                'completed',           // Return processed
                'cancelled'            // Return cancelled
            ])->default('requested');

            // Return Type
            $table->enum('return_type', [
                'refund',              // Customer wants refund
                'exchange',            // Customer wants exchange
                'store_credit'         // Customer wants store credit
            ])->default('refund');

            // Amounts
            $table->decimal('items_subtotal', 10, 2);
            $table->decimal('shipping_paid', 10, 2)->default(0);
            $table->decimal('tax_paid', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2);
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->decimal('restocking_fee', 10, 2)->default(0);
            $table->boolean('refund_shipping')->default(false);

            // Policy & Approval
            $table->boolean('requires_approval')->default(true);
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Return Shipping
            $table->text('return_address')->nullable(); // Where to send items
            $table->string('return_carrier')->nullable(); // UPS, USPS, FedEx, etc.
            $table->string('return_tracking_number')->nullable();
            $table->string('return_label_url')->nullable();
            $table->decimal('return_shipping_cost', 10, 2)->nullable();
            $table->boolean('customer_pays_return_shipping')->default(true);

            // Inspection & Processing
            $table->foreignId('inspected_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('inspected_at')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->enum('inspection_result', [
                'approved',
                'partial_approval',
                'rejected'
            ])->nullable();

            // Completion
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();

            // Deadlines
            $table->timestamp('return_by_date')->nullable(); // Deadline for customer to ship
            $table->timestamp('received_at')->nullable(); // When package was received

            // Notifications
            $table->timestamp('customer_notified_at')->nullable();
            $table->json('notification_history')->nullable();

            // Metadata
            $table->json('custom_fields')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'created_at']);
            $table->index('customer_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
