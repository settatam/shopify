# Team Invitations & Member Management

## Overview

The **Team Invitations Module** enables shop owners and administrators to invite team members to their account, manage roles and permissions, and collaborate efficiently. Team members can access the platform with specific permissions based on their role.

---

## Features

### 1. **Team Member Invitations**
- Invite team members via email
- Bulk invite multiple members at once
- Custom personal messages with invitations
- 7-day invitation expiry
- Automatic email notifications
- Resend and revoke invitations

### 2. **Role-Based Access Control**
- 5 predefined roles with different permission levels
- Custom permissions per team member
- Granular access control
- Role updates for existing members

### 3. **Invitation Management**
- Track pending, accepted, rejected, and expired invitations
- View invitation history
- Automatic cleanup of expired invitations
- IP address and user agent tracking for security

### 4. **Team Member Management**
- View all team members
- Update member roles and permissions
- Remove team members
- View member activity and login history
- Team statistics and analytics

---

## Roles & Permissions

###  Available Roles

| Role | Description | Permissions |
|------|-------------|-------------|
| **Owner** | Shop owner with full access | All permissions including billing and team management |
| **Admin** | Administrator with full access except billing | All permissions except billing |
| **Manager** | Can manage products, orders, and inventory | Manage products, orders, inventory; View reports |
| **Staff** | Can manage orders and view products | Manage orders; View products and inventory |
| **Read Only** | Can only view data | View products, orders, inventory, and reports |

### Permission List

**Owner Permissions:**
- `manage_team` - Invite, remove, and manage team members
- `manage_billing` - Manage subscription and billing
- `manage_shop` - Configure shop settings
- `manage_products` - Create, edit, delete products
- `manage_orders` - Process and manage orders
- `manage_inventory` - Adjust inventory levels
- `manage_channels` - Connect and manage sales channels
- `view_reports` - Access analytics and reports

**Admin Permissions:**
- `manage_team`
- `manage_products`
- `manage_orders`
- `manage_inventory`
- `manage_channels`
- `view_reports`

**Manager Permissions:**
- `manage_products`
- `manage_orders`
- `manage_inventory`
- `view_reports`

**Staff Permissions:**
- `manage_orders`
- `view_products`
- `view_inventory`

**Read Only Permissions:**
- `view_products`
- `view_orders`
- `view_inventory`
- `view_reports`

---

## Database Schema

### `team_invitations` Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| shop_id | bigint | Shop that invitation belongs to |
| invited_by | bigint | User who sent invitation |
| email | string | Email of invitee |
| token | string | Unique invitation token (64 characters) |
| role | enum | Role assigned to invitee |
| permissions | json | Custom permissions (optional) |
| status | enum | pending, accepted, rejected, expired, revoked |
| message | text | Personal message from inviter |
| expires_at | timestamp | Invitation expiry (default 7 days) |
| accepted_at | timestamp | When invitation was accepted |
| rejected_at | timestamp | When invitation was rejected |
| revoked_at | timestamp | When invitation was revoked |
| user_id | bigint | User created after acceptance |
| accepted_ip | ip | IP address of acceptance |
| accepted_user_agent | string | Browser used for acceptance |

### Added Fields to `users` Table

| Column | Type | Description |
|--------|------|-------------|
| is_team_member | boolean | Whether user is a team member (vs owner) |
| invited_by | bigint | User who invited this team member |
| permissions | json | Custom permissions override |
| last_login_at | timestamp | Last login timestamp |
| last_login_ip | ip | Last login IP address |

---

## API Endpoints

### Team Members

#### Get All Team Members
```http
GET /api/team/members
```

**Response:**
```json
{
  "members": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "admin",
      "is_team_member": true,
      "invited_by": 1,
      "permissions": ["manage_products", "manage_orders"],
      "last_login_at": "2025-11-15T10:30:00Z",
      "created_at": "2025-11-01T09:00:00Z"
    }
  ]
}
```

#### Update Member Role
```http
POST /api/team/members/{member}/role
```

**Request:**
```json
{
  "role": "manager",
  "permissions": ["manage_products", "manage_orders", "manage_inventory"]
}
```

#### Remove Team Member
```http
DELETE /api/team/members/{member}
```

### Invitations

