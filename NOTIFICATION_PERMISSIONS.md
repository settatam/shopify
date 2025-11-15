# Notification Permissions Integration

## Overview

The **Notification Permissions Integration** connects the Team Invitations permission system with the Notifications Module, ensuring that users only receive notifications for events they have permission to access. This creates a secure, role-based notification system where team members only see relevant notifications based on their responsibilities.

---

## How It Works

### Permission-Based Filtering

When a notification is sent, the system:
1. Checks if the event requires a specific permission
2. Verifies the user has that permission
3. Only sends the notification if authorized
4. Logs permission denials for audit purposes

### Automatic Integration

The integration is **automatic** - when you send a notification using the NotificationService, permission checking happens automatically. No extra code needed!

```php
// This automatically checks if user has 'manage_orders' permission
$notificationService->send(
    eventType: 'order.placed',
    user: $user,
    variables: [...]
);
```

---

## Event-Permission Mapping

### Orders Category (requires `manage_orders`)

| Event Type | Description | Required Permission |
|------------|-------------|---------------------|
| order.placed | New order created | manage_orders |
| order.paid | Payment confirmed | manage_orders |
| order.shipped | Order shipped | manage_orders |
| order.delivered | Order delivered | manage_orders |
| order.cancelled | Order cancelled | manage_orders |
| order.refunded | Order refunded | manage_orders |

**Who Receives**: Owners, Admins, Managers, Staff (not Read Only)

### Inventory Category (requires `manage_inventory`)

| Event Type | Description | Required Permission |
|------------|-------------|---------------------|
| inventory.low_stock | Stock below threshold | manage_inventory |
| inventory.out_of_stock | Product out of stock | manage_inventory |
| inventory.restocked | Product restocked | manage_inventory |
| inventory.sync_failed | Inventory sync error | manage_inventory |

**Who Receives**: Owners, Admins, Managers (not Staff or Read Only)

### Returns Category (requires `manage_orders`)

| Event Type | Description | Required Permission |
|------------|-------------|---------------------|
| return.created | Return request submitted | manage_orders |
| return.approved | Return request approved | manage_orders |
| return.rejected | Return request rejected | manage_orders |
| return.received | Return item received | manage_orders |
| return.refund_processed | Refund processed | manage_orders |

**Who Receives**: Owners, Admins, Managers, Staff (not Read Only)

### Channels Category

| Event Type | Description | Required Permission |
|------------|-------------|---------------------|
| channel.connected | Channel successfully connected | manage_channels |
| channel.disconnected | Channel disconnected | manage_channels |
| channel.sync_error | Channel sync failed | manage_channels |
| channel.listing_created | New listing published | manage_products |

**Who Receives**:
- channel.* events: Owners, Admins (not Managers, Staff, or Read Only)
- listing_created: Owners, Admins, Managers (not Staff or Read Only)

### System Category

| Event Type | Description | Required Permission |
|------------|-------------|---------------------|
| system.welcome | New user welcome | None (everyone) |
| system.password_reset | Password reset requested | None (everyone) |
| system.invoice | Monthly invoice generated | manage_billing |
| system.trial_ending | Trial period ending | manage_billing |
| system.subscription_renewed | Subscription renewed | manage_billing |
| system.team_member_joined | Team member accepted invitation | manage_team |

**Who Receives**:
- welcome, password_reset: Everyone
- Billing events: Owners only
- team_member_joined: Owners, Admins

### Marketing Category

| Event Type | Description | Required Permission |
|------------|-------------|---------------------|
| marketing.abandoned_cart | Cart abandoned | view_reports |
| marketing.product_recommendation | Product recommendations | manage_products |
| marketing.sale_announcement | Sale/promotion announcement | manage_products |

**Who Receives**:
- abandoned_cart: Everyone except Read Only
- product_*: Owners, Admins, Managers

---

## Permission Matrix

