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
        Schema::create('mailchimp_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Mailchimp data
            $table->string('mailchimp_id')->nullable(); // Subscriber hash from Mailchimp
            $table->string('email')->index();
            $table->string('audience_id')->index();

            // Subscriber info
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();

            // Status
            $table->enum('status', ['subscribed', 'unsubscribed', 'pending', 'cleaned', 'transactional'])->default('subscribed');
            $table->json('tags')->nullable();
            $table->json('merge_fields')->nullable(); // Custom fields

            // Marketing preferences
            $table->boolean('email_marketing')->default(true);
            $table->boolean('sms_marketing')->default(false);

            // Sync tracking
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->nullable(); // 'pending', 'synced', 'error'
            $table->text('sync_error')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['shop_id', 'email']);
            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'audience_id']);
            $table->unique(['shop_id', 'email', 'audience_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailchimp_subscribers');
    }
};
