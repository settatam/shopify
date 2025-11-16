<?php

namespace App\Services;

use App\Models\EmailProviderSetting;
use App\Models\MailchimpSubscriber;
use App\Models\EmailCampaign;
use App\Models\Shop;
use App\Models\User;
use MailchimpMarketing\ApiClient;
use Illuminate\Support\Facades\Log;

class MailchimpService
{
    protected ?ApiClient $client = null;

    /**
     * Add or update subscriber in Mailchimp.
     */
    public function addSubscriber(
        User $user,
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
        string $status = 'subscribed',
        ?array $mergeFields = null,
        ?array $tags = null
    ): ?MailchimpSubscriber {
        $settings = $this->getSettings($user);

        if (!$settings || !$settings->isReadyToSend()) {
            Log::error('Mailchimp not configured for shop', ['user_id' => $user->id]);
            return null;
        }

        $audienceId = $settings->default_audience_id;

        if (!$audienceId) {
            Log::error('No default audience ID configured', ['user_id' => $user->id]);
            return null;
        }

        try {
            $this->initializeClient($settings);

            $subscriberHash = md5(strtolower($email));

            // Prepare member data
            $memberData = [
                'email_address' => $email,
                'status' => $status,
            ];

            if ($firstName || $lastName) {
                $memberData['merge_fields'] = [
                    'FNAME' => $firstName ?? '',
                    'LNAME' => $lastName ?? '',
                ];

                if ($mergeFields) {
                    $memberData['merge_fields'] = array_merge($memberData['merge_fields'], $mergeFields);
                }
            }

            // Add or update member
            $response = $this->client->lists->setListMember(
                $audienceId,
                $subscriberHash,
                $memberData
            );

            // Create or update local record
            $subscriber = MailchimpSubscriber::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'email' => $email,
                    'audience_id' => $audienceId,
                ],
                [
                    'mailchimp_id' => $response->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'status' => $status,
                    'merge_fields' => $mergeFields,
                    'tags' => $tags,
                ]
            );

            // Add tags if provided
            if ($tags) {
                $this->addTagsToSubscriber($settings, $audienceId, $subscriberHash, $tags);
            }

            $subscriber->markAsSynced();

            Log::info('Mailchimp subscriber added', [
                'user_id' => $user->id,
                'email' => $email,
                'status' => $status,
            ]);

