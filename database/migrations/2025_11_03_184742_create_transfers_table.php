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
        Schema::create('transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $t->foreignId('from_location_id')->constrained('locations');
            $t->foreignId('to_location_id')->constrained('locations');
            $t->string('status')->default('draft'); // draft, in_transit, received, cancelled
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
