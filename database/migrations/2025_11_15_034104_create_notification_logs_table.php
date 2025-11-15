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
            $table->string('channel'); // sms, whatsapp
            $table->string('to');
            $table->string('from')->nullable();
            $table->text('message');
            $table->string('template_key')->nullable();
            $table->json('template_variables')->nullable();
            $table->string('message_sid')->nullable();
            $table->string('status')->default('queued'); // queued, sent, delivered, failed, undelivered
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->decimal('price', 10, 4)->nullable();
            $table->string('price_unit')->nullable();
            $table->string('related_type')->nullable(); // e.g., 'order', 'product', 'user'
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
            $table->index(['shop_id', 'status']);
            $table->index(['message_sid']);
            $table->index(['related_type', 'related_id']);
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