#### Invite Team Member
```http
POST /api/team/invitations
```

**Request:**
```json
{
  "email": "jane@example.com",
  "role": "staff",
  "permissions": ["manage_orders", "view_products"],
  "message": "Hey Jane, would love to have you join our team!"
}
```

**Response:**
```json
{
  "message": "Invitation sent successfully",
  "invitation": {
    "id": 123,
    "shop_id": 1,
    "email": "jane@example.com",
    "role": "staff",
    "token": "abc123...",
    "status": "pending",
    "expires_at": "2025-11-22T10:00:00Z",
    "inviter": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    }
  }
}
```

#### Bulk Invite
```http
POST /api/team/invitations/bulk
```

**Request:**
```json
{
  "invitations": [
    {
      "email": "jane@example.com",
      "role": "staff",
      "message": "Welcome to the team!"
    },
    {
      "email": "bob@example.com",
      "role": "manager",
      "message": "Excited to work with you!"
    }
  ]
}
```

**Response:**
```json
{
  "message": "Bulk invitation completed",
  "success_count": 2,
  "failed_count": 0,
  "results": {
    "success": [
      {
        "email": "jane@example.com",
        "invitation": {...}
      },
      {
        "email": "bob@example.com",
        "invitation": {...}
      }
    ],
    "failed": []
  }
}
```

#### Get Pending Invitations
```http
GET /api/team/invitations/pending
```

**Response:**
```json
{
  "invitations": [
    {
      "id": 123,
      "email": "jane@example.com",
      "role": "staff",
      "status": "pending",
      "expires_at": "2025-11-22T10:00:00Z",
      "inviter": {
        "id": 1,
        "name": "John Doe"
      }
    }
  ]
}
```

#### Resend Invitation
```http
POST /api/team/invitations/{invitation}/resend
```

#### Revoke Invitation
```http
DELETE /api/team/invitations/{invitation}
```

### Public Invitation Endpoints (No Auth)

#### Get Invitation Details
```http
GET /api/team/invitations/{token}
```

**Response:**
```json
{
  "invitation": {
    "id": 123,
    "email": "jane@example.com",
    "role": "staff",
    "shop": {
      "id": 1,
      "name": "My Awesome Store"
    },
    "inviter": {
      "name": "John Doe",
      "email": "john@example.com"
    },
    "message": "Welcome to the team!",
    "expires_at": "2025-11-22T10:00:00Z"
  }
}
```

#### Accept Invitation (New User)
```http
POST /api/team/invitations/{token}/accept
```

**Request:**
```json
{
  "name": "Jane Smith",
  "password": "securepassword123",
  "password_confirmation": "securepassword123"
}
```

**Response:**
```json
{
  "message": "Invitation accepted successfully",
  "user": {
    "id": 2,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "role": "staff",
    "shop_id": 1
  },
  "token": "auth_token_here"
}
```

#### Accept Invitation (Existing User)
```http
POST /api/team/invitations/{token}/accept-existing
Authorization: Bearer {existing_user_token}
```

### Roles

#### Get Available Roles
```http
GET /api/team/roles
```

**Response:**
```json
{
  "roles": {
    "owner": {
      "name": "Owner",
      "description": "Full access to all features including billing and team management",
      "permissions": ["manage_team", "manage_billing", "manage_shop", ...]
    },
    "admin": {
      "name": "Administrator",
      "description": "Full access except billing",
      "permissions": ["manage_team", "manage_products", ...]
    },
    ...
  }
}
```

### Statistics

#### Get Team Statistics
```http
GET /api/team/statistics
```

**Response:**
```json
{
  "statistics": {
    "total_members": 5,
    "team_members": 4,
    "pending_invitations": 2,
    "total_invitations_sent": 10,
    "accepted_invitations": 6,
    "rejected_invitations": 1,
    "by_role": {
      "owner": 1,
      "admin": 2,
      "manager": 1,
      "staff": 1
    }
  }
}
```

---

## Programmatic Usage

### Sending Invitations

