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
        Schema::create('return_shipping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');

            // Shipping Provider
            $table->enum('provider', [
                'shipstation',
                'ups',
                'usps',
                'fedex',
                'dhl',
                'canada_post',
                'royal_mail',
                'other'
            ])->default('shipstation');

            // Label Information
            $table->string('label_id')->nullable(); // Provider's label ID
            $table->string('tracking_number')->nullable()->index();
            $table->string('carrier_code')->nullable();
            $table->string('service_code')->nullable(); // ground, express, etc.
            $table->string('label_url')->nullable();
            $table->string('label_pdf_url')->nullable();
            $table->text('label_base64')->nullable(); // Store label directly

            // Shipping Costs
            $table->decimal('label_cost', 10, 2)->nullable();
            $table->decimal('insurance_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->boolean('customer_pays')->default(true);

            // Package Details
            $table->decimal('weight_oz', 10, 2)->nullable();
            $table->decimal('length_in', 10, 2)->nullable();
            $table->decimal('width_in', 10, 2)->nullable();
            $table->decimal('height_in', 10, 2)->nullable();

            // Addresses
            $table->json('from_address'); // Customer's address
            $table->json('to_address');   // Return warehouse address

            // Status
            $table->enum('status', [
                'label_created',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed_delivery',
                'returned_to_sender',
                'cancelled'
            ])->default('label_created');

            // Tracking Events
            $table->json('tracking_events')->nullable();
            $table->timestamp('last_tracking_update')->nullable();
            $table->timestamp('estimated_delivery_date')->nullable();
            $table->timestamp('actual_delivery_date')->nullable();

            // Label Generation
            $table->timestamp('label_generated_at')->nullable();
            $table->foreignId('generated_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('label_printed')->default(false);
            $table->timestamp('label_printed_at')->nullable();

            // Voiding/Cancellation
            $table->boolean('voided')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('void_reason')->nullable();

            // Provider Response
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['return_request_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_shipping');
    }
};
