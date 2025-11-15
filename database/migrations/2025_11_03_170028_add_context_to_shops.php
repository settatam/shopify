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
        Schema::table('shops', function (Blueprint $t) {
            $t->string('host_type')->default('shopify'); // shopify | square | custom
            $t->string('host_merchant_id')->nullable()->index(); // shop domain or Square merchant_id
            $t->string('host_display_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            //
            $table->dropColumn('host_type');
            $table->dropColumn('host_merchant_id');
            $table->dropColumn('host_display_name');
        });
    }
};
