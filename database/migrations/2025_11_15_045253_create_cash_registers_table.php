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
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('set null');
            $table->string('name'); // e.g., "Main Register", "Register 1"
            $table->string('status')->default('closed'); // open, closed
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->decimal('current_balance', 10, 2)->default(0);
            $table->decimal('expected_balance', 10, 2)->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->json('settings')->nullable(); // Register-specific settings
            $table->timestamps();

            $table->index(['shop_id', 'status']);
        });

        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('cash_register_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('set null');
            $table->string('transaction_number')->unique();
            $table->string('payment_method'); // cash, check
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('amount_tendered', 10, 2)->nullable(); // Amount given by customer
            $table->decimal('change_given', 10, 2)->nullable(); // Change returned
            $table->string('check_number')->nullable(); // For check payments
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('line_items'); // Products sold
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('completed'); // completed, voided, refunded
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'created_at']);
            $table->index('cash_register_id');
        });

        Schema::create('cash_drawer_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('cash_register_id')->constrained()->onDelete('cascade');
            $table->string('type'); // sale, cash_in, cash_out, opening, closing, adjustment
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('payment_method')->nullable(); // cash, check (for sales)
            $table->foreignId('pos_transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->text('notes')->nullable();
            $table->string('reference')->nullable(); // External reference number
            $table->timestamps();

            $table->index(['cash_register_id', 'type']);
            $table->index(['cash_register_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_activities');
        Schema::dropIfExists('pos_transactions');
        Schema::dropIfExists('cash_registers');
    }
};
