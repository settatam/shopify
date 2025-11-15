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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // Matches notification_templates.event_type
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false); // Future: SMS notifications
            $table->boolean('push_enabled')->default(false); // Future: Push notifications
            $table->boolean('in_app_enabled')->default(true); // In-app notifications
            $table->json('settings')->nullable(); // Additional preferences (frequency, digest, etc.)
            $table->timestamps();

            $table->unique(['user_id', 'event_type']);
            $table->index(['shop_id', 'user_id']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
