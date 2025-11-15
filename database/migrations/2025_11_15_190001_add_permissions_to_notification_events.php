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
        Schema::table('notification_events', function (Blueprint $table) {
            $table->string('required_permission')->nullable()->after('category');
            $table->index('required_permission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_events', function (Blueprint $table) {
            $table->dropIndex(['required_permission']);
            $table->dropColumn('required_permission');
        });
    }
};
