<template>
    <div class="max-w-2xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Connect Walmart Marketplace</h1>
                <p class="text-gray-600 mt-2">
                    Enter your Walmart Marketplace API credentials to start selling on Walmart.
                </p>
            </div>

            <!-- Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6">
                <h3 class="font-semibold text-blue-900 mb-2">How to get your API credentials:</h3>
                <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
                    <li>Go to <a href="https://developer.walmart.com/" target="_blank" class="underline">Walmart Developer Portal</a></li>
                    <li>Sign in or create a seller account</li>
                    <li>Navigate to API Credentials section</li>
                    <li>Generate or copy your Client ID and Client Secret</li>
                    <li>Paste them below</li>
                </ol>
            </div>

            <!-- Connection Form -->
            <form @submit.prevent="connect" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Channel Name
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full border rounded p-2"
                        placeholder="e.g., Walmart US"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Client ID <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.client_id"
                        type="text"
                        required
                        class="w-full border rounded p-2"
                        placeholder="Enter your Walmart Client ID"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Client Secret <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.client_secret"
                        type="password"
                        required
                        class="w-full border rounded p-2"
                        placeholder="Enter your Walmart Client Secret"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Your credentials are securely encrypted and stored
                    </p>
                </div>

                <!-- Sandbox Toggle -->
                <div class="flex items-center gap-2">
                    <input
                        v-model="form.sandbox"
                        type="checkbox"
                        id="sandbox"
                        class="h-4 w-4 rounded"
                    />
                    <label for="sandbox" class="text-sm text-gray-700">
                        Use Sandbox Environment (for testing)
                    </label>
                </div>

                <!-- Error Message -->
                <div v-if="error" class="bg-red-50 border border-red-200 rounded p-3">
                    <p class="text-sm text-red-800">{{ error }}</p>
                </div>

                <!-- Success Message -->
                <div v-if="success" class="bg-green-50 border border-green-200 rounded p-3">
                    <p class="text-sm text-green-800">{{ success }}</p>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-4">
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ loading ? 'Connecting...' : 'Connect Walmart' }}
                    </button>
                    <button
                        type="button"
                        @click="$inertia.visit('/channels')"
                        class="px-4 py-2 border rounded hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                </div>
            </form>

            <!-- Help Section -->
            <div class="mt-8 pt-6 border-t">
                <h3 class="font-semibold mb-2">Need Help?</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Make sure you have an approved Walmart Marketplace seller account</li>
                    <li>• Ensure your API credentials are from the correct environment (Production vs Sandbox)</li>
                    <li>• Contact Walmart Seller Support if you can't access API credentials</li>
                    <li>• Review our <a href="/docs/walmart" class="text-blue-600 underline">Walmart Integration Guide</a></li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    shopId: Number,
})

const form = ref({
    name: 'Walmart Marketplace',
    client_id: '',
    client_secret: '',
    sandbox: true,
})

const loading = ref(false)
const error = ref(null)
const success = ref(null)

const connect = async () => {
    loading.value = true
    error.value = null
    success.value = null

    try {
        const response = await axios.post('/api/walmart/connect', form.value)

        success.value = response.data.message

        // Redirect to channels page after 2 seconds
        setTimeout(() => {
            window.location.href = '/channels'
        }, 2000)
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to connect to Walmart'
    } finally {
        loading.value = false
    }
}
</script>
