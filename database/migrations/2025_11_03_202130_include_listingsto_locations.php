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
        Schema::table('channels', function (Blueprint $table) {
            if (!Schema::hasColumn('channels','included_location_ids')) {
                $table->json('included_location_ids')->nullable()->after('sandbox');
            }
            if (!Schema::hasColumn('channels','safety_stock')) {
                $table->unsignedInteger('safety_stock')->default(0)->after('included_location_ids');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            //
        });
    }
};