| Role | Receives Order Notifications | Receives Inventory Notifications | Receives Channel Notifications | Receives Billing Notifications | Receives Team Notifications |
|------|------------------------------|----------------------------------|--------------------------------|--------------------------------|-----------------------------|
| **Owner** | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Yes |
| **Admin** | ✅ Yes | ✅ Yes | ✅ Yes | ❌ No | ✅ Yes |
| **Manager** | ✅ Yes | ✅ Yes | ❌ No | ❌ No | ❌ No |
| **Staff** | ✅ Yes | ❌ No | ❌ No | ❌ No | ❌ No |
| **Read Only** | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No |

---

## Usage Examples

### Automatic Permission Checking

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

// Send to single user (automatically checks permissions)
$notificationService->send(
    eventType: 'inventory.low_stock',
    user: $user,
    variables: [
        'product_name' => 'Wireless Headphones',
        'quantity' => '5',
        'threshold' => '10',
    ]
);

// If $user doesn't have 'manage_inventory' permission,
// notification is automatically skipped and logged
```

### Send to All Users with Permission

```php
// Send low stock alert to everyone with inventory permission
$notificationService->sendToUsersWithPermission(
    shop: $shop,
    eventType: 'inventory.low_stock',
    variables: [
        'product_name' => 'Wireless Headphones',
        'quantity' => '5',
    ],
    requiredPermission: 'manage_inventory'
);

// This will automatically send to: Owners, Admins, Managers
// But NOT Staff or Read Only users
```

### Send by Role (with automatic permission filtering)

```php
// Try to send to all admins
$notificationService->sendToShopUsers(
    shop: $shop,
    eventType: 'channel.sync_error',
    variables: [
        'channel_name' => 'eBay',
        'error_message' => 'API rate limit exceeded',
    ],
    roles: ['admin', 'manager']
);

// Even though we specified 'manager', they won't receive it
// because 'channel.sync_error' requires 'manage_channels' permission
// which managers don't have
```

### Check Permission Before Sending

```php
// Manually check if user can receive notification
if ($user->canReceiveNotification('order.placed')) {
    // User has required permission
    $notificationService->send('order.placed', $user, $variables);
} else {
    // User lacks permission - handle accordingly
    Log::info('User cannot receive order notifications', [
        'user_id' => $user->id,
        'role' => $user->role,
    ]);
}
```

### Check Specific Permission

```php
// Check if user has a specific permission
if ($user->hasPermission('manage_orders')) {
    // User can manage orders
}

// Check if user has any permission
if ($user->hasAnyPermission(['manage_orders', 'manage_products'])) {
    // User can manage orders OR products
}

// Check if user has all permissions
if ($user->hasAllPermissions(['manage_orders', 'manage_inventory'])) {
    // User can manage both orders AND inventory
}
```

---

## Database Schema

### notification_events Table Update

Added `required_permission` column:

```sql
ALTER TABLE notification_events
ADD COLUMN required_permission VARCHAR(255) NULL AFTER category,
ADD INDEX idx_required_permission (required_permission);
```

### Data Population

Use the seeder to populate event-permission mappings:

```bash
php artisan db:seed --class=NotificationEventsSeeder
```

This creates 28 system events with their permission requirements.

---

## API Integration

### Get Events with Permission Requirements

```http
GET /api/notifications/events
```

**Response:**
```json
{
  "events": [
    {
      "event_type": "order.placed",
      "name": "Order Placed",
      "description": "Triggered when a new order is created",
      "category": "orders",
      "required_permission": "manage_orders",
      "available_variables": {...},
      "enabled_by_default": true
    },
    {
      "event_type": "system.welcome",
      "name": "Welcome Email",
      "description": "Triggered when new user signs up",
      "category": "system",
      "required_permission": null,
      "available_variables": {...},
      "enabled_by_default": true
    }
  ]
}
```

### User Preferences Filtered by Permission

When getting user preferences, the system automatically filters out events the user doesn't have permission for:

```http
GET /api/notifications/preferences
```

**Response (for Staff member):**
```json
{
  "preferences": {
    "order.placed": {
      "name": "Order Placed",
      "category": "orders",
      "email": true,
      "sms": false,
      "push": true,
      "in_app": true
    },
    "system.welcome": {
      "name": "Welcome Email",
      "category": "system",
      "email": true,
      "sms": false,
      "push": false,
      "in_app": true
    }
    // Note: inventory.* events NOT included because
    // staff don't have manage_inventory permission
  }
}
```

---

## Permission Checking Flow

```
1. Send Notification Called
   ↓