```php
use App\Services\TeamInvitationService;

$invitationService = app(TeamInvitationService::class);

// Send single invitation
$invitation = $invitationService->invite(
    shop: $shop,
    invitedBy: $currentUser,
    email: 'jane@example.com',
    role: 'staff',
    permissions: null, // Uses role's default permissions
    message: 'Welcome to the team!'
);

// Bulk invite
$results = $invitationService->bulkInvite(
    shop: $shop,
    invitedBy: $currentUser,
    invitations: [
        [
            'email' => 'jane@example.com',
            'role' => 'staff',
            'message' => 'Welcome!'
        ],
        [
            'email' => 'bob@example.com',
            'role' => 'manager',
        ],
    ]
);

// Check results
echo "Success: " . count($results['success']);
echo "Failed: " . count($results['failed']);
```

### Accepting Invitations

```php
use App\Services\TeamInvitationService;

$invitationService = app(TeamInvitationService::class);

// Accept for new user
$user = $invitationService->accept(
    token: $token,
    userData: [
        'name' => 'Jane Smith',
        'password' => 'securepassword',
    ],
    ip: request()->ip(),
    userAgent: request()->userAgent()
);

// Accept for existing user
$user = $invitationService->acceptExistingUser(
    token: $token,
    user: $existingUser,
    ip: request()->ip(),
    userAgent: request()->userAgent()
);
```

### Managing Team Members

```php
use App\Services\TeamInvitationService;

$invitationService = app(TeamInvitationService::class);

// Get all team members
$members = $invitationService->getTeamMembers($shop, includeOwner: true);

// Update member role
$invitationService->updateMemberRole(
    member: $member,
    role: 'manager',
    permissions: ['manage_products', 'manage_orders', 'manage_inventory']
);

// Remove team member
$invitationService->removeMember($member);

// Get pending invitations
$pending = $invitationService->getPendingInvitations($shop);

// Resend invitation
$newInvitation = $invitationService->resend($oldInvitation);

// Revoke invitation
$invitationService->revoke($invitation);
```

### Checking Permissions

```php
// Check if user has specific permission
if ($user->hasPermission('manage_products')) {
    // Allow product management
}

// Check role
if ($user->role === 'admin' || $user->role === 'owner') {
    // Allow admin actions
}

// Get user's permissions
$permissions = $user->permissions ?? TeamInvitation::getRolePermissions($user->role);
```

---

## Invitation Email

### Email Content

The invitation email includes:
- Shop name
- Inviter's name and email
- Role being assigned
- Personal message (if provided)
- Invitation URL with unique token
- Expiry date
- Accept invitation button

### Email Template

The email uses a professional HTML template with:
- Branded header
- Clear call-to-action button
- Invitation details in formatted box
- Personal message (if included)
- Expiry warning
- Plain text fallback

### Customization

Customize the email template at:
- HTML: `resources/views/emails/team-invitation.blade.php`
- Plain text: `resources/views/emails/team-invitation-text.blade.php`

---

## Security Features

### Invitation Security
- **Unique Tokens**: 64-character random tokens
- **Expiry**: Invitations expire after 7 days
- **Single Use**: Tokens can only be used once
- **CSRF Protection**: State validation on OAuth flow
- **IP Tracking**: Record IP address on acceptance
- **User Agent Tracking**: Record browser/device used

### Access Control
- **Role-Based**: Permissions tied to roles
- **Custom Permissions**: Override role defaults
- **Shop Scoping**: Team members only access their shop
- **Authorization Policies**: Laravel policies enforce access
- **Audit Trail**: Track who invited whom and when

### Data Protection
- **Email Validation**: Strict email format validation
- **Password Requirements**: Minimum 8 characters
- **Secure Hashing**: Bcrypt password hashing
- **Encrypted Storage**: Sensitive data encrypted at rest

---

