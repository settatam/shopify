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
        Schema::table('users', function (Blueprint $table) {
            // Check if columns don't already exist
            if (!Schema::hasColumn('users', 'is_team_member')) {
                $table->boolean('is_team_member')->default(false)->after('role');
            }

            if (!Schema::hasColumn('users', 'invited_by')) {
                $table->foreignId('invited_by')->nullable()->constrained('users')->onDelete('set null')->after('is_team_member');
            }

            if (!Schema::hasColumn('users', 'permissions')) {
                $table->json('permissions')->nullable()->after('invited_by');
            }

            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            }

            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->ipAddress('last_login_ip')->nullable()->after('last_login_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['is_team_member', 'invited_by', 'permissions', 'last_login_at', 'last_login_ip'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    if ($column === 'invited_by') {
                        $table->dropForeign(['invited_by']);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
