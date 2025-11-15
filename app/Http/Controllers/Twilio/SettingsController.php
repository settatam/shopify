<?php

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\NotificationLog;
use App\Services\Twilio\TwilioClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Show Twilio settings page
     */
    public function index(Request $request)
    {
        $shop = $request->user()->shop;
        $twilioSettings = $shop->settings['twilio'] ?? [];
        $notificationTemplates = $shop->settings['notification_templates'] ?? $this->getDefaultTemplates();

        // Mask sensitive data
        if (!empty($twilioSettings['auth_token'])) {
            $twilioSettings['auth_token'] = '****' . substr($twilioSettings['auth_token'], -4);
        }

        return inertia('Twilio/Settings', [
            'twilioSettings' => $twilioSettings,
            'notificationTemplates' => $notificationTemplates,
            'isConfigured' => !empty($twilioSettings['account_sid']) && !empty($twilioSettings['from_number']),
        ]);
    }

    /**
     * Update Twilio credentials
     */
    public function updateCredentials(Request $request): JsonResponse
    {
        $request->validate([
            'account_sid' => 'required|string',
            'auth_token' => 'required|string',
            'from_number' => 'required|string',
            'whatsapp_number' => 'nullable|string',
        ]);

        $shop = $request->user()->shop;

        // Validate phone numbers
        $fromNumber = TwilioClient::formatPhoneNumber($request->input('from_number'));
        if (!TwilioClient::validatePhoneNumber($fromNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number format. Please use E.164 format (e.g., +12345678900)',
            ], 422);
        }

        $whatsappNumber = $request->input('whatsapp_number');
        if ($whatsappNumber) {
            // WhatsApp numbers should have whatsapp: prefix
            if (!str_starts_with($whatsappNumber, 'whatsapp:')) {
                $whatsappNumber = 'whatsapp:' . TwilioClient::formatPhoneNumber($whatsappNumber);
            }
        }

        // Update shop settings
        $settings = $shop->settings ?? [];
        $settings['twilio'] = [
            'account_sid' => $request->input('account_sid'),
            'auth_token' => $request->input('auth_token'),
            'from_number' => $fromNumber,
            'whatsapp_number' => $whatsappNumber,
        ];

        $shop->update(['settings' => $settings]);

        // Test connection
        try {
            $client = new TwilioClient($shop);
            $testResult = $client->testConnection();

            if (!$testResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credentials saved but connection test failed: ' . ($testResult['error'] ?? 'Unknown error'),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Twilio credentials saved and verified successfully',
                'account_info' => $testResult,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Credentials saved but verification failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Test Twilio connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        try {
            $client = new TwilioClient($shop);

            if (!$client->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio is not configured. Please add your credentials first.',
                ], 422);
            }

            $result = $client->testConnection();

            if ($result['success']) {
                // Also get balance
                $balance = $client->getBalance();

                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful',
                    'account' => $result,
                    'balance' => $balance,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . ($result['error'] ?? 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Send test notification
     */
    public function sendTest(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string',
            'channel' => 'required|in:sms,whatsapp',
        ]);

        $shop = $request->user()->shop;

        try {
            $client = new TwilioClient($shop);

            $to = TwilioClient::formatPhoneNumber($request->input('to'));

            if ($request->input('channel') === 'whatsapp') {
                $result = $client->sendWhatsApp($to, $request->input('message'));
            } else {
                $result = $client->sendSMS($to, $request->input('message'));
            }

            // Log notification
            NotificationLog::create([
                'shop_id' => $shop->id,
                'channel' => $request->input('channel'),
                'to' => $to,
                'from' => $result['from'],
                'message' => $request->input('message'),
                'message_sid' => $result['message_sid'],
                'status' => 'sent',
                'price' => $result['price'],
                'price_unit' => $result['price_unit'],
                'sent_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test notification: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update notification templates
     */
    public function updateTemplates(Request $request): JsonResponse
    {
        $request->validate([
            'templates' => 'required|array',
            'templates.*.key' => 'required|string',
            'templates.*.name' => 'required|string',
            'templates.*.message' => 'required|string',
            'templates.*.enabled' => 'boolean',
        ]);

        $shop = $request->user()->shop;

        // Convert array of templates to keyed array
        $templates = [];
        foreach ($request->input('templates') as $template) {
            $templates[$template['key']] = [
                'name' => $template['name'],
                'message' => $template['message'],
                'enabled' => $template['enabled'] ?? true,
            ];
        }

        $settings = $shop->settings ?? [];
        $settings['notification_templates'] = $templates;

        $shop->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Notification templates updated successfully',
        ]);
    }

    /**
     * Get notification history
     */
    public function getHistory(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $logs = NotificationLog::forShop($shop->id)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'logs' => $logs,
            'stats' => [
                'total' => NotificationLog::forShop($shop->id)->count(),
                'sent' => NotificationLog::forShop($shop->id)->status('sent')->count(),
                'delivered' => NotificationLog::forShop($shop->id)->status('delivered')->count(),
                'failed' => NotificationLog::forShop($shop->id)->status('failed')->count(),
            ],
        ]);
    }

    /**
     * Get default notification templates
     */
    protected function getDefaultTemplates(): array
    {
        return [
            'order_placed' => [
                'name' => 'Order Placed',
                'message' => 'Hi {customer_name}, your order #{order_number} has been placed. Total: {total}. Thank you for your order!',
                'enabled' => true,
            ],
            'order_shipped' => [
                'name' => 'Order Shipped',
                'message' => 'Hi {customer_name}, your order #{order_number} has been shipped! Track: {tracking_number}',
                'enabled' => true,
            ],
            'order_delivered' => [
                'name' => 'Order Delivered',
                'message' => 'Hi {customer_name}, your order #{order_number} has been delivered. Enjoy your purchase!',
                'enabled' => false,
            ],
            'low_inventory' => [
                'name' => 'Low Inventory Alert',
                'message' => 'ALERT: {product_name} (SKU: {sku}) is low on stock. Only {quantity} remaining.',
                'enabled' => true,
            ],
            'order_cancelled' => [
                'name' => 'Order Cancelled',
                'message' => 'Hi {customer_name}, your order #{order_number} has been cancelled. Refund will be processed within 3-5 business days.',
                'enabled' => false,
            ],
        ];
    }

    /**
     * Disconnect Twilio
     */
    public function disconnect(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        $settings = $shop->settings ?? [];
        unset($settings['twilio']);

        $shop->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Twilio disconnected successfully',
        ]);
    }
}
