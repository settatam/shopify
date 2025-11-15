<template>
    <div class="max-w-2xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Connect Square POS</h1>
                <p class="text-gray-600 mt-2">
                    Connect your Square POS to sync products, inventory, and process in-person sales.
                </p>
            </div>

            <!-- Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-blue-900 mb-2">How to connect Square POS:</h3>
                <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
                    <li>Click "Connect to Square" below</li>
                    <li>You'll be redirected to Square to authorize access</li>
                    <li>Log in to your Square account if prompted</li>
                    <li>Select the location you want to connect</li>
                    <li>Grant the requested permissions</li>
                    <li>You'll be redirected back to complete the connection</li>
                </ol>
            </div>

            <!-- What permissions are needed -->
            <div class="bg-gray-50 border border-gray-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-gray-900 mb-2">Required Permissions:</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li>• <strong>Read merchant profile</strong> - To get your business details</li>
                    <li>• <strong>Read and write items</strong> - To sync product catalog</li>
                    <li>• <strong>Read and write inventory</strong> - To sync inventory levels</li>
                    <li>• <strong>Read orders</strong> - To import POS sales</li>
                    <li>• <strong>Read payments</strong> - To track payment details</li>
                </ul>
            </div>

            <!-- Features -->
            <div class="bg-purple-50 border border-purple-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-purple-900 mb-2">What you can do with Square:</h3>
                <ul class="text-sm text-purple-800 space-y-1">
                    <li>✓ Sell inventory items through Square POS terminals</li>
                    <li>✓ Automatic inventory deduction when items are sold</li>
                    <li>✓ Sync products and prices to Square catalog</li>
                    <li>✓ Import completed orders and sales data</li>
                    <li>✓ Real-time webhook notifications for sales</li>
                </ul>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="bg-red-50 border border-red-200 rounded p-3 mb-4">
                <p class="text-sm text-red-800">{{ error }}</p>
            </div>

            <!-- Success Message -->
            <div v-if="success" class="bg-green-50 border border-green-200 rounded p-3 mb-4">
                <p class="text-sm text-green-800">{{ success }}</p>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 pt-4">
                <button
                    @click="connect"
                    :disabled="loading"
                    class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ loading ? 'Connecting...' : 'Connect to Square' }}
                </button>
                <button
                    type="button"
                    @click="$inertia.visit('/channels')"
                    class="px-4 py-2 border rounded hover:bg-gray-50"
                >
                    Cancel
                </button>
            </div>

            <!-- Help Section -->
            <div class="mt-8 pt-6 border-t">
                <h3 class="font-semibold mb-2">Need Help?</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Make sure you have an active Square account</li>
                    <li>• Ensure you're logged into the correct Square account</li>
                    <li>• You need at least one active location in Square</li>
                    <li>• You can manage connected apps in your Square dashboard</li>
                    <li>• Review our <a href="/docs/square" class="text-blue-600 underline">Square Integration Guide</a></li>
                </ul>
            </div>

            <!-- Important Notes -->
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded">
                <h4 class="font-semibold text-yellow-900 text-sm mb-2">Important Notes:</h4>
                <ul class="text-xs text-yellow-800 space-y-1">
                    <li>• When you sell an item through Square POS, the local inventory will automatically decrease</li>
                    <li>• Make sure to sync your catalog before using Square POS for the first time</li>
                    <li>• Inventory changes in your local system will sync to Square</li>
                    <li>• Configure webhooks to get real-time updates for sales</li>
                </ul>
            </div>

            <!-- Security Note -->
            <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-green-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-green-900 text-sm">Secure OAuth Connection</h4>
                        <p class="text-xs text-green-700 mt-1">
                            We use Square's official OAuth 2.0 authentication. Your Square password is never shared with us.
                            You can revoke access at any time from your Square dashboard.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
    shopId: Number,
})

const loading = ref(false)
const error = ref(null)
const success = ref(null)

const connect = async () => {
    loading.value = true
    error.value = null
    success.value = null

    try {
        // Redirect to Square OAuth authorize endpoint
        window.location.href = '/api/square/authorize'
    } catch (err) {
        error.value = err.message || 'Failed to initiate Square connection'
        loading.value = false
    }
}
</script>
