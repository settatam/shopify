<template>
    <div class="max-w-2xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Connect QuickBooks Online</h1>
                <p class="text-gray-600 mt-2">
                    Connect your QuickBooks Online account to automatically sync orders, customers, and products for seamless accounting.
                </p>
            </div>

            <!-- Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-blue-900 mb-2">How to connect QuickBooks:</h3>
                <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
                    <li>Click "Connect to QuickBooks" below</li>
                    <li>You'll be redirected to Intuit to authorize access</li>
                    <li>Log in to your QuickBooks Online account</li>
                    <li>Select the company you want to connect</li>
                    <li>Grant the requested permissions</li>
                    <li>You'll be redirected back to complete the connection</li>
                </ol>
            </div>

            <!-- What data is synced -->
            <div class="bg-gray-50 border border-gray-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-gray-900 mb-2">What gets synced:</h3>
                <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
                    <div>
                        <h4 class="font-semibold mb-1">To QuickBooks:</h4>
                        <ul class="space-y-1">
                            <li>• Orders (as invoices or sales receipts)</li>
                            <li>• Customers</li>
                            <li>• Products (as inventory items)</li>
                            <li>• Payments</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-1">From QuickBooks:</h4>
                        <ul class="space-y-1">
                            <li>• Chart of accounts</li>
                            <li>• Tax codes and rates</li>
                            <li>• Payment methods</li>
                            <li>• Company information</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Required Permissions -->
            <div class="bg-green-50 border border-green-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-green-900 mb-2">Required Permissions:</h3>
                <ul class="text-sm text-green-800 space-y-1">
                    <li>• <strong>Accounting</strong> - Read and write access to your QuickBooks data</li>
                </ul>
                <p class="text-xs text-green-700 mt-2">
                    This allows us to create and update customers, items, invoices, and sales receipts in your QuickBooks Online company.
                </p>
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
                    class="flex-1 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 flex items-center justify-center gap-2"
                >
                    <svg v-if="!loading" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    {{ loading ? 'Connecting...' : 'Connect to QuickBooks' }}
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
                    <li>• Make sure you have an active QuickBooks Online subscription</li>
                    <li>• You must be the company administrator to connect</li>
                    <li>• After connecting, configure your income and expense accounts in channel settings</li>
                    <li>• Review our <a href="/docs/quickbooks" class="text-blue-600 underline">QuickBooks Integration Guide</a></li>
                </ul>
            </div>

            <!-- QuickBooks vs Desktop Note -->
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold text-yellow-900 text-sm">QuickBooks Online Only</h4>
                        <p class="text-xs text-yellow-700 mt-1">
                            This integration works with QuickBooks Online only. QuickBooks Desktop is not supported.
                            If you use QuickBooks Desktop, consider switching to QuickBooks Online or using manual exports.
                        </p>
                    </div>
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
                            We use Intuit's official OAuth 2.0 authentication. Your QuickBooks password is never shared with us.
                            You can revoke access at any time from your QuickBooks account settings.
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
        // Redirect to QuickBooks OAuth authorize endpoint
        window.location.href = '/api/quickbooks/authorize'
    } catch (err) {
        error.value = err.message || 'Failed to initiate QuickBooks connection'
        loading.value = false
    }
}
</script>
