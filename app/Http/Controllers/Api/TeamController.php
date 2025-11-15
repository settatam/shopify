<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamInvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller
{
    public function __construct(
        protected TeamInvitationService $invitationService
    ) {}

    /**
     * Get all team members for shop.
     */
    public function getMembers(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $members = $this->invitationService->getTeamMembers($shop, includeOwner: true);

        return response()->json([
            'members' => $members,
        ]);
    }

    /**
     * Invite team member.
     */
    public function invite(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:owner,admin,manager,staff,readonly',
            'permissions' => 'nullable|array',
            'message' => 'nullable|string|max:1000',
        ]);

        try {
            $invitation = $this->invitationService->invite(
                shop: $request->user()->shop,
                invitedBy: $request->user(),
                email: $request->email,
                role: $request->role,
                permissions: $request->permissions,
                message: $request->message
            );

            return response()->json([
                'message' => 'Invitation sent successfully',
                'invitation' => $invitation->load(['inviter']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk invite team members.
     */
    public function bulkInvite(Request $request): JsonResponse
    {
        $request->validate([
            'invitations' => 'required|array|min:1',
            'invitations.*.email' => 'required|email',
            'invitations.*.role' => 'required|in:owner,admin,manager,staff,readonly',
            'invitations.*.permissions' => 'nullable|array',
            'invitations.*.message' => 'nullable|string|max:1000',
        ]);

        $results = $this->invitationService->bulkInvite(
            shop: $request->user()->shop,
            invitedBy: $request->user(),
            invitations: $request->invitations
        );

        return response()->json([
            'message' => 'Bulk invitation completed',
            'results' => $results,
            'success_count' => count($results['success']),
            'failed_count' => count($results['failed']),
        ]);
    }

    /**
     * Get pending invitations.
     */
    public function getPendingInvitations(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $invitations = $this->invitationService->getPendingInvitations($shop);

        return response()->json([
            'invitations' => $invitations,
        ]);
    }

    /**
     * Get invitation by token.
     */
    public function getInvitation(Request $request, string $token): JsonResponse
    {
        $invitation = $this->invitationService->getByToken($token);

        if (!$invitation) {
            return response()->json([
                'error' => 'Invitation not found',
            ], 404);
        }

        if (!$invitation->canBeAccepted()) {
            return response()->json([
                'error' => 'This invitation has expired or is no longer valid',
                'status' => $invitation->status,
                'expired' => $invitation->isExpired(),
            ], 400);
        }

        return response()->json([
            'invitation' => $invitation,
        ]);
    }

    /**
     * Accept invitation (create new account).
     */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user = $this->invitationService->accept(
                token: $token,
                userData: [
                    'name' => $request->name,
                    'password' => $request->password,
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );

            // Generate auth token
            $authToken = $user->createToken('team-member-access')->plainTextToken;

            return response()->json([
                'message' => 'Invitation accepted successfully',
                'user' => $user,
                'token' => $authToken,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Accept invitation for existing user.
     */
    public function acceptInvitationExisting(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'User not authenticated',
            ], 401);
        }

        try {
            $updatedUser = $this->invitationService->acceptExistingUser(
                token: $token,
                user: $user,
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'message' => 'Invitation accepted successfully',
                'user' => $updatedUser,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Resend invitation.
     */
    public function resendInvitation(Request $request, TeamInvitation $invitation): JsonResponse
    {
        $this->authorize('update', $invitation);

        try {
            $newInvitation = $this->invitationService->resend($invitation);

            return response()->json([
                'message' => 'Invitation resent successfully',
                'invitation' => $newInvitation->load(['inviter']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Revoke invitation.
     */
    public function revokeInvitation(Request $request, TeamInvitation $invitation): JsonResponse
    {
        $this->authorize('delete', $invitation);

        try {
            $this->invitationService->revoke($invitation);

            return response()->json([
                'message' => 'Invitation revoked successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove team member.
     */
    public function removeMember(Request $request, User $member): JsonResponse
    {
        $this->authorize('delete', $member);

        try {
            $this->invitationService->removeMember($member);

            return response()->json([
                'message' => 'Team member removed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update team member role.
     */
    public function updateMemberRole(Request $request, User $member): JsonResponse
    {
        $this->authorize('update', $member);

        $request->validate([
            'role' => 'required|in:owner,admin,manager,staff,readonly',
            'permissions' => 'nullable|array',
        ]);

        try {
            $this->invitationService->updateMemberRole(
                member: $member,
                role: $request->role,
                permissions: $request->permissions
            );

            return response()->json([
                'message' => 'Team member role updated successfully',
                'member' => $member->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get team statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $stats = $this->invitationService->getStatistics($shop);

        return response()->json([
            'statistics' => $stats,
        ]);
    }

    /**
     * Get available roles and permissions.
     */
    public function getRoles(Request $request): JsonResponse
    {
        $roles = [
            'owner' => [
                'name' => 'Owner',
                'description' => 'Full access to all features including billing and team management',
                'permissions' => TeamInvitation::getRolePermissions('owner'),
            ],
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Full access except billing',
                'permissions' => TeamInvitation::getRolePermissions('admin'),
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Can manage products, orders, and inventory',
                'permissions' => TeamInvitation::getRolePermissions('manager'),
            ],
            'staff' => [
                'name' => 'Staff',
                'description' => 'Can manage orders and view products',
                'permissions' => TeamInvitation::getRolePermissions('staff'),
            ],
            'readonly' => [
                'name' => 'Read Only',
                'description' => 'Can view data but cannot make changes',
                'permissions' => TeamInvitation::getRolePermissions('readonly'),
            ],
        ];

        return response()->json([
            'roles' => $roles,
        ]);
    }
}
