<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\NotificationPreference;
use App\Models\NotificationLog;
use App\Models\NotificationEvent;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get all notification templates for shop.
     */
    public function getTemplates(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NotificationTemplate::class);

        $shop = $request->user()->shop;

        $templates = NotificationTemplate::where('shop_id', $shop->id)
            ->with('logs')
            ->get();

        return response()->json([
            'templates' => $templates,
        ]);
    }

    /**
     * Get specific notification template.
     */
    public function getTemplate(Request $request, NotificationTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        return response()->json([
            'template' => $template->load('logs'),
        ]);
    }

    /**
     * Create new notification template.
     */
    public function createTemplate(Request $request): JsonResponse
    {
        $this->authorize('create', NotificationTemplate::class);

        $request->validate([
            'event_type' => 'required|string',
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:500',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email',
            'reply_to' => 'nullable|email',
            'category' => 'nullable|in:transactional,marketing,system',
            'is_active' => 'nullable|boolean',
            'settings' => 'nullable|array',
        ]);

        $shop = $request->user()->shop;

        $template = NotificationTemplate::create([
            'shop_id' => $shop->id,
            'event_type' => $request->event_type,
            'name' => $request->name,
            'subject' => $request->subject,
            'body_html' => $request->body_html,
            'body_text' => $request->body_text,
            'from_name' => $request->from_name,
            'from_email' => $request->from_email,
            'reply_to' => $request->reply_to,
            'category' => $request->category ?? 'transactional',
            'is_active' => $request->is_active ?? true,
            'is_system' => false,
            'settings' => $request->settings ?? [],
        ]);

        return response()->json([
            'message' => 'Template created successfully',
            'template' => $template,
        ], 201);
    }

    /**
     * Update notification template.
     */
    public function updateTemplate(Request $request, NotificationTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'subject' => 'sometimes|string|max:500',
            'body_html' => 'sometimes|string',
            'body_text' => 'nullable|string',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email',
            'reply_to' => 'nullable|email',
            'is_active' => 'sometimes|boolean',
            'settings' => 'nullable|array',
        ]);

        $template->update($request->only([
            'name',
            'subject',
            'body_html',
            'body_text',
            'from_name',
            'from_email',
            'reply_to',
            'is_active',
            'settings',
        ]));

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => $template,
        ]);
    }

    /**
     * Delete notification template.
     */
    public function deleteTemplate(Request $request, NotificationTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);

        if ($template->is_system) {
            return response()->json([
                'error' => 'Cannot delete system template',
            ], 403);
        }

        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Test notification template.
     */
    public function testTemplate(Request $request, NotificationTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);

        $request->validate([
            'email' => 'required|email',
            'variables' => 'nullable|array',
        ]);

        $log = $this->notificationService->test(
            $template,
            $request->email,
            $request->variables ?? []
        );

        return response()->json([
            'message' => 'Test notification queued',
            'log' => $log,
        ]);
    }

    /**
     * Get user notification preferences.
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = $this->notificationService->getUserPreferences($user);

        return response()->json([
            'preferences' => $preferences,
        ]);
    }

    /**
     * Update user notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'preferences' => 'required|array',
            'preferences.*.email' => 'sometimes|boolean',
            'preferences.*.sms' => 'sometimes|boolean',
            'preferences.*.push' => 'sometimes|boolean',
            'preferences.*.in_app' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        $this->notificationService->updatePreferences($user, $request->preferences);

        return response()->json([
            'message' => 'Preferences updated successfully',
        ]);
    }

    /**
     * Get notification logs.
     */
    public function getLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NotificationLog::class);

        $shop = $request->user()->shop;

        $query = NotificationLog::where('shop_id', $shop->id);

        // Filter by event type
        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by channel
        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        $logs = $query->with(['user', 'template'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($logs);
    }

    /**
     * Get specific notification log.
     */
    public function getLog(Request $request, NotificationLog $log): JsonResponse
    {
        $this->authorize('view', $log);

        return response()->json([
            'log' => $log->load(['user', 'template']),
        ]);
    }

    /**
     * Retry failed notification.
     */
    public function retryLog(Request $request, NotificationLog $log): JsonResponse
    {
        $this->authorize('update', $log);

        $success = $this->notificationService->retry($log);

        if (!$success) {
            return response()->json([
                'error' => 'Notification cannot be retried (max retries exceeded)',
            ], 400);
        }

        return response()->json([
            'message' => 'Notification queued for retry',
            'log' => $log->fresh(),
        ]);
    }

    /**
     * Retry all failed notifications.
     */
    public function retryAllFailed(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NotificationLog::class);

        $shop = $request->user()->shop;

        $retried = $this->notificationService->retryFailed($shop);

        return response()->json([
            'message' => "Retried {$retried} failed notifications",
            'count' => $retried,
        ]);
    }

    /**
     * Get notification statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NotificationLog::class);

        $shop = $request->user()->shop;

        $eventType = $request->input('event_type');

        $stats = $this->notificationService->getStatistics($shop, $eventType);

        return response()->json([
            'statistics' => $stats,
        ]);
    }

    /**
     * Get all available notification events.
     */
    public function getEvents(Request $request): JsonResponse
    {
        $events = NotificationEvent::all();

        return response()->json([
            'events' => $events,
        ]);
    }

    /**
     * Track email open (tracking pixel).
     */
    public function trackOpen(Request $request, string $id): \Illuminate\Http\Response
    {
        $log = NotificationLog::find($id);

        if ($log) {
            $log->markAsOpened();
        }

        // Return 1x1 transparent GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Track link click.
     */
    public function trackClick(Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $log = NotificationLog::find($id);

        if ($log) {
            $log->markAsClicked();
        }

        $url = $request->input('url');

        if (!$url) {
            abort(404);
        }

        return redirect()->away(urldecode($url));
    }

    /**
     * Handle email provider webhooks (delivery, bounce, etc.).
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // TODO: Implement webhook handling for email providers
        // Each provider (SendGrid, Mailgun, SES, Postmark) has different webhook formats

        $provider = $request->input('provider', config('mail.default'));

        // Log webhook for debugging
        \Log::info('Email webhook received', [
            'provider' => $provider,
            'payload' => $request->all(),
        ]);

        return response()->json(['message' => 'Webhook received']);
    }
}
