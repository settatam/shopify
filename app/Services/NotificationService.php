<?php

namespace App\Services;

use App\Models\User;
use App\Models\Shop;
use App\Models\NotificationTemplate;
use App\Models\NotificationPreference;
use App\Models\NotificationLog;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification based on event type.
     *
     * @param string $eventType Event type (e.g., 'order.placed')
     * @param User $user Recipient user
     * @param array $variables Template variables
     * @param array $metadata Additional metadata (order_id, product_id, etc.)
     * @param string $channel Notification channel (email, sms, push, in_app)
     * @return NotificationLog|null
     */
    public function send(
        string $eventType,
        User $user,
        array $variables = [],
        array $metadata = [],
        string $channel = 'email'
    ): ?NotificationLog {
        // Check if user has notifications enabled for this event and channel
        if (!$this->isEnabled($user, $eventType, $channel)) {
            Log::info('Notification skipped - disabled by user preferences', [
                'event_type' => $eventType,
                'user_id' => $user->id,
                'channel' => $channel,
            ]);
            return null;
        }

        // Get template for this event
        $template = $this->getTemplate($user->shop, $eventType);

        if (!$template) {
            Log::error('Notification template not found', [
                'event_type' => $eventType,
                'shop_id' => $user->shop_id,
            ]);
            return null;
        }

        // Validate template variables
        if (!$template->validateVariables($variables)) {
            Log::error('Invalid template variables', [
                'event_type' => $eventType,
                'provided' => array_keys($variables),
                'required' => $template->extractRequiredVariables(),
            ]);
            return null;
        }

        // Create notification log
        $log = $this->createLog($template, $user, $variables, $metadata, $channel);

        // Queue notification for sending
        SendNotificationJob::dispatch($log)->onQueue('notifications');

        return $log;
    }

    /**
     * Send notification to multiple users.
     *
     * @param string $eventType
     * @param array $users Array of User models or user IDs
     * @param array $variables
     * @param array $metadata
     * @param string $channel
     * @return array Array of NotificationLog models
     */
    public function sendBulk(
        string $eventType,
        array $users,
        array $variables = [],
        array $metadata = [],
        string $channel = 'email'
    ): array {
        $logs = [];

        foreach ($users as $user) {
            if (is_int($user)) {
                $user = User::find($user);
            }

            if (!$user) {
                continue;
            }

            $log = $this->send($eventType, $user, $variables, $metadata, $channel);

            if ($log) {
                $logs[] = $log;
            }
        }

        return $logs;
    }

    /**
     * Send notification to all users in a shop with a specific role.
     *
     * @param Shop $shop
     * @param string $eventType
     * @param array $variables
     * @param string|array $roles User roles to notify
     * @param array $metadata
     * @param string $channel
     * @return array
     */
    public function sendToShopUsers(
        Shop $shop,
        string $eventType,
        array $variables = [],
        string|array $roles = 'owner',
        array $metadata = [],
        string $channel = 'email'
    ): array {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        $users = User::where('shop_id', $shop->id)
            ->whereIn('role', $roles)
            ->get();

        return $this->sendBulk($eventType, $users->all(), $variables, $metadata, $channel);
    }

    /**
     * Send notification to all users in a shop with specific permission.
     *
     * @param Shop $shop
     * @param string $eventType
     * @param array $variables
     * @param string $requiredPermission
     * @param array $metadata
     * @param string $channel
     * @return array
     */
    public function sendToUsersWithPermission(
        Shop $shop,
        string $eventType,
        array $variables = [],
        string $requiredPermission = '',
        array $metadata = [],
        string $channel = 'email'
    ): array {
        // Get all users for this shop
        $users = User::where('shop_id', $shop->id)->get();

        // Filter users who have the required permission
        $eligibleUsers = $users->filter(function ($user) use ($requiredPermission) {
            return $user->hasPermission($requiredPermission);
        });

        return $this->sendBulk($eventType, $eligibleUsers->all(), $variables, $metadata, $channel);
    }

    /**
     * Check if notifications are enabled for user.
     */
    protected function isEnabled(User $user, string $eventType, string $channel): bool
    {
        // First, check if user has permission to receive this notification
        if (!$user->canReceiveNotification($eventType)) {
            Log::info('Notification skipped - user lacks required permission', [
                'event_type' => $eventType,
                'user_id' => $user->id,
                'user_role' => $user->role,
            ]);
            return false;
        }

        // Then check user preferences
        $preference = NotificationPreference::forUser($user)
            ->forEvent($eventType)
            ->first();

        if (!$preference) {
            // Use default from notification event
            $event = \App\Models\NotificationEvent::where('event_type', $eventType)->first();
            return $event ? $event->enabled_by_default : true;
        }

        return $preference->isEnabledFor($channel);
    }

    /**
     * Get template for event type.
     */
    protected function getTemplate(Shop $shop, string $eventType): ?NotificationTemplate
    {
        return NotificationTemplate::where('shop_id', $shop->id)
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create notification log entry.
     */
    protected function createLog(
        NotificationTemplate $template,
        User $user,
        array $variables,
        array $metadata,
        string $channel
    ): NotificationLog {
        // Render template with variables
        $subject = $template->renderSubject($variables);
        $body = $channel === 'email' ? $template->renderBodyHtml($variables) : $template->renderBodyText($variables);

        return NotificationLog::create([
            'shop_id' => $user->shop_id,
            'user_id' => $user->id,
            'notification_template_id' => $template->id,
            'event_type' => $template->event_type,
            'channel' => $channel,
            'recipient_email' => $user->email,
            'recipient_phone' => $user->phone,
            'subject' => $subject,
            'body' => $body,
            'variables' => $variables,
            'metadata' => $metadata,
            'status' => 'queued',
            'queued_at' => now(),
        ]);
    }

    /**
     * Enable notifications for user and event type.
     */
    public function enableForUser(User $user, string $eventType, string $channel = 'email'): void
    {
        $preference = NotificationPreference::getOrCreateFor($user, $eventType);
        $preference->enable($channel);
    }

    /**
     * Disable notifications for user and event type.
     */
    public function disableForUser(User $user, string $eventType, string $channel = 'email'): void
    {
        $preference = NotificationPreference::getOrCreateFor($user, $eventType);
        $preference->disable($channel);
    }

    /**
     * Update user preferences in bulk.
     */
    public function updatePreferences(User $user, array $preferences): void
    {
        foreach ($preferences as $eventType => $channels) {
            $preference = NotificationPreference::getOrCreateFor($user, $eventType);

            $preference->update([
                'email_enabled' => $channels['email'] ?? false,
                'sms_enabled' => $channels['sms'] ?? false,
                'push_enabled' => $channels['push'] ?? false,
                'in_app_enabled' => $channels['in_app'] ?? false,
            ]);
        }
    }

    /**
     * Get user preferences for all event types.
     */
    public function getUserPreferences(User $user): array
    {
        $events = \App\Models\NotificationEvent::all();
        $preferences = NotificationPreference::forUser($user)->get()->keyBy('event_type');

        $result = [];

        foreach ($events as $event) {
            $preference = $preferences->get($event->event_type);

            $result[$event->event_type] = [
                'name' => $event->name,
                'description' => $event->description,
                'category' => $event->category,
                'email' => $preference ? $preference->email_enabled : $event->enabled_by_default,
                'sms' => $preference ? $preference->sms_enabled : false,
                'push' => $preference ? $preference->push_enabled : false,
                'in_app' => $preference ? $preference->in_app_enabled : $event->enabled_by_default,
            ];
        }

        return $result;
    }

    /**
     * Get notification statistics for shop.
     */
    public function getStatistics(Shop $shop, ?string $eventType = null): array
    {
        $query = NotificationLog::where('shop_id', $shop->id);

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $total = $query->count();
        $sent = $query->whereIn('status', ['sent', 'delivered', 'opened', 'clicked'])->count();
        $failed = $query->whereIn('status', ['failed', 'bounced', 'rejected'])->count();
        $queued = $query->where('status', 'queued')->count();

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'queued' => $queued,
            'delivery_rate' => NotificationLog::getDeliveryRate($shop->id, $eventType),
            'open_rate' => NotificationLog::getOpenRate($shop->id, $eventType),
            'click_rate' => NotificationLog::getClickRate($shop->id, $eventType),
        ];
    }

    /**
     * Retry failed notification.
     */
    public function retry(NotificationLog $log): bool
    {
        if (!$log->canRetry()) {
            return false;
        }

        $log->scheduleRetry();

        SendNotificationJob::dispatch($log)->onQueue('notifications');

        return true;
    }

    /**
     * Retry all failed notifications for a shop.
     */
    public function retryFailed(Shop $shop, int $maxRetries = 3): int
    {
        $failed = NotificationLog::where('shop_id', $shop->id)
            ->retryable($maxRetries)
            ->get();

        $retried = 0;

        foreach ($failed as $log) {
            if ($this->retry($log)) {
                $retried++;
            }
        }

        return $retried;
    }

    /**
     * Create or update notification template.
     */
    public function createTemplate(
        Shop $shop,
        string $eventType,
        string $name,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        array $settings = []
    ): NotificationTemplate {
        return NotificationTemplate::create([
            'shop_id' => $shop->id,
            'event_type' => $eventType,
            'name' => $name,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'category' => $settings['category'] ?? 'transactional',
            'from_name' => $settings['from_name'] ?? null,
            'from_email' => $settings['from_email'] ?? null,
            'reply_to' => $settings['reply_to'] ?? null,
            'is_active' => $settings['is_active'] ?? true,
            'settings' => $settings,
        ]);
    }

    /**
     * Test notification by sending to specific email.
     */
    public function test(NotificationTemplate $template, string $email, array $variables = []): NotificationLog
    {
        // Use default variables if none provided
        if (empty($variables)) {
            $variables = $this->getDefaultVariables($template->event_type);
        }

        $log = NotificationLog::create([
            'shop_id' => $template->shop_id,
            'notification_template_id' => $template->id,
            'event_type' => $template->event_type,
            'channel' => 'email',
            'recipient_email' => $email,
            'subject' => $template->renderSubject($variables),
            'body' => $template->renderBodyHtml($variables),
            'variables' => $variables,
            'metadata' => ['test' => true],
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        SendNotificationJob::dispatch($log)->onQueue('notifications');

        return $log;
    }

    /**
     * Get default variables for testing.
     */
    protected function getDefaultVariables(string $eventType): array
    {
        return [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'order_number' => 'ORD-12345',
            'order_total' => '$99.99',
            'order_date' => now()->format('M d, Y'),
            'product_name' => 'Sample Product',
            'product_sku' => 'SKU-123',
            'quantity' => '2',
            'tracking_number' => '1Z999AA10123456784',
            'tracking_url' => 'https://example.com/track/1Z999AA10123456784',
            'rma_number' => 'RMA-12345',
            'refund_amount' => '$50.00',
            'shop_name' => 'My Store',
            'shop_url' => 'https://mystore.com',
            'support_email' => 'support@mystore.com',
            'support_phone' => '1-800-123-4567',
        ];
    }
}
