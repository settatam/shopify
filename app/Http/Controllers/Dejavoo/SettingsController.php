<?php

namespace App\Http\Controllers\Dejavoo;

use App\Http\Controllers\Controller;
use App\Services\Dejavoo\DejavooClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Get Dejavoo settings
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;
        $settings = $shop->settings['dejavoo'] ?? [];

        return response()->json([
            'connected' => !empty($settings['register_id']) && !empty($settings['auth_key']),
            'register_id' => $settings['register_id'] ?? null,
            'api_url' => $settings['api_url'] ?? 'https://app3.dejavoo.com',
            'terminal_name' => $settings['terminal_name'] ?? null,
            'auto_settle' => $settings['auto_settle'] ?? false,
            'allow_tips' => $settings['allow_tips'] ?? true,
            'allow_cashback' => $settings['allow_cashback'] ?? false,
        ]);
    }

    /**
     * Update Dejavoo credentials
     */
    public function updateCredentials(Request $request): JsonResponse
    {
        $request->validate([
            'register_id' => 'required|string',
            'auth_key' => 'required|string',
            'api_url' => 'nullable|url',
        ]);

        $shop = $request->user()->shop;
        $settings = $shop->settings;

        $settings['dejavoo'] = array_merge($settings['dejavoo'] ?? [], [
            'register_id' => $request->input('register_id'),
            'auth_key' => $request->input('auth_key'),
            'api_url' => $request->input('api_url', 'https://app3.dejavoo.com'),
        ]);

        $shop->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Dejavoo credentials updated successfully',
        ]);
    }

    /**
     * Update Dejavoo settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'terminal_name' => 'nullable|string',
            'auto_settle' => 'boolean',
            'allow_tips' => 'boolean',
            'allow_cashback' => 'boolean',
        ]);

        $shop = $request->user()->shop;
        $settings = $shop->settings;

        $dejavooSettings = $settings['dejavoo'] ?? [];
        $dejavooSettings = array_merge($dejavooSettings, $request->only([
            'terminal_name',
            'auto_settle',
            'allow_tips',
            'allow_cashback',
        ]));

        $settings['dejavoo'] = $dejavooSettings;
        $shop->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Dejavoo settings updated successfully',
        ]);
    }

    /**
     * Test Dejavoo connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            $shop = $request->user()->shop;
            $client = new DejavooClient($shop);

            $result = $client->testConnection();

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful',
                    'status' => $result['status'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . ($result['error'] ?? 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Perform batch close (settlement)
     */
    public function batchClose(Request $request): JsonResponse
    {
        try {
            $shop = $request->user()->shop;
            $client = new DejavooClient($shop);

            $response = $client->batchClose();
            $parsed = $client->parseResponse($response);

            if ($parsed['approved']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Batch closed successfully',
                    'data' => $parsed,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $parsed['message'],
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch close failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Disconnect Dejavoo
     */
    public function disconnect(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;
        $settings = $shop->settings;

        unset($settings['dejavoo']);
        $shop->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Dejavoo disconnected successfully',
        ]);
    }
}
