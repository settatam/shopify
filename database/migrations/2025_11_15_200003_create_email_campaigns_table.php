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
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            // Provider info
            $table->enum('provider', ['mailchimp', 'sendgrid', 'custom'])->default('mailchimp');
            $table->string('provider_campaign_id')->nullable()->index(); // External campaign ID

            // Campaign details
            $table->string('name');
            $table->string('subject');
            $table->text('preview_text')->nullable();
            $table->text('content_html')->nullable();
            $table->text('content_text')->nullable();

            // Targeting
            $table->string('audience_id')->nullable(); // Mailchimp audience/list ID
            $table->json('segment_criteria')->nullable(); // Filtering criteria
            $table->integer('recipient_count')->default(0);

            // Sending configuration
            $table->string('from_email');
            $table->string('from_name');
            $table->string('reply_to')->nullable();

            // Status
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            // Analytics
            $table->integer('emails_sent')->default(0);
            $table->integer('opens')->default(0);
            $table->integer('unique_opens')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('unique_clicks')->default(0);
            $table->integer('bounces')->default(0);
            $table->integer('unsubscribes')->default(0);
            $table->decimal('open_rate', 5, 2)->default(0); // Percentage
            $table->decimal('click_rate', 5, 2)->default(0); // Percentage

            // Metadata
            $table->json('settings')->nullable();
            $table->json('tracking_options')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'provider']);
            $table->index(['shop_id', 'created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
    }
};
