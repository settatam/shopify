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
        Schema::create('channel_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('merchant_location_key'); // eBay unique key
            $table->string('name')->nullable();
            $table->json('address_json')->nullable();
            $table->json('geo_json')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['channel_id','merchant_location_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_locations');
    }
};
