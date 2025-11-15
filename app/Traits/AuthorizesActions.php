<?php

namespace App\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for common authorization patterns in controllers.
 *
 * This trait provides convenient methods for authorizing actions
 * with shop-scoping and permission checks.
 */
trait AuthorizesActions
{
    /**
     * Authorize that user belongs to the same shop as the model.
     *
     * @throws AuthorizationException
     */
    protected function authorizeShopOwnership(Model $model): void
    {
        if (!property_exists($model, 'shop_id')) {
            return;
        }

        $user = auth()->user();

        if (!$user || $user->shop_id !== $model->shop_id) {
            throw new AuthorizationException('You do not have access to this resource.');
        }
    }

    /**
     * Authorize that user has a specific permission.
     *
     * @throws AuthorizationException
     */
    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();

        if (!$user || !$user->hasPermission($permission)) {
            throw new AuthorizationException(
                "You do not have the required permission: {$permission}"
            );
        }
    }

    /**
     * Authorize that user has any of the specified permissions.
     *
     * @throws AuthorizationException
     */
    protected function authorizeAnyPermission(array $permissions): void
    {
        $user = auth()->user();

        if (!$user || !$user->hasAnyPermission($permissions)) {
            throw new AuthorizationException(
                'You do not have any of the required permissions: ' . implode(', ', $permissions)
            );
        }
    }

    /**
     * Authorize that user has all of the specified permissions.
     *
     * @throws AuthorizationException
     */
    protected function authorizeAllPermissions(array $permissions): void
    {
        $user = auth()->user();

        if (!$user || !$user->hasAllPermissions($permissions)) {
            throw new AuthorizationException(
                'You do not have all the required permissions: ' . implode(', ', $permissions)
            );
        }
    }

    /**
     * Authorize that user has a specific role.
     *
     * @throws AuthorizationException
     */
    protected function authorizeRole(string $role): void
    {
        $user = auth()->user();

        if (!$user || $user->role !== $role) {
            throw new AuthorizationException(
                "You must be a {$role} to perform this action."
            );
        }
    }

    /**
     * Authorize that user has any of the specified roles.
     *
     * @throws AuthorizationException
     */
    protected function authorizeAnyRole(array $roles): void
    {
        $user = auth()->user();

        if (!$user || !in_array($user->role, $roles)) {
            throw new AuthorizationException(
                'You must have one of the following roles: ' . implode(', ', $roles)
            );
        }
    }

    /**
     * Authorize that user is an admin or owner.
     *
     * @throws AuthorizationException
     */
    protected function authorizeAdmin(): void
    {
        $user = auth()->user();

        if (!$user || !$user->isAdmin()) {
            throw new AuthorizationException('You must be an admin or owner to perform this action.');
        }
    }

    /**
     * Authorize that user is the owner.
     *
     * @throws AuthorizationException
     */
    protected function authorizeOwner(): void
    {
        $user = auth()->user();

        if (!$user || !$user->isOwner()) {
            throw new AuthorizationException('Only the shop owner can perform this action.');
        }
    }

    /**
     * Authorize that user can view the model.
     *
     * @throws AuthorizationException
     */
    protected function authorizeView(Model $model): void
    {
        $this->authorize('view', $model);
    }

    /**
     * Authorize that user can update the model.
     *
     * @throws AuthorizationException
     */
    protected function authorizeUpdate(Model $model): void
    {
        $this->authorize('update', $model);
    }

    /**
     * Authorize that user can delete the model.
     *
     * @throws AuthorizationException
     */
    protected function authorizeDelete(Model $model): void
    {
        $this->authorize('delete', $model);
    }

    /**
     * Check if user belongs to the same shop as the model.
     */
    protected function belongsToSameShop(Model $model): bool
    {
        if (!property_exists($model, 'shop_id')) {
            return true;
        }

        $user = auth()->user();

        return $user && $user->shop_id === $model->shop_id;
    }

    /**
     * Check if user has a specific permission.
     */
    protected function hasPermission(string $permission): bool
    {
        $user = auth()->user();

        return $user && $user->hasPermission($permission);
    }

    /**
     * Check if user has any of the specified permissions.
     */
    protected function hasAnyPermission(array $permissions): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyPermission($permissions);
    }

    /**
     * Check if user has all of the specified permissions.
     */
    protected function hasAllPermissions(array $permissions): bool
    {
        $user = auth()->user();

        return $user && $user->hasAllPermissions($permissions);
    }

    /**
     * Check if user has a specific role.
     */
    protected function hasRole(string $role): bool
    {
        $user = auth()->user();

        return $user && $user->role === $role;
    }

    /**
     * Check if user is an admin or owner.
     */
    protected function isAdmin(): bool
    {
        $user = auth()->user();

        return $user && $user->isAdmin();
    }

    /**
     * Check if user is the owner.
     */
    protected function isOwner(): bool
    {
        $user = auth()->user();

        return $user && $user->isOwner();
    }
}
