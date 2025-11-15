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
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->string('email'); // Email of invitee
            $table->string('token')->unique(); // Unique invitation token
            $table->enum('role', ['owner', 'admin', 'manager', 'staff', 'readonly'])->default('staff');
            $table->json('permissions')->nullable(); // Custom permissions
            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired', 'revoked'])->default('pending');
            $table->text('message')->nullable(); // Personal message from inviter
            $table->timestamp('expires_at'); // Expiration timestamp (default 7 days)
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // User created after acceptance
            $table->ipAddress('accepted_ip')->nullable();
            $table->string('accepted_user_agent')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'email']);
            $table->index('token');
            $table->index('email');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
