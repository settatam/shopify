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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('return_request_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('channel_order_id')->nullable()->constrained()->onDelete('set null');
            $table->string('refund_number')->unique();
            $table->string('order_number')->index();

            // Refund Type
            $table->enum('refund_type', [
                'full',                // Full refund
                'partial',             // Partial refund
                'shipping_only',       // Only shipping cost
                'tax_only',            // Only tax
                'custom'               // Custom amount
            ]);

            // Amounts
            $table->decimal('items_refund', 10, 2)->default(0);
            $table->decimal('shipping_refund', 10, 2)->default(0);
            $table->decimal('tax_refund', 10, 2)->default(0);
            $table->decimal('restocking_fee', 10, 2)->default(0);
            $table->decimal('total_refund', 10, 2);

            // Payment Method
            $table->enum('refund_method', [
                'original_payment',    // Refund to original payment method
                'store_credit',        // Issue store credit
                'cash',                // Cash refund (POS)
                'check',               // Mail check
                'bank_transfer',       // Direct bank transfer
                'paypal',              // PayPal refund
                'manual'               // Manual/other
            ])->default('original_payment');

            // Original Payment Information
            $table->string('original_payment_method')->nullable();
            $table->string('original_transaction_id')->nullable();
            $table->string('payment_gateway')->nullable(); // stripe, square, paypal, etc.

            // Status
            $table->enum('status', [
                'pending',             // Refund initiated
                'processing',          // Being processed
                'completed',           // Refund successful
                'failed',              // Refund failed
                'cancelled',           // Refund cancelled
                'on_hold'              // On hold for review
            ])->default('pending');

            // Processing Details
            $table->string('gateway_refund_id')->nullable(); // Refund ID from payment gateway
            $table->text('gateway_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by_id')->nullable()->constrained('users')->onDelete('set null');

            // Failure Information
            $table->text('failure_reason')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();

            // Store Credit (if applicable)
            $table->string('store_credit_code')->nullable();
            $table->timestamp('store_credit_expires_at')->nullable();

            // Customer Information
            $table->string('customer_email');
            $table->string('customer_name')->nullable();

            // Channel Integration
            $table->foreignId('channel_id')->nullable()->constrained()->onDelete('set null');
            $table->string('channel_refund_id')->nullable(); // Refund ID on the channel (eBay, Amazon, etc.)
            $table->boolean('synced_to_channel')->default(false);
            $table->timestamp('synced_to_channel_at')->nullable();

            // Notifications
            $table->boolean('customer_notified')->default(false);
            $table->timestamp('customer_notified_at')->nullable();

            // Reason & Notes
            $table->text('refund_reason')->nullable();
            $table->text('internal_notes')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

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
        Schema::dropIfExists('refunds');
    }
};
