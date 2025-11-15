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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('notification_template_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type'); // order.placed, inventory.low_stock, etc.
            $table->enum('channel', ['email', 'sms', 'push', 'in_app'])->default('email');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('subject')->nullable(); // Actual subject sent
            $table->text('body')->nullable(); // Actual body sent (for debugging)
            $table->json('variables')->nullable(); // Variables used in template
            $table->json('metadata')->nullable(); // Related entity IDs (order_id, product_id, etc.)
            $table->enum('status', [
                'queued',      // Queued for sending
                'sending',     // Currently being sent
                'sent',        // Successfully sent
                'delivered',   // Confirmed delivered (webhook)
                'opened',      // Email opened (tracking pixel)
                'clicked',     // Link clicked (tracking)
                'bounced',     // Hard or soft bounce
                'failed',      // Failed to send
                'rejected',    // Rejected by provider (spam, invalid, etc.)
                'unsubscribed' // User unsubscribed
            ])->default('queued');
            $table->text('error_message')->nullable(); // Error details if failed
            $table->string('provider')->nullable(); // Email provider used (smtp, sendgrid, ses, etc.)
            $table->string('provider_message_id')->nullable(); // Provider's message ID for tracking
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('retry_count')->default(0); // Number of retry attempts
            $table->timestamp('next_retry_at')->nullable(); // When to retry if failed
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'user_id']);
            $table->index(['shop_id', 'event_type']);
            $table->index('recipient_email');
            $table->index('provider_message_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