2. Check if event requires permission
   ↓
3. If YES → Check if user has permission
   │         ↓
   │      If NO → Skip notification, log reason
   │         ↓
   │      If YES → Continue
   ↓
4. Check user notification preferences
   ↓
5. If enabled → Send notification
   ↓
6. If disabled → Skip notification
```

---

## Logging & Debugging

### Permission Denial Logs

When a notification is skipped due to lack of permission:

```
[2025-11-15 10:30:00] local.INFO: Notification skipped - user lacks required permission
{
  "event_type": "inventory.low_stock",
  "user_id": 5,
  "user_role": "staff"
}
```

### How to Debug

**Check if user has permission:**
```php
$permissions = $user->getAllPermissions();
// Returns: ['manage_orders', 'view_products', 'view_inventory']

$has = $user->hasPermission('manage_inventory');
// Returns: false
```

**Check event permission requirement:**
```php
$event = NotificationEvent::where('event_type', 'inventory.low_stock')->first();
echo $event->required_permission;
// Output: "manage_inventory"
```

**Check if user can receive:**
```php
$can = $user->canReceiveNotification('inventory.low_stock');
// Returns: false (because user lacks manage_inventory)
```

---

## Custom Events with Permissions

You can create custom events with specific permission requirements:

```php
use App\Models\NotificationEvent;

NotificationEvent::create([
    'event_type' => 'custom.high_value_order',
    'name' => 'High Value Order Alert',
    'description' => 'Triggered when order exceeds $10,000',
    'category' => 'orders',
    'required_permission' => 'manage_orders',
    'available_variables' => [
        'order_number' => 'Order number',
        'order_total' => 'Order total',
        'customer_name' => 'Customer name',
    ],
    'enabled_by_default' => true,
    'is_system' => false,
]);
```

Then send notifications:
```php
$notificationService->send(
    eventType: 'custom.high_value_order',
    user: $user,
    variables: [
        'order_number' => 'ORD-98765',
        'order_total' => '$12,500.00',
        'customer_name' => 'Jane Smith',
    ]
);
```

---

## Migration Guide

### For Existing Installations

1. **Run the migration:**
```bash
php artisan migrate
```

2. **Seed notification events:**
```bash
php artisan db:seed --class=NotificationEventsSeeder
```

3. **Verify permissions:**
```bash
php artisan tinker
> $user = User::find(1);
> $user->getAllPermissions();
> $user->hasPermission('manage_orders');
```

4. **Test notifications:**
```php
$notificationService->send('order.placed', $user, [
    'order_number' => 'TEST-001',
    'customer_name' => 'Test User',
]);
```

### Breaking Changes

**None!** This is a backward-compatible update:
- Events without `required_permission` work as before (everyone receives)
- Existing notifications continue to work
- New permission checking is additive, not restrictive

---

## Best Practices

### 1. Use Appropriate Permissions

Match notification permissions to the data they expose:
- Order notifications → `manage_orders`
- Inventory alerts → `manage_inventory`
- Billing notifications → `manage_billing`

### 2. Consider Sensitivity

For sensitive events (billing, high-value orders), use restrictive permissions:
```php
// Only owners should receive billing notifications
required_permission: 'manage_billing'
```

### 3. Test Permission Filtering

Always test with different roles:
```php
// Test with each role
foreach (['owner', 'admin', 'manager', 'staff', 'readonly'] as $role) {
    $user = User::factory()->create(['role' => $role]);
    $result = $notificationService->send('order.placed', $user, $variables);
    // Verify expected behavior
}
```

### 4. Document Custom Events

When creating custom events, document the permission requirement:
```php
NotificationEvent::create([
    'event_type' => 'custom.supplier_delay',
    'name' => 'Supplier Delay Alert',
    'description' => 'Triggered when supplier shipment is delayed',
    'required_permission' => 'manage_inventory', // Document why
    // Reason: Inventory managers need to know about supply delays
]);
```

---

## Troubleshooting

### User Not Receiving Notifications

**Check 1: Does user have required permission?**
```php
$user->hasPermission('manage_orders'); // true or false?
```

**Check 2: What are user's permissions?**
```php
$user->getAllPermissions();
// Returns array of permission strings
```

**Check 3: What permission does event require?**
```php
$event = NotificationEvent::where('event_type', 'order.placed')->first();
$event->required_permission; // 'manage_orders'
```

**Check 4: Can user receive this notification?**
```php
$user->canReceiveNotification('order.placed');
// Returns true or false
```

**Check 5: Are preferences enabled?**
```php
$preference = NotificationPreference::forUser($user)
    ->forEvent('order.placed')
    ->first();