## Scheduled Tasks

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Mark expired invitations daily
    $schedule->job(new CleanupExpiredInvitationsJob())
        ->daily()
        ->at('02:00');
}
```

---

## Workflows

### New Team Member Onboarding Flow

1. **Owner sends invitation**
   - Owner enters email and selects role
   - Optional personal message
   - Invitation created with 7-day expiry

2. **Invitee receives email**
   - Email with invitation details
   - Clear accept button
   - Invitation URL with token

3. **Invitee clicks accept**
   - Redirected to registration page
   - Pre-filled email address
   - Enters name and password

4. **Account created**
   - User account created with specified role
   - Invitation marked as accepted
   - Owner notified of acceptance
   - New user can log in immediately

5. **Team collaboration begins**
   - New user has access based on role
   - Can perform permitted actions
   - Visible in team members list

### Existing User Joining Additional Shop

1. **Owner sends invitation**
   - Invitation sent to existing user's email

2. **Existing user receives email**
   - User already has account
   - Email indicates they can accept to join new shop

3. **User accepts with existing account**
   - Logs in with existing credentials
   - Clicks special "accept with existing account" link
   - Switches to new shop context

4. **User added to shop**
   - User's shop_id updated
   - Role and permissions applied
   - Can switch between shops (future feature)

---

## Best Practices

### Inviting Team Members

1. **Assign Appropriate Roles**
   - Start with minimum required permissions
   - Upgrade as needed
   - Don't over-permission team members

2. **Personal Messages**
   - Include context about why they're being invited
   - Set expectations about their responsibilities
   - Make them feel welcome

3. **Review Pending Invitations**
   - Check pending invitations weekly
   - Resend if not accepted within 3-4 days
   - Revoke if no longer needed

4. **Onboard Properly**
   - Provide training on platform usage
   - Document processes and procedures
   - Set up regular check-ins

### Managing Permissions

1. **Role-Based First**
   - Use predefined roles when possible
   - Only use custom permissions when necessary
   - Document custom permission choices

2. **Regular Audits**
   - Review team members quarterly
   - Remove inactive members
   - Update roles as responsibilities change

3. **Principle of Least Privilege**
   - Grant minimum permissions needed
   - Increase access only when required
   - Review before granting owner/admin access

### Security

1. **Monitor Acceptances**
   - Review accepted invitations
   - Check IP addresses for anomalies
   - Investigate unexpected acceptances

2. **Revoke Promptly**
   - Revoke invitations when role changes
   - Revoke for employees who leave
   - Don't leave old invitations pending

3. **Limit Owner Accounts**
   - Only 1-2 owners per shop
   - Use admin role for most administrators
   - Protect owner credentials carefully

---

## Troubleshooting

### Invitation Not Received

**Possible Causes:**
1. Email in spam folder
2. Invalid email address
3. Email provider blocking

**Solutions:**
- Check spam/junk folders
- Verify email address is correct
- Whitelist sender domain
- Resend invitation
- Try alternative email address

### Invitation Expired

**Solution:**
- Owner can resend invitation
- New invitation with fresh token created
- 7-day expiry resets

### Cannot Accept Invitation

**Possible Causes:**
1. Invitation already accepted
2. Invitation revoked
3. Invitation expired
4. Invalid token

**Solutions:**
- Check invitation status
- Request new invitation from owner
- Verify correct invitation link

### User Already Exists

**Possible Causes:**
- User with email already in this shop
- Pending invitation to same email

**Solutions:**
- Owner should check existing team members
- Revoke old invitation before sending new one
- Use different email address

---

## Notifications Integration

The team module integrates with the Notifications Module to send:

**Email Notifications:**
- `team.invitation_sent` - When invitation is sent (to invitee)
- `team.invitation_accepted` - When invitation is accepted (to inviter)
- `team.member_added` - When member is added (to owner)
- `team.member_removed` - When member is removed (to owner)
- `team.role_changed` - When member's role changes (to member)

Configure notification templates in the Notifications Module.

---

## Future Enhancements

### Planned Features (Q1 2026)
- Multi-shop support (users can belong to multiple shops)
- Team member activity logs
- Permission-based UI hiding
- Invitation templates
- Slack/Teams integration for notifications
- SSO (Single Sign-On) support
- 2FA (Two-Factor Authentication)
- Session management and forced logout

### Under Consideration
- Department/team grouping
- Custom roles beyond 5 predefined
- Time-limited access (temporary permissions)
- Approval workflow for sensitive actions
- Delegation (temporary permission grants)
- Audit trail for all team member actions

---

## Support

For issues or questions:
- **Documentation**: [https://docs.multichannel.app/team](https://docs.multichannel.app/team)
- **Support Email**: [support@multichannel.app](mailto:support@multichannel.app)
- **API Reference**: [https://api.multichannel.app/docs](https://api.multichannel.app/docs)

---

**Last Updated**: November 15, 2025
**Version**: 1.0.0
