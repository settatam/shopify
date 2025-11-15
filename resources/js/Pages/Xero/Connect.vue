<template>
    <div class="max-w-2xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Connect Xero</h1>
                <p class="text-gray-600 mt-2">
                    Connect your Xero organization to automatically sync orders, customers, and products for seamless accounting.
                </p>
            </div>

            <!-- Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-blue-900 mb-2">How to connect Xero:</h3>
                <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
                    <li>Click "Connect to Xero" below</li>
                    <li>You'll be redirected to Xero to authorize access</li>
                    <li>Log in to your Xero account</li>
                    <li>Select the organization you want to connect</li>
                    <li>Grant the requested permissions</li>
                    <li>You'll be redirected back to complete the connection</li>
                </ol>
            </div>

            <!-- What data is synced -->
            <div class="bg-gray-50 border border-gray-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-gray-900 mb-2">What gets synced:</h3>
                <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
                    <div>
                        <h4 class="font-semibold mb-1">To Xero:</h4>
                        <ul class="space-y-1">
                            <li>• Orders (as sales invoices)</li>
                            <li>• Contacts (customers)</li>
                            <li>• Items (products)</li>
                            <li>• Payments</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-1">From Xero:</h4>
                        <ul class="space-y-1">
                            <li>• Chart of accounts</li>
                            <li>• Tax rates</li>
                            <li>• Tracking categories</li>
                            <li>• Organization settings</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Required Permissions -->
            <div class="bg-green-50 border border-green-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-green-900 mb-2">Required Permissions:</h3>
                <ul class="text-sm text-green-800 space-y-1">
                    <li>• <strong>Accounting Transactions</strong> - Create and update invoices, payments</li>
                    <li>• <strong>Contacts</strong> - Manage customer information</li>
                    <li>• <strong>Settings</strong> - Read chart of accounts and tax settings</li>
                    <li>• <strong>Offline Access</strong> - Maintain connection without re-authentication</li>
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
                    class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center gap-2"
                >
                    <svg v-if="!loading" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    {{ loading ? 'Connecting...' : 'Connect to Xero' }}
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
                    <li>• Make sure you have an active Xero subscription</li>
                    <li>• You must be an organization administrator to connect</li>
                    <li>• After connecting, configure your revenue and expense accounts in channel settings</li>
                    <li>• Review our <a href="/docs/xero" class="text-blue-600 underline">Xero Integration Guide</a></li>
                </ul>
            </div>

            <!-- Multiple Organizations Note -->
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-yellow-900 text-sm">Multiple Organizations</h4>
                        <p class="text-xs text-yellow-700 mt-1">
                            If you have multiple Xero organizations, you can connect to each one separately.
                            The first connected organization will be used by default.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Xero Features -->
            <div class="mt-6 grid grid-cols-2 gap-4">
                <div class="p-3 bg-gray-50 rounded border">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h4 class="font-semibold text-sm">Automatic Invoicing</h4>
                    </div>
                    <p class="text-xs text-gray-600">
                        Orders automatically create invoices in Xero with all line items and taxes
                    </p>
                </div>
                <div class="p-3 bg-gray-50 rounded border">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <h4 class="font-semibold text-sm">Payment Tracking</h4>
                    </div>
                    <p class="text-xs text-gray-600">
                        Paid orders automatically record payments to your bank account
                    </p>
                </div>
                <div class="p-3 bg-gray-50 rounded border">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <h4 class="font-semibold text-sm">Contact Management</h4>
                    </div>
                    <p class="text-xs text-gray-600">
                        Customers sync as contacts with addresses and contact details
                    </p>
                </div>
                <div class="p-3 bg-gray-50 rounded border">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <h4 class="font-semibold text-sm">Inventory Items</h4>
                    </div>
                    <p class="text-xs text-gray-600">
                        Products sync as tracked inventory items with quantities
                    </p>
                </div>
            </div>

            <!-- Security Note -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-blue-900 text-sm">Secure OAuth Connection</h4>
                        <p class="text-xs text-blue-700 mt-1">
                            We use Xero's official OAuth 2.0 authentication. Your Xero password is never shared with us.
                            You can revoke access at any time from your Xero account settings.
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
        // Redirect to Xero OAuth authorize endpoint
        window.location.href = '/api/xero/authorize'
    } catch (err) {
        error.value = err.message || 'Failed to initiate Xero connection'
        loading.value = false
    }
}
</script>
