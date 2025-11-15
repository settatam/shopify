Team Invitation
================

Hello!

{{ $invitation->inviter->name }} has invited you to join their team at {{ $invitation->shop->name }}.

@if($invitation->message)
Personal message:
{{ $invitation->message }}

@endif
Invitation Details:
-------------------
Shop: {{ $invitation->shop->name }}
Role: {{ $invitation->getRoleDisplayName() }}
Invited by: {{ $invitation->inviter->name }} ({{ $invitation->inviter->email }})
Expires: {{ $invitation->expires_at->format('M d, Y \a\t g:i A') }}

To accept this invitation, visit:
{{ $invitation->getInvitationUrl() }}

This invitation will expire on {{ $invitation->expires_at->format('M d, Y') }}. If you don't wish to join this team, you can simply ignore this email.

---
© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
This is an automated message. Please do not reply to this email.