$preference->email_enabled; // true or false?
```

### Notification Logs

Check notification_logs table for permission denials:
```sql
SELECT * FROM notification_logs
WHERE status = 'rejected'
AND error_message LIKE '%permission%';
```

### Application Logs

Check Laravel logs for permission-related skips:
```bash
tail -f storage/logs/laravel.log | grep "permission"
```

---

## Security Considerations

### Data Leakage Prevention

Permission-based notifications prevent data leakage:
- Staff members won't receive inventory alerts (they can't see inventory numbers)
- Read-only users won't receive any action notifications
- Billing notifications only go to owners (financial data protection)

### Audit Trail

All permission denials are logged:
```php
Log::info('Notification skipped - user lacks required permission', [
    'event_type' => $eventType,
    'user_id' => $user->id,
    'user_role' => $user->role,
]);
```

### Defense in Depth

Permission checking happens at multiple layers:
1. Notification service (before sending)
2. User model (permission checking)
3. Event model (permission definition)
4. Preference model (user settings)

---

## Performance Considerations

### Caching

Event permission requirements are cacheable:
```php
// Cache event permissions for 1 hour
$requiredPermission = Cache::remember(
    "event_permission:{$eventType}",
    3600,
    fn() => NotificationEvent::where('event_type', $eventType)
        ->value('required_permission')
);
```

### Batch Processing

When sending to multiple users, permissions are checked efficiently:
```php
// This filters in-memory, not with database queries
$eligibleUsers = $users->filter(fn($u) => $u->hasPermission('manage_orders'));
```

### Database Indexes

The `required_permission` column is indexed for fast lookups:
```sql
INDEX idx_required_permission (required_permission)
```

---

## Future Enhancements

### Planned Features
- **Permission Groups**: Define custom permission groups beyond roles
- **Time-Based Permissions**: Temporary permission grants for notifications
- **Channel-Specific Permissions**: Different permissions for email vs SMS
- **Event Permission UI**: Manage event permissions from admin panel
- **Permission Analytics**: Track which permissions gate which notifications

### Under Consideration
- **Delegated Permissions**: Allow users to delegate notification permissions
- **Permission Expiry**: Time-limited permission grants
- **Permission Requests**: Users can request permission upgrades
- **Notification Quotas**: Limit notifications per permission level

---

## Support

For issues or questions:
- **Documentation**: [https://docs.multichannel.app/notifications/permissions](https://docs.multichannel.app/notifications/permissions)
- **Support Email**: [support@multichannel.app](mailto:support@multichannel.app)
- **API Reference**: [https://api.multichannel.app/docs](https://api.multichannel.app/docs)

---

**Last Updated**: November 15, 2025
**Version**: 1.0.0
