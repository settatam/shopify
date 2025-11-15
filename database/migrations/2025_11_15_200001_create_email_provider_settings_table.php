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
        Schema::create('email_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');

            // Provider info
            $table->enum('provider', ['sendgrid', 'mailchimp', 'ses', 'mailgun', 'postmark'])->default('sendgrid');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_primary')->default(false); // Primary provider for transactional emails

            // API credentials (encrypted)
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->string('server_prefix')->nullable(); // For Mailchimp (us1, us2, etc.)

            // SendGrid specific
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();

            // Mailchimp specific
            $table->string('default_audience_id')->nullable();
            $table->json('audience_ids')->nullable(); // Multiple audiences
            $table->boolean('double_optin')->default(true);

            // Settings and configuration
            $table->json('settings')->nullable();
            $table->json('webhooks')->nullable();

            // Stats
            $table->integer('emails_sent_today')->default(0);
            $table->integer('emails_sent_month')->default(0);
            $table->integer('total_emails_sent')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_email_sent_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['shop_id', 'provider']);
            $table->index(['shop_id', 'is_active']);
            $table->index(['shop_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_provider_settings');
    }
};
