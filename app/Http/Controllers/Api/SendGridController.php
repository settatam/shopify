<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailProviderSetting;
use App\Services\SendGridService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SendGridController extends Controller
{
    public function __construct(
        protected SendGridService $sendGridService
    ) {}

    /**
     * Get SendGrid configuration.
     */
    public function getConfiguration(Request $request): JsonResponse
    {
        $this->authorize('view', $request->user());

        

        $settings = EmailProviderSetting::byProvider($request->user()->id, 'sendgrid');

        if (!$settings) {
            return response()->json([
                'configured' => false,
            ]);
        }

        return response()->json([
            'configured' => true,
            'settings' => [
                'is_active' => $settings->is_active,
                'is_primary' => $settings->is_primary,
                'from_email' => $settings->from_email,
                'from_name' => $settings->from_name,
                'reply_to' => $settings->reply_to,
                'emails_sent_today' => $settings->emails_sent_today,
                'emails_sent_month' => $settings->emails_sent_month,
                'total_emails_sent' => $settings->total_emails_sent,
                'last_email_sent_at' => $settings->last_email_sent_at,
            ],
        ]);
    }

    /**
     * Configure SendGrid.
     */
    public function configure(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'api_key' => 'required|string',
            'from_email' => 'required|email',
            'from_name' => 'required|string|max:255',
            'reply_to' => 'nullable|email',
            'is_primary' => 'nullable|boolean',
        ]);

        

        try {
            $settings = $this->sendGridService->configure(
                user: $request->user(),
                apiKey: $request->api_key,
                fromEmail: $request->from_email,
                fromName: $request->from_name,
                replyTo: $request->reply_to,
                isPrimary: $request->boolean('is_primary', false)
            );

            return response()->json([
                'message' => 'SendGrid configured successfully',
                'settings' => [
                    'is_active' => $settings->is_active,
                    'is_primary' => $settings->is_primary,
                    'from_email' => $settings->from_email,
                    'from_name' => $settings->from_name,
                    'reply_to' => $settings->reply_to,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Test SendGrid configuration.
     */
    public function testConfiguration(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'test_email' => 'required|email',
        ]);



        $success = $this->sendGridService->testConfiguration($request->user(), $request->test_email);

        if ($success) {
            return response()->json([
                'message' => 'Test email sent successfully',
            ]);
        }

        return response()->json([
            'error' => 'Failed to send test email. Please check your configuration.',
        ], 400);
    }

    /**
     * Get SendGrid statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $this->authorize('view', $request->user());



        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $stats = $this->sendGridService->getStatistics($request->user(), $startDate, $endDate);

        if (!$stats) {
            return response()->json([
                'error' => 'Failed to fetch statistics',
            ], 400);
        }

        return response()->json([
            'statistics' => $stats,
        ]);
    }

    /**
     * Send a test email.
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:500',
            'html_content' => 'required|string',
            'text_content' => 'nullable|string',
        ]);

        

        $success = $this->sendGridService->send(
            user: $request->user(),
            to: $request->to,
            subject: $request->subject,
            htmlContent: $request->html_content,
            textContent: $request->text_content
        );

        if ($success) {
            return response()->json([
                'message' => 'Email sent successfully',
            ]);
        }

        return response()->json([
            'error' => 'Failed to send email',
        ], 400);
    }

    /**
     * Update SendGrid settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $request->validate([
            'is_active' => 'nullable|boolean',
            'is_primary' => 'nullable|boolean',
            'from_email' => 'nullable|email',
            'from_name' => 'nullable|string|max:255',
            'reply_to' => 'nullable|email',
        ]);

        

        $settings = EmailProviderSetting::byProvider($request->user()->id, 'sendgrid');

        if (!$settings) {
            return response()->json([
                'error' => 'SendGrid not configured',
            ], 404);
        }

        $settings->update($request->only([
            'is_active',
            'is_primary',
            'from_email',
            'from_name',
            'reply_to',
        ]));

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => [
                'is_active' => $settings->is_active,
                'is_primary' => $settings->is_primary,
                'from_email' => $settings->from_email,
                'from_name' => $settings->from_name,
                'reply_to' => $settings->reply_to,
            ],
        ]);
    }

    /**
     * Disconnect SendGrid.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        

        $settings = EmailProviderSetting::byProvider($request->user()->id, 'sendgrid');

        if (!$settings) {
            return response()->json([
                'error' => 'SendGrid not configured',
            ], 404);
        }

        $settings->delete();

        return response()->json([
            'message' => 'SendGrid disconnected successfully',
        ]);
    }
}
