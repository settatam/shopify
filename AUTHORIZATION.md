# Authorization System

This document describes the comprehensive role-based access control (RBAC) and permission-based authorization system implemented in the application.

## Table of Contents

- [Overview](#overview)
- [Roles and Permissions](#roles-and-permissions)
- [Policies](#policies)
- [Middleware](#middleware)
- [Helper Trait](#helper-trait)
- [Usage Examples](#usage-examples)
- [Best Practices](#best-practices)

---

## Overview

The authorization system provides multi-layered access control to ensure users can only perform actions they're authorized for based on their:

1. **Role** - Owner, Admin, Manager, Staff, or Read Only
2. **Permissions** - Specific capabilities granted to the role
3. **Shop Ownership** - Users can only access data belonging to their shop
4. **Resource Ownership** - Additional checks for specific resources

### Authorization Layers

1. **Middleware** - Route-level protection using `permission` and `role` middleware
2. **Policies** - Model-level authorization using Laravel Policies
3. **Helper Methods** - Convenience methods in User model and AuthorizesActions trait

---

## Roles and Permissions

### Available Roles

| Role | Description | Key Characteristics |
|------|-------------|---------------------|
| `owner` | Shop Owner | Full access to all features including billing and team management |
| `admin` | Administrator | Full access except billing |
| `manager` | Manager | Can manage products, orders, inventory, and channels |
| `staff` | Staff Member | Can manage orders and view products |
| `readonly` | Read Only | Can view data but cannot make changes |

### Available Permissions

| Permission | Description | Default Roles |
|------------|-------------|---------------|
| `manage_team` | Invite, remove, and manage team members | Owner, Admin |
| `manage_billing` | Access and manage billing information | Owner |
| `manage_shop` | Update shop settings and configuration | Owner, Admin |
| `manage_products` | Create, update, delete products | Owner, Admin, Manager |
| `manage_orders` | Create, update, fulfill, cancel orders | Owner, Admin, Manager, Staff |
| `manage_inventory` | Adjust, transfer, reserve inventory | Owner, Admin, Manager |
| `manage_channels` | Connect, disconnect, sync channels | Owner, Admin, Manager |
| `view_reports` | View analytics and reports | Owner, Admin, Manager, Staff, Read Only |

### Role-Permission Mapping

```php
'owner' => [
    'manage_team',
    'manage_billing',
    'manage_shop',
    'manage_products',
    'manage_orders',
    'manage_inventory',
    'manage_channels',
    'view_reports',
],
'admin' => [
    'manage_team',
    'manage_shop',
    'manage_products',
    'manage_orders',
    'manage_inventory',
    'manage_channels',
    'view_reports',
],
'manager' => [
    'manage_products',
    'manage_orders',
    'manage_inventory',
    'manage_channels',
    'view_reports',
],
'staff' => [
    'manage_orders',
    'view_reports',
],
'readonly' => [
    'view_reports',
],
```

---

## Policies

Laravel Policies provide model-level authorization. All policies implement shop-scoping and permission checks.

### Available Policies

1. **ProductPolicy** - `app/Policies/ProductPolicy.php`
2. **OrderPolicy** - `app/Policies/OrderPolicy.php`
3. **ChannelPolicy** - `app/Policies/ChannelPolicy.php`
4. **InventoryPolicy** - `app/Policies/InventoryPolicy.php`
5. **ShopPolicy** - `app/Policies/ShopPolicy.php`
6. **UserPolicy** - `app/Policies/UserPolicy.php`
7. **TeamInvitationPolicy** - `app/Policies/TeamInvitationPolicy.php`
8. **NotificationTemplatePolicy** - `app/Policies/NotificationTemplatePolicy.php`
9. **NotificationLogPolicy** - `app/Policies/NotificationLogPolicy.php`

### Policy Methods

Each policy implements relevant methods from:
- `viewAny` - List all resources
- `view` - View a single resource
- `create` - Create a new resource
- `update` - Update an existing resource
- `delete` - Delete a resource
- `restore` - Restore a soft-deleted resource
- `forceDelete` - Permanently delete a resource

Some policies also have custom methods:
- **ProductPolicy**: `publish` - Publish product to channels
- **OrderPolicy**: `fulfill`, `cancel`, `refund` - Order operations
- **ChannelPolicy**: `connect`, `disconnect`, `sync` - Channel operations
- **InventoryPolicy**: `adjust`, `transfer`, `reserve`, `release` - Inventory operations
- **ShopPolicy**: `manageBilling`, `viewReports` - Shop operations
- **UserPolicy**: `invite`, `updateOwn` - User operations
- **TeamInvitationPolicy**: `resend` - Invitation operations

### Policy Examples

#### ProductPolicy

```php
public function viewAny(User $user): bool
{
    return $user->hasAnyPermission(['manage_products', 'view_products']);
}

public function view(User $user, Product $product): bool
{
    return $user->shop_id === $product->shop_id &&
           $user->hasAnyPermission(['manage_products', 'view_products']);
}

public function update(User $user, Product $product): bool
{
    return $user->shop_id === $product->shop_id &&
           $user->hasPermission('manage_products');
}

public function publish(User $user, Product $product): bool
{
    return $user->shop_id === $product->shop_id &&
           $user->hasAllPermissions(['manage_products', 'manage_channels']);
}
```

#### UserPolicy

```php
public function update(User $user, User $targetUser): bool
{
    // Can't update yourself (use updateOwn for that)
    if ($user->id === $targetUser->id) {
        return false;
    }

    // Can't update an owner unless you're an owner
    if ($targetUser->isOwner() && !$user->isOwner()) {
        return false;
    }

    return $user->shop_id === $targetUser->shop_id &&
           $user->hasPermission('manage_team');
}

public function delete(User $user, User $targetUser): bool
{
    // Can't delete yourself
    if ($user->id === $targetUser->id) {
        return false;
    }

    // Can't delete the owner
    if ($targetUser->isOwner()) {
        return false;
    }

    return $user->shop_id === $targetUser->shop_id &&
           $user->hasPermission('manage_team');
}
```

---

## Middleware

Two middleware classes provide route-level authorization.

### CheckPermission Middleware

Validates that the authenticated user has a specific permission.

**Usage in Routes:**

```php
Route::middleware(['auth:sanctum', 'permission:manage_products'])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
});
```

**Error Response:**

```json
{
    "error": "Forbidden",
    "message": "You do not have permission to perform this action",
    "required_permission": "manage_products",
    "your_permissions": ["manage_orders", "view_reports"]
}
```

### CheckRole Middleware

Validates that the authenticated user has one of the specified roles.

**Usage in Routes:**

```php
Route::middleware(['auth:sanctum', 'role:owner,admin'])->group(function () {
    Route::post('/team/invite', [TeamController::class, 'invite']);
    Route::delete('/team/{member}', [TeamController::class, 'removeMember']);
});
```

**Error Response:**

```json
{
    "error": "Forbidden",
    "message": "You do not have the required role to perform this action",
    "required_roles": ["owner", "admin"],
    "your_role": "staff"
}
```

---

## Helper Trait

The `AuthorizesActions` trait provides convenient methods for common authorization patterns.

### Using the Trait

```php
use App\Traits\AuthorizesActions;

class ProductController extends Controller
{
    use AuthorizesActions;

    public function store(Request $request)
    {
        // Check permission
        $this->authorizePermission('manage_products');

        // ... create product
    }

    public function update(Request $request, Product $product)
    {
        // Check shop ownership
        $this->authorizeShopOwnership($product);

        // Check permission
        $this->authorizePermission('manage_products');

        // ... update product
    }
}
```

### Available Methods

#### Authorization Methods (throw exception if unauthorized)

- `authorizeShopOwnership(Model $model)` - User belongs to same shop as model
- `authorizePermission(string $permission)` - User has specific permission
- `authorizeAnyPermission(array $permissions)` - User has any of the permissions
- `authorizeAllPermissions(array $permissions)` - User has all permissions
- `authorizeRole(string $role)` - User has specific role
- `authorizeAnyRole(array $roles)` - User has any of the roles
- `authorizeAdmin()` - User is admin or owner
- `authorizeOwner()` - User is owner
- `authorizeView(Model $model)` - User can view model
- `authorizeUpdate(Model $model)` - User can update model
- `authorizeDelete(Model $model)` - User can delete model

#### Check Methods (return boolean)

- `belongsToSameShop(Model $model)` - Check shop ownership
- `hasPermission(string $permission)` - Check permission
- `hasAnyPermission(array $permissions)` - Check any permission
- `hasAllPermissions(array $permissions)` - Check all permissions
- `hasRole(string $role)` - Check role
- `isAdmin()` - Check if admin or owner
- `isOwner()` - Check if owner

---

## Usage Examples

### In Controllers

#### Using Laravel's authorize() method

```php
public function update(Request $request, Product $product)
{
    // Uses ProductPolicy@update
    $this->authorize('update', $product);

    $product->update($request->validated());

    return response()->json(['product' => $product]);
}
```

#### Using Helper Trait

```php
use App\Traits\AuthorizesActions;

class OrderController extends Controller
{
    use AuthorizesActions;

    public function fulfill(Request $request, Order $order)
    {
        // Check if belongs to same shop
        $this->authorizeShopOwnership($order);

        // Check if has permission
        $this->authorizePermission('manage_orders');

        $order->fulfill();

        return response()->json(['message' => 'Order fulfilled']);
    }

    public function statistics(Request $request)
    {
        // Only admins can view statistics
        $this->authorizeAdmin();

        $stats = $this->orderService->getStatistics();

        return response()->json(['statistics' => $stats]);
    }
}
```

#### Using Middleware

```php
// In routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {

    // Permission-based routes
    Route::middleware(['permission:manage_products'])->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
    });

    // Role-based routes
    Route::middleware(['role:owner,admin'])->group(function () {
        Route::post('/team/invite', [TeamController::class, 'invite']);
        Route::delete('/users/{user}', [TeamController::class, 'removeMember']);
    });

    // Multiple middleware
    Route::delete('/shop', [ShopController::class, 'destroy'])
        ->middleware(['role:owner', 'permission:manage_billing']);
});
```

### In User Model

```php
// Check single permission
if ($user->hasPermission('manage_products')) {
    // User can manage products
}

// Check multiple permissions (any)
if ($user->hasAnyPermission(['manage_products', 'manage_orders'])) {
    // User can manage products OR orders
}

// Check multiple permissions (all)
if ($user->hasAllPermissions(['manage_products', 'manage_channels'])) {
    // User can manage products AND channels
}

// Check role
if ($user->isAdmin()) {
    // User is admin or owner
}

if ($user->isOwner()) {
    // User is owner
}

// Get all permissions
$permissions = $user->getAllPermissions();
// Returns: ['manage_products', 'manage_orders', ...]
```

### In Blade Views

```blade
@can('update', $product)
    <button>Edit Product</button>
@endcan

@can('delete', $product)
    <button>Delete Product</button>
@endcan

@if(auth()->user()->hasPermission('manage_products'))
    <a href="/products/create">Create Product</a>
@endif

@if(auth()->user()->isAdmin())
    <a href="/admin">Admin Panel</a>
@endif
```

### In Policies

```php
public function publish(User $user, Product $product): bool
{
    // Must belong to same shop
    if ($user->shop_id !== $product->shop_id) {
        return false;
    }

    // Must have both permissions
    return $user->hasAllPermissions(['manage_products', 'manage_channels']);
}
```

---

## Best Practices

### 1. Always Use Shop-Scoping

Every policy should verify the user belongs to the same shop as the resource:

```php
public function view(User $user, Product $product): bool
{
    return $user->shop_id === $product->shop_id &&
           $user->hasPermission('manage_products');
}
```

### 2. Use Policies for Model-Level Authorization

Use `$this->authorize()` in controllers instead of manual checks:

```php
// Good
$this->authorize('update', $product);

// Bad
if (!$user->hasPermission('manage_products') || $user->shop_id !== $product->shop_id) {
    abort(403);
}
```

### 3. Use Middleware for Route-Level Protection

Protect entire route groups with middleware:

```php
Route::middleware(['auth:sanctum', 'permission:manage_products'])->group(function () {
    // All routes here require manage_products permission
});
```

### 4. Check Permissions, Not Roles (When Possible)

```php
// Good - permission-based
if ($user->hasPermission('manage_products')) {
    // ...
}

// Bad - role-based (less flexible)
if ($user->role === 'admin') {
    // ...
}
```

### 5. Handle Special Cases Explicitly

For operations that shouldn't apply to certain users:

```php
public function delete(User $user, User $targetUser): bool
{
    // Can't delete yourself
    if ($user->id === $targetUser->id) {
        return false;
    }

    // Can't delete the owner
    if ($targetUser->isOwner()) {
        return false;
    }

    return $user->hasPermission('manage_team');
}
```

### 6. Use Descriptive Error Messages

Provide clear feedback when authorization fails:

```php
if (!$user->hasPermission('manage_billing')) {
    throw new AuthorizationException(
        'Only users with billing access can view invoices.'
    );
}
```

### 7. Document Custom Policy Methods

Add clear docblocks for custom policy methods:

```php
/**
 * Determine if user can publish product to channels.
 *
 * Requires both manage_products and manage_channels permissions.
 */
public function publish(User $user, Product $product): bool
{
    return $user->shop_id === $product->shop_id &&
           $user->hasAllPermissions(['manage_products', 'manage_channels']);
}
```

### 8. Test Authorization Logic

Always test authorization in your feature tests:

```php
public function test_staff_cannot_delete_products()
{
    $staff = User::factory()->create(['role' => 'staff']);
    $product = Product::factory()->create(['shop_id' => $staff->shop_id]);

    $this->actingAs($staff)
        ->delete("/api/products/{$product->id}")
        ->assertForbidden();
}

public function test_manager_can_update_products()
{
    $manager = User::factory()->create(['role' => 'manager']);
    $product = Product::factory()->create(['shop_id' => $manager->shop_id]);

    $this->actingAs($manager)
        ->put("/api/products/{$product->id}", ['title' => 'Updated'])
        ->assertOk();
}
```

---

## Security Considerations

1. **Never Trust Client-Side Authorization** - Always validate on the server
2. **Always Validate Shop Ownership** - Prevent cross-shop data access
3. **Use Policies Over Manual Checks** - Centralized authorization logic
4. **Log Authorization Failures** - Monitor for potential security issues
5. **Regularly Review Permissions** - Ensure role permissions match business needs
6. **Protect System Resources** - Prevent modification of system templates, owner deletion, etc.

---

## Extending the System

### Adding New Permissions

1. Add permission to `TeamInvitation::getRolePermissions()`:

```php
'new_permission' => [
    'owner' => true,
    'admin' => true,
    'manager' => false,
    'staff' => false,
    'readonly' => false,
],
```

2. Add permission check method to User model (optional):

```php
public function canManageNewFeature(): bool
{
    return $this->hasPermission('new_permission');
}
```

3. Use in policies:

```php
public function manage(User $user, NewModel $model): bool
{
    return $user->shop_id === $model->shop_id &&
           $user->hasPermission('new_permission');
}
```

### Adding New Policies

1. Create policy:

```bash
php artisan make:policy NewModelPolicy --model=NewModel
```

2. Implement policy methods with shop-scoping and permission checks

3. Register in `AppServiceProvider`:

```php
Gate::policy(NewModel::class, NewModelPolicy::class);
```

4. Use in controllers:

```php
$this->authorize('update', $newModel);
```

---

## Troubleshooting

### "This action is unauthorized" error

- Check if user has required permission: `$user->getAllPermissions()`
- Check if user belongs to same shop: `$user->shop_id === $model->shop_id`
- Check if policy method exists and returns true
- Check if middleware is properly configured

### Middleware not working

- Ensure middleware is registered in `bootstrap/app.php`
- Verify route middleware is correctly applied
- Check authentication is working (`auth:sanctum`)

### Policy not being called

- Verify policy is registered in `AppServiceProvider`
- Ensure you're using `$this->authorize()` correctly
- Check model class name matches policy registration

---

## Summary

The authorization system provides comprehensive access control through:

1. **5 Roles** - Owner, Admin, Manager, Staff, Read Only
2. **8 Permissions** - manage_team, manage_billing, manage_shop, manage_products, manage_orders, manage_inventory, manage_channels, view_reports
3. **9 Policies** - For Products, Orders, Channels, Inventory, Shop, Users, Team Invitations, Notification Templates, Notification Logs
4. **2 Middleware** - permission and role middleware for route protection
5. **Helper Trait** - Convenient authorization methods for controllers
6. **Shop-Scoping** - All authorization includes multi-tenant shop verification

This ensures users can only perform actions they're authorized for and can only access data belonging to their shop.
