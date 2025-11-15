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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // order.placed, inventory.low_stock, return.approved, etc.
            $table->string('name'); // Human-readable name
            $table->string('subject'); // Email subject with variables
            $table->text('body_html'); // HTML email body
            $table->text('body_text')->nullable(); // Plain text version
            $table->json('available_variables')->nullable(); // List of available template variables
            $table->string('from_name')->nullable(); // Override default from name
            $table->string('from_email')->nullable(); // Override default from email
            $table->string('reply_to')->nullable(); // Reply-to email
            $table->boolean('is_active')->default(true); // Enable/disable template
            $table->boolean('is_system')->default(false); // System templates can't be deleted
            $table->enum('category', ['transactional', 'marketing', 'system'])->default('transactional');
            $table->json('settings')->nullable(); // Additional settings (attachments, CC, BCC, etc.)
            $table->timestamps();

            $table->index(['shop_id', 'event_type']);
            $table->index(['shop_id', 'is_active']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