            return $subscriber;

        } catch (\Exception $e) {
            Log::error('Mailchimp add subscriber error', [
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Unsubscribe a subscriber.
     */
    public function unsubscribe(User $user, string $email): bool
    {
        $settings = $this->getSettings($user);

        if (!$settings || !$settings->isReadyToSend()) {
            return false;
        }

        try {
            $this->initializeClient($settings);

            $subscriberHash = md5(strtolower($email));
            $audienceId = $settings->default_audience_id;

            $this->client->lists->updateListMember(
                $audienceId,
                $subscriberHash,
                ['status' => 'unsubscribed']
            );

            // Update local record
            $subscriber = MailchimpSubscriber::where('user_id', $user->id)
                ->where('email', $email)
                ->first();

            if ($subscriber) {
                $subscriber->markAsUnsubscribed();
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Mailchimp unsubscribe error', [
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Add tags to subscriber.
     */
    public function addTagsToSubscriber(EmailProviderSetting $settings, string $audienceId, string $subscriberHash, array $tags): void
    {
        try {
            $this->initializeClient($settings);

            $tagData = [
                'tags' => array_map(fn($tag) => ['name' => $tag, 'status' => 'active'], $tags),
            ];

            $this->client->lists->updateListMemberTags(
                $audienceId,
                $subscriberHash,
                $tagData
            );

        } catch (\Exception $e) {
            Log::error('Mailchimp add tags error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all audiences (lists).
     */
    public function getAudiences(User $user): ?array
    {
        $settings = $this->getSettings($user);

        if (!$settings || !$settings->isReadyToSend()) {
            return null;
        }

        try {
            $this->initializeClient($settings);

            $response = $this->client->lists->getAllLists();

            return $response->lists ?? [];

        } catch (\Exception $e) {
            Log::error('Mailchimp get audiences error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get subscriber count for audience.
     */
    public function getSubscriberCount(User $user, ?string $audienceId = null): ?int
    {
        $settings = $this->getSettings($user);

        if (!$settings || !$settings->isReadyToSend()) {
            return null;
        }

        $audienceId = $audienceId ?? $settings->default_audience_id;

        if (!$audienceId) {
            return null;
        }

        try {
            $this->initializeClient($settings);

            $response = $this->client->lists->getList($audienceId);

            return $response->stats->member_count ?? 0;

        } catch (\Exception $e) {
            Log::error('Mailchimp get subscriber count error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a new campaign.
     */
    public function createCampaign(
        User $user,
        User $creator,
        string $name,
        string $subject,
        string $htmlContent,
        ?string $previewText = null,
        ?string $audienceId = null
    ): ?EmailCampaign {
        $settings = $this->getSettings($user);

        if (!$settings || !$settings->isReadyToSend()) {
            return null;
        }

        $audienceId = $audienceId ?? $settings->default_audience_id;

        if (!$audienceId) {
            Log::error('No audience ID for campaign', ['user_id' => $user->id]);
            return null;
        }

        try {
            $this->initializeClient($settings);

            // Create campaign in Mailchimp
            $campaignData = [
                'type' => 'regular',
                'recipients' => [
                    'list_id' => $audienceId,
                ],
                'settings' => [
                    'subject_line' => $subject,
                    'preview_text' => $previewText ?? '',
                    'title' => $name,
                    'from_name' => $settings->from_name,
                    'reply_to' => $settings->reply_to ?? $settings->from_email,
                ],
            ];

            $response = $this->client->campaigns->create($campaignData);

            // Set campaign content
            $this->client->campaigns->setContent($response->id, [
                'html' => $htmlContent,
            ]);

            // Create local campaign record
            $campaign = EmailCampaign::create([
                'user_id' => $user->id,
                
                'provider' => 'mailchimp',
                'provider_campaign_id' => $response->id,
                'name' => $name,
                'subject' => $subject,
                'preview_text' => $previewText,
                'content_html' => $htmlContent,
                'audience_id' => $audienceId,
                'from_email' => $settings->from_email,
                'from_name' => $settings->from_name,
                'reply_to' => $settings->reply_to,
                'status' => 'draft',
            ]);

            Log::info('Mailchimp campaign created', [
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
            ]);

            return $campaign;

        } catch (\Exception $e) {
            Log::error('Mailchimp create campaign error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send a campaign.
     */
    public function sendCampaign(EmailCampaign $campaign): bool
    {
        $settings = $this->getSettings($campaign->shop);

        if (!$settings || !$settings->isReadyToSend()) {
            return false;
        }

        if (!$campaign->canBeSent()) {
            Log::error('Campaign cannot be sent', ['campaign_id' => $campaign->id]);
            return false;
        }

        try {
            $this->initializeClient($settings);

            $this->client->campaigns->send($campaign->provider_campaign_id);

            $campaign->markAsSent();

            Log::info('Mailchimp campaign sent', [
                'shop_id' => $campaign->shop_id,
                'campaign_id' => $campaign->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Mailchimp send campaign error', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            $campaign->markAsFailed();

            return false;
        }
    }

    /**
     * Schedule a campaign.
     */
    public function scheduleCampaign(EmailCampaign $campaign, \DateTimeInterface $scheduledTime): bool
    {
        $settings = $this->getSettings($campaign->shop);

        if (!$settings || !$settings->isReadyToSend()) {
            return false;
        }

        try {
            $this->initializeClient($settings);

            $this->client->campaigns->schedule($campaign->provider_campaign_id, [
                'schedule_time' => $scheduledTime->format('Y-m-d\TH:i:s\Z'),
            ]);

            $campaign->markAsScheduled($scheduledTime);

            return true;

        } catch (\Exception $e) {
            Log::error('Mailchimp schedule campaign error', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get campaign statistics.
     */
    public function getCampaignStatistics(EmailCampaign $campaign): ?array
    {
        $settings = $this->getSettings($campaign->shop);

        if (!$settings || !$settings->isReadyToSend()) {
            return null;
        }

        try {
            $this->initializeClient($settings);

            $response = $this->client->reports->getCampaignReport($campaign->provider_campaign_id);

            // Update local campaign analytics
            $campaign->updateAnalytics([
                'emails_sent' => $response->emails_sent ?? 0,
                'opens' => $response->opens->opens_total ?? 0,
                'unique_opens' => $response->opens->unique_opens ?? 0,
                'clicks' => $response->clicks->clicks_total ?? 0,
                'unique_clicks' => $response->clicks->unique_subscriber_clicks ?? 0,
                'bounces' => ($response->bounces->hard_bounces ?? 0) + ($response->bounces->soft_bounces ?? 0),
                'unsubscribes' => $response->unsubscribed ?? 0,
            ]);

            return [
                'emails_sent' => $response->emails_sent,
                'opens' => $response->opens,
                'clicks' => $response->clicks,
                'bounces' => $response->bounces,
                'unsubscribes' => $response->unsubscribed,
            ];

        } catch (\Exception $e) {
            Log::error('Mailchimp get campaign statistics error', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Sync all subscribers from Mailchimp audience.
     */
    public function syncSubscribers(User $user, ?string $audienceId = null): int
    {
        $settings = $this->getSettings($user);

        if (!$settings || !$settings->isReadyToSend()) {
            return 0;
        }

        $audienceId = $audienceId ?? $settings->default_audience_id;

        if (!$audienceId) {
            return 0;
        }

        try {
            $this->initializeClient($settings);

            $count = 0;
            $offset = 0;
            $limit = 1000;

            do {
                $response = $this->client->lists->getListMembersInfo($audienceId, [
                    'count' => $limit,
                    'offset' => $offset,
                ]);

                foreach ($response->members as $member) {
                    MailchimpSubscriber::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'email' => $member->email_address,
                            'audience_id' => $audienceId,
                        ],
                        [
                            'mailchimp_id' => $member->id,
                            'first_name' => $member->merge_fields->FNAME ?? null,
                            'last_name' => $member->merge_fields->LNAME ?? null,
                            'status' => $member->status,
                            'last_synced_at' => now(),
                            'sync_status' => 'synced',
                        ]
                    );

                    $count++;
                }

                $offset += $limit;

            } while (count($response->members) === $limit);

            $settings->update(['last_sync_at' => now()]);

            Log::info('Mailchimp subscribers synced', [
                'user_id' => $user->id,
                'count' => $count,
            ]);

            return $count;

        } catch (\Exception $e) {
            Log::error('Mailchimp sync subscribers error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Verify API key and get server prefix.
     */
    public function verifyApiKey(string $apiKey): ?array
    {
        try {
            // Extract server prefix from API key (format: key-us1)
            $parts = explode('-', $apiKey);

            if (count($parts) !== 2) {
                return null;
            }

            $serverPrefix = $parts[1];

            $client = new ApiClient();
            $client->setConfig([
                'apiKey' => $apiKey,
                'server' => $serverPrefix,
            ]);

            // Verify by calling the ping endpoint
            $response = $client->ping->get();

            if ($response && $response->health_status === 'Everything\'s Chimpy!') {
                return [
                    'valid' => true,
                    'server_prefix' => $serverPrefix,
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Mailchimp API key verification failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Configure Mailchimp for shop.
     */
    public function configure(
        User $user,
        string $apiKey,
        string $defaultAudienceId,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?string $replyTo = null
    ): EmailProviderSetting {
        $verification = $this->verifyApiKey($apiKey);

        if (!$verification) {
            throw new \Exception('Invalid Mailchimp API key');
        }

        $settings = $this->getSettings($user) ?? new EmailProviderSetting([
            'user_id' => $user->id,
            'provider' => 'mailchimp',
        ]);

        $settings->setApiKey($apiKey);
        $settings->server_prefix = $verification['server_prefix'];
        $settings->default_audience_id = $defaultAudienceId;
        $settings->from_email = $fromEmail ?? $user->email;
        $settings->from_name = $fromName ?? $user->name;
        $settings->reply_to = $replyTo;
        $settings->is_active = true;
        $settings->save();

        return $settings;
    }

    /**
     * Get Mailchimp settings for shop.
     */
    protected function getSettings(User $user): ?EmailProviderSetting
    {
        return EmailProviderSetting::byProvider($user->id, 'mailchimp');
    }

    /**
     * Initialize Mailchimp API client.
     */
    protected function initializeClient(EmailProviderSetting $settings): void
    {
        if ($this->client) {
            return;
        }

        $this->client = new ApiClient();
        $this->client->setConfig([
            'apiKey' => $settings->getDecryptedApiKey(),
            'server' => $settings->server_prefix,
        ]);
    }
}
