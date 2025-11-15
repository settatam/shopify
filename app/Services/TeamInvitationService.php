<?php

namespace App\Services;

use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\Shop;
use App\Jobs\SendTeamInvitationJob;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeamInvitationService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Send invitation to team member.
     *
     * @param Shop $shop
     * @param User $invitedBy
     * @param string $email
     * @param string $role
     * @param array|null $permissions
     * @param string|null $message
     * @return TeamInvitation
     * @throws \Exception
     */
    public function invite(
        Shop $shop,
        User $invitedBy,
        string $email,
        string $role = 'staff',
        ?array $permissions = null,
        ?string $message = null
    ): TeamInvitation {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email address');
        }

        // Check if user already exists in this shop
        $existingUser = User::where('shop_id', $shop->id)
            ->where('email', $email)
            ->first();

        if ($existingUser) {
            throw new \Exception('User with this email already exists in your team');
        }

        // Check for pending invitation
        $pendingInvitation = TeamInvitation::forShop($shop->id)
            ->forEmail($email)
            ->pending()
            ->first();

        if ($pendingInvitation) {
            throw new \Exception('An invitation has already been sent to this email');
        }

        // Validate role
        $validRoles = ['owner', 'admin', 'manager', 'staff', 'readonly'];
        if (!in_array($role, $validRoles)) {
            throw new \Exception('Invalid role');
        }

        // Create invitation
        $invitation = TeamInvitation::create([
            'shop_id' => $shop->id,
            'invited_by' => $invitedBy->id,
            'email' => $email,
            'role' => $role,
            'permissions' => $permissions ?? TeamInvitation::getRolePermissions($role),
            'message' => $message,
            'expires_at' => now()->addDays(7),
        ]);

        // Queue invitation email
        SendTeamInvitationJob::dispatch($invitation)->onQueue('notifications');

        Log::info('Team invitation sent', [
            'shop_id' => $shop->id,
            'invited_by' => $invitedBy->id,
            'email' => $email,
            'role' => $role,
            'invitation_id' => $invitation->id,
        ]);

        return $invitation;
    }

    /**
     * Bulk invite multiple team members.
     *
     * @param Shop $shop
     * @param User $invitedBy
     * @param array $invitations Array of ['email', 'role', 'message']
     * @return array
     */
    public function bulkInvite(Shop $shop, User $invitedBy, array $invitations): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($invitations as $invitationData) {
            try {
                $invitation = $this->invite(
                    $shop,
                    $invitedBy,
                    $invitationData['email'],
                    $invitationData['role'] ?? 'staff',
                    $invitationData['permissions'] ?? null,
                    $invitationData['message'] ?? null
                );

                $results['success'][] = [
                    'email' => $invitationData['email'],
                    'invitation' => $invitation,
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'email' => $invitationData['email'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Accept invitation and create user account.
     *
     * @param string $token
     * @param array $userData ['name', 'password']
     * @param string|null $ip
     * @param string|null $userAgent
     * @return User
     * @throws \Exception
     */
    public function accept(
        string $token,
        array $userData,
        ?string $ip = null,
        ?string $userAgent = null
    ): User {
        $invitation = TeamInvitation::where('token', $token)->first();

        if (!$invitation) {
            throw new \Exception('Invalid invitation token');
        }

        if (!$invitation->canBeAccepted()) {
            throw new \Exception('This invitation has expired or is no longer valid');
        }

        DB::beginTransaction();

        try {
            // Create user account
            $user = User::create([
                'shop_id' => $invitation->shop_id,
                'name' => $userData['name'],
                'email' => $invitation->email,
                'password' => Hash::make($userData['password']),
                'email_verified_at' => now(),
                'role' => $invitation->role,
                'permissions' => $invitation->permissions,
                'is_team_member' => true,
                'invited_by' => $invitation->invited_by,
                'last_login_at' => now(),
                'last_login_ip' => $ip,
            ]);

            // Mark invitation as accepted
            $invitation->accept($user, $ip, $userAgent);

            // Send welcome notification
            $this->notificationService->send(
                eventType: 'system.team_member_joined',
                user: $invitation->inviter,
                variables: [
                    'member_name' => $user->name,
                    'member_email' => $user->email,
                    'member_role' => $invitation->getRoleDisplayName(),
                    'shop_name' => $invitation->shop->name,
                ],
                metadata: [
                    'user_id' => $user->id,
                    'invitation_id' => $invitation->id,
                ]
            );

            DB::commit();

            Log::info('Team invitation accepted', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'shop_id' => $invitation->shop_id,
            ]);

            return $user;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Accept invitation for existing user.
     *
     * @param string $token
     * @param User $user
     * @param string|null $ip
     * @param string|null $userAgent
     * @return User
     * @throws \Exception
     */
    public function acceptExistingUser(
        string $token,
        User $user,
        ?string $ip = null,
        ?string $userAgent = null
    ): User {
        $invitation = TeamInvitation::where('token', $token)->first();

        if (!$invitation) {
            throw new \Exception('Invalid invitation token');
        }

        if (!$invitation->canBeAccepted()) {
            throw new \Exception('This invitation has expired or is no longer valid');
        }

        if ($user->email !== $invitation->email) {
            throw new \Exception('This invitation was sent to a different email address');
        }

        DB::beginTransaction();

        try {
            // Update existing user
            $user->update([
                'shop_id' => $invitation->shop_id,
                'role' => $invitation->role,
                'permissions' => $invitation->permissions,
                'is_team_member' => true,
                'invited_by' => $invitation->invited_by,
            ]);

            // Mark invitation as accepted
            $invitation->accept($user, $ip, $userAgent);

            DB::commit();

            Log::info('Existing user accepted team invitation', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'shop_id' => $invitation->shop_id,
            ]);

            return $user;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Resend invitation.
     *
     * @param TeamInvitation $invitation
     * @return TeamInvitation
     * @throws \Exception
     */
    public function resend(TeamInvitation $invitation): TeamInvitation
    {
        if ($invitation->status === 'accepted') {
            throw new \Exception('Cannot resend accepted invitation');
        }

        // Revoke old invitation
        if ($invitation->status === 'pending') {
            $invitation->revoke();
        }

        // Create new invitation
        $newInvitation = $invitation->resend();

        // Queue invitation email
        SendTeamInvitationJob::dispatch($newInvitation)->onQueue('notifications');

        Log::info('Team invitation resent', [
            'old_invitation_id' => $invitation->id,
            'new_invitation_id' => $newInvitation->id,
            'email' => $newInvitation->email,
        ]);

        return $newInvitation;
    }

    /**
     * Revoke invitation.
     *
     * @param TeamInvitation $invitation
     * @return void
     * @throws \Exception
     */
    public function revoke(TeamInvitation $invitation): void
    {
        $invitation->revoke();

        Log::info('Team invitation revoked', [
            'invitation_id' => $invitation->id,
            'email' => $invitation->email,
        ]);
    }

    /**
     * Remove team member.
     *
     * @param User $member
     * @return void
     * @throws \Exception
     */
    public function removeMember(User $member): void
    {
        if (!$member->is_team_member) {
            throw new \Exception('User is not a team member');
        }

        if ($member->role === 'owner') {
            throw new \Exception('Cannot remove shop owner');
        }

        DB::beginTransaction();

        try {
            // Soft delete or hard delete based on preference
            $member->delete();

            Log::info('Team member removed', [
                'user_id' => $member->id,
                'shop_id' => $member->shop_id,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update team member role.
     *
     * @param User $member
     * @param string $role
     * @param array|null $permissions
     * @return void
     * @throws \Exception
     */
    public function updateMemberRole(User $member, string $role, ?array $permissions = null): void
    {
        if (!$member->is_team_member && $member->role !== 'owner') {
            throw new \Exception('User is not a team member');
        }

        if ($member->role === 'owner' && $role !== 'owner') {
            throw new \Exception('Cannot change owner role');
        }

        $member->update([
            'role' => $role,
            'permissions' => $permissions ?? TeamInvitation::getRolePermissions($role),
        ]);

        Log::info('Team member role updated', [
            'user_id' => $member->id,
            'old_role' => $member->role,
            'new_role' => $role,
        ]);
    }

    /**
     * Get all team members for shop.
     *
     * @param Shop $shop
     * @param bool $includeOwner
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTeamMembers(Shop $shop, bool $includeOwner = true)
    {
        $query = User::where('shop_id', $shop->id);

        if (!$includeOwner) {
            $query->where('role', '!=', 'owner');
        }

        return $query->with('invitedByUser')->get();
    }

    /**
     * Get pending invitations for shop.
     *
     * @param Shop $shop
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingInvitations(Shop $shop)
    {
        return TeamInvitation::forShop($shop->id)
            ->pending()
            ->with(['inviter'])
            ->get();
    }

    /**
     * Get invitation by token.
     *
     * @param string $token
     * @return TeamInvitation|null
     */
    public function getByToken(string $token): ?TeamInvitation
    {
        return TeamInvitation::where('token', $token)
            ->with(['shop', 'inviter'])
            ->first();
    }

    /**
     * Mark expired invitations.
     *
     * @return int Number of invitations marked as expired
     */
    public function markExpiredInvitations(): int
    {
        $expired = TeamInvitation::expired()->get();

        foreach ($expired as $invitation) {
            $invitation->markExpired();
        }

        Log::info('Marked expired invitations', [
            'count' => $expired->count(),
        ]);

        return $expired->count();
    }

    /**
     * Get team statistics for shop.
     *
     * @param Shop $shop
     * @return array
     */
    public function getStatistics(Shop $shop): array
    {
        return [
            'total_members' => User::where('shop_id', $shop->id)->count(),
            'team_members' => User::where('shop_id', $shop->id)
                ->where('is_team_member', true)
                ->count(),
            'pending_invitations' => TeamInvitation::forShop($shop->id)
                ->pending()
                ->count(),
            'total_invitations_sent' => TeamInvitation::forShop($shop->id)->count(),
            'accepted_invitations' => TeamInvitation::forShop($shop->id)
                ->withStatus('accepted')
                ->count(),
            'rejected_invitations' => TeamInvitation::forShop($shop->id)
                ->withStatus('rejected')
                ->count(),
            'by_role' => User::where('shop_id', $shop->id)
                ->select('role', DB::raw('count(*) as count'))
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray(),
        ];
    }
}
