<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailProviderSetting;
use App\Models\MailchimpSubscriber;
use App\Models\EmailCampaign;
use App\Services\MailchimpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MailchimpController extends Controller
{
    public function __construct(
        protected MailchimpService $mailchimpService
    ) {}

    /**
     * Get Mailchimp configuration.
     */
    public function getConfiguration(Request $request): JsonResponse
    {
        $this->authorize('view', $request->user());

        

        $settings = EmailProviderSetting::byProvider($request->user()->id, 'mailchimp');

        if (!$settings) {
            return response()->json([
                'configured' => false,
            ]);
        }

        return response()->json([
            'configured' => true,
            'settings' => [
                'is_active' => $settings->is_active,
                'server_prefix' => $settings->server_prefix,
                'default_audience_id' => $settings->default_audience_id,
                'audience_ids' => $settings->audience_ids,
                'from_email' => $settings->from_email,
                'from_name' => $settings->from_name,
                'reply_to' => $settings->reply_to,
                'double_optin' => $settings->double_optin,
                'last_sync_at' => $settings->last_sync_at,
            ],
        ]);
    }

    /**
     * Configure Mailchimp.
     */
    public function configure(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'api_key' => 'required|string',
            'default_audience_id' => 'required|string',
            'from_email' => 'nullable|email',
            'from_name' => 'nullable|string|max:255',
            'reply_to' => 'nullable|email',
        ]);

        

        try {
            $settings = $this->mailchimpService->configure(
                user: $request->user(),
                apiKey: $request->api_key,
                defaultAudienceId: $request->default_audience_id,
                fromEmail: $request->from_email,
                fromName: $request->from_name,
                replyTo: $request->reply_to
            );

            return response()->json([
                'message' => 'Mailchimp configured successfully',
                'settings' => [
                    'is_active' => $settings->is_active,
                    'server_prefix' => $settings->server_prefix,
                    'default_audience_id' => $settings->default_audience_id,
                    'from_email' => $settings->from_email,
                    'from_name' => $settings->from_name,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all audiences (lists).
     */
    public function getAudiences(Request $request): JsonResponse
    {
        $this->authorize('view', $request->user());

        

        $audiences = $this->mailchimpService->getAudiences($shop);

        if ($audiences === null) {
            return response()->json([
                'error' => 'Failed to fetch audiences',
            ], 400);
        }

        return response()->json([
            'audiences' => $audiences,
        ]);
    }

    /**
     * Add subscriber.
     */
    public function addSubscriber(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'email' => 'required|email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'status' => 'nullable|in:subscribed,pending,unsubscribed',
            'tags' => 'nullable|array',
            'merge_fields' => 'nullable|array',
        ]);

        

        $subscriber = $this->mailchimpService->addSubscriber(
            user: $request->user(),
            email: $request->email,
            firstName: $request->first_name,
            lastName: $request->last_name,
            status: $request->input('status', 'subscribed'),
            mergeFields: $request->merge_fields,
            tags: $request->tags
        );

        if (!$subscriber) {
            return response()->json([
                'error' => 'Failed to add subscriber',
            ], 400);
        }

        return response()->json([
            'message' => 'Subscriber added successfully',
            'subscriber' => $subscriber,
        ]);
    }

    /**
     * Unsubscribe subscriber.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'email' => 'required|email',
        ]);

        

        $success = $this->mailchimpService->unsubscribe($shop, $request->email);

        if (!$success) {
            return response()->json([
                'error' => 'Failed to unsubscribe',
            ], 400);
        }

        return response()->json([
            'message' => 'Subscriber unsubscribed successfully',
        ]);
    }

    /**
     * Get all subscribers.
     */
    public function getSubscribers(Request $request): JsonResponse
    {
        $this->authorize('view', $request->user());

        

        $query = MailchimpSubscriber::where('user_id', $request->user()->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by audience
        if ($request->has('audience_id')) {
            $query->where('audience_id', $request->audience_id);
        }

        // Search by email
        if ($request->has('search')) {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        $subscribers = $query->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($subscribers);
    }

    /**
     * Sync subscribers from Mailchimp.
     */
    public function syncSubscribers(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        

        $count = $this->mailchimpService->syncSubscribers(
            user: $request->user(),
            audienceId: $request->input('audience_id')
        );

        return response()->json([
            'message' => "Synced {$count} subscribers",
            'count' => $count,
        ]);
    }

    /**
     * Get subscriber count.
     */
    public function getSubscriberCount(Request $request): JsonResponse
    {
        $this->authorize('view', $request->user());

        

        $count = $this->mailchimpService->getSubscriberCount(
            user: $request->user(),
            audienceId: $request->input('audience_id')
        );

        if ($count === null) {
            return response()->json([
                'error' => 'Failed to fetch subscriber count',
            ], 400);
        }

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * Create a campaign.
     */
    public function createCampaign(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:500',
            'html_content' => 'required|string',
            'preview_text' => 'nullable|string|max:500',
            'audience_id' => 'nullable|string',
        ]);

        

        $campaign = $this->mailchimpService->createCampaign(
            user: $request->user(),
            
            name: $request->name,
            subject: $request->subject,
            htmlContent: $request->html_content,
            previewText: $request->preview_text,
            audienceId: $request->audience_id
        );

        if (!$campaign) {
            return response()->json([
                'error' => 'Failed to create campaign',
            ], 400);
        }

        return response()->json([
            'message' => 'Campaign created successfully',
            'campaign' => $campaign,
        ], 201);
    }

    /**
     * Get all campaigns.
     */
    public function getCampaigns(Request $request): JsonResponse
    {
        $this->authorize('view', $request->user());

        

        $query = EmailCampaign::where('user_id', $request->user()->id)
            ->where('provider', 'mailchimp');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $campaigns = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($campaigns);
    }

    /**
     * Get single campaign.
     */
    public function getCampaign(Request $request, EmailCampaign $campaign): JsonResponse
    {
        $this->authorize('view', $request->user());

        if ($campaign->user_id !== $request->user()_id) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'campaign' => $campaign,
        ]);
    }

    /**
     * Send a campaign.
     */
    public function sendCampaign(Request $request, EmailCampaign $campaign): JsonResponse
    {
        $this->authorize('update', $request->user());

        if ($campaign->user_id !== $request->user()_id) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 403);
        }

        $success = $this->mailchimpService->sendCampaign($campaign);

        if (!$success) {
            return response()->json([
                'error' => 'Failed to send campaign',
            ], 400);
        }

        return response()->json([
            'message' => 'Campaign sent successfully',
            'campaign' => $campaign->fresh(),
        ]);
    }

    /**
     * Schedule a campaign.
     */
    public function scheduleCampaign(Request $request, EmailCampaign $campaign): JsonResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        if ($campaign->user_id !== $request->user()_id) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 403);
        }

        $success = $this->mailchimpService->scheduleCampaign(
            campaign: $campaign,
            scheduledTime: new \DateTime($request->scheduled_at)
        );

        if (!$success) {
            return response()->json([
                'error' => 'Failed to schedule campaign',
            ], 400);
        }

        return response()->json([
            'message' => 'Campaign scheduled successfully',
            'campaign' => $campaign->fresh(),
        ]);
    }

    /**
     * Get campaign statistics.
     */
    public function getCampaignStatistics(Request $request, EmailCampaign $campaign): JsonResponse
    {
        $this->authorize('view', $request->user());

        if ($campaign->user_id !== $request->user()_id) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 403);
        }

        $stats = $this->mailchimpService->getCampaignStatistics($campaign);

        if (!$stats) {
            return response()->json([
                'error' => 'Failed to fetch statistics',
            ], 400);
        }

        return response()->json([
            'statistics' => $stats,
            'campaign' => $campaign->fresh(),
        ]);
    }

    /**
     * Disconnect Mailchimp.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        

        $settings = EmailProviderSetting::byProvider($request->user()->id, 'mailchimp');

        if (!$settings) {
            return response()->json([
                'error' => 'Mailchimp not configured',
            ], 404);
        }

        $settings->delete();

        return response()->json([
            'message' => 'Mailchimp disconnected successfully',
        ]);
    }
}
