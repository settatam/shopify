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
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->unique(); // Unique event identifier
            $table->string('name'); // Human-readable name
            $table->text('description')->nullable();
            $table->enum('category', ['orders', 'inventory', 'returns', 'channels', 'system', 'marketing']);
            $table->json('available_variables'); // List of variables available for this event
            $table->boolean('is_system')->default(true); // System events
            $table->boolean('enabled_by_default')->default(true); // Default preference for new users
            $table->json('settings')->nullable(); // Event-specific settings
            $table->timestamps();

            $table->index('event_type');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
