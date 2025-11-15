<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .invitation-box {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #4F46E5;
        }
        .button {
            display: inline-block;
            background-color: #4F46E5;
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #4338CA;
        }
        .details {
            margin: 20px 0;
        }
        .details-row {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .details-label {
            font-weight: bold;
            color: #6b7280;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            padding: 20px;
        }
        .message {
            background-color: #fef3c7;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Team Invitation</h1>
    </div>

    <div class="content">
        <p>Hello!</p>

        <p><strong>{{ $invitation->inviter->name }}</strong> has invited you to join their team at <strong>{{ $invitation->shop->name }}</strong>.</p>

        @if($invitation->message)
        <div class="message">
            <strong>Personal message:</strong><br>
            {{ $invitation->message }}
        </div>
        @endif

        <div class="invitation-box">
            <h2>Invitation Details</h2>

            <div class="details">
                <div class="details-row">
                    <span class="details-label">Shop:</span> {{ $invitation->shop->name }}
                </div>
                <div class="details-row">
                    <span class="details-label">Role:</span> {{ $invitation->getRoleDisplayName() }}
                </div>
                <div class="details-row">
                    <span class="details-label">Invited by:</span> {{ $invitation->inviter->name }} ({{ $invitation->inviter->email }})
                </div>
                <div class="details-row">
                    <span class="details-label">Expires:</span> {{ $invitation->expires_at->format('M d, Y \a\t g:i A') }}
                </div>
            </div>
        </div>

        <div style="text-align: center;">
            <a href="{{ $invitation->getInvitationUrl() }}" class="button">
                Accept Invitation
            </a>
        </div>

        <p style="color: #6b7280; font-size: 14px;">
            This invitation will expire on {{ $invitation->expires_at->format('M d, Y') }}. If you don't wish to join this team, you can simply ignore this email.
        </p>

        <p style="color: #6b7280; font-size: 14px;">
            If the button above doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $invitation->getInvitationUrl() }}">{{ $invitation->getInvitationUrl() }}</a>
        </p>
    </div>

    <div class="footer">
        <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        <p>This is an automated message. Please do not reply to this email.</p>
    </div>
</body>
</html>
