<template>
    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">ShipStation Settings</h1>
                <p class="text-gray-600 mt-2">
                    Configure ShipStation for shipping label generation and order fulfillment.
                </p>
            </div>

            <!-- Connection Status -->
            <div v-if="connected" class="bg-green-50 border border-green-200 rounded p-4 mb-6">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-green-800 font-semibold">Connected to ShipStation</span>
                </div>
            </div>

            <!-- Error/Success Messages -->
            <div v-if="error" class="bg-red-50 border border-red-200 rounded p-3 mb-4">
                <p class="text-sm text-red-800">{{ error }}</p>
            </div>

            <div v-if="success" class="bg-green-50 border border-green-200 rounded p-3 mb-4">
                <p class="text-sm text-green-800">{{ success }}</p>
            </div>

            <!-- API Credentials Section -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold mb-4">API Credentials</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                        <input
                            v-model="credentials.api_key"
                            type="text"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter your ShipStation API Key"
                        />
                        <p class="text-xs text-gray-500 mt-1">
                            Find this in ShipStation → Account Settings → API Settings
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Secret</label>
                        <input
                            v-model="credentials.api_secret"
                            type="password"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter your ShipStation API Secret"
                        />
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="saveCredentials"
                            :disabled="saving"
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ saving ? 'Saving...' : 'Save Credentials' }}
                        </button>

                        <button
                            v-if="connected"
                            @click="testConnection"
                            :disabled="testing"
                            class="px-4 py-2 border rounded hover:bg-gray-50 disabled:opacity-50"
                        >
                            {{ testing ? 'Testing...' : 'Test Connection' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Settings Section (only show if connected) -->
            <div v-if="connected" class="mb-8">
                <h2 class="text-lg font-semibold mb-4">Shipping Settings</h2>

                <div class="space-y-4">
                    <!-- Default Carrier -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Carrier</label>
                        <select
                            v-model="settings.default_carrier"
                            @change="loadServices"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Select a carrier...</option>
                            <option v-for="carrier in carriers" :key="carrier.code" :value="carrier.code">
                                {{ carrier.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Default Service -->
                    <div v-if="settings.default_carrier">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Service</label>
                        <select
                            v-model="settings.default_service"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Select a service...</option>
                            <option v-for="service in services" :key="service.code" :value="service.code">
                                {{ service.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Default Package -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Package Type</label>
                        <select
                            v-model="settings.default_package"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="package">Package</option>
                            <option value="letter">Letter</option>
                            <option value="large_envelope_or_flat">Large Envelope/Flat</option>
                            <option value="thick_envelope">Thick Envelope</option>
                            <option value="large_package">Large Package</option>
                            <option value="flat_rate_box">Flat Rate Box</option>
                            <option value="flat_rate_envelope">Flat Rate Envelope</option>
                            <option value="flat_rate_padded_envelope">Flat Rate Padded Envelope</option>
                            <option value="small_flat_rate_box">Small Flat Rate Box</option>
                            <option value="medium_flat_rate_box">Medium Flat Rate Box</option>
                            <option value="large_flat_rate_box">Large Flat Rate Box</option>
                        </select>
                    </div>

                    <!-- Default Confirmation -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Confirmation</label>
                        <select
                            v-model="settings.default_confirmation"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="none">None</option>
                            <option value="delivery">Delivery Confirmation</option>
                            <option value="signature">Signature Required</option>
                            <option value="adult_signature">Adult Signature Required</option>
                            <option value="direct_signature">Direct Signature Required</option>
                        </select>
                    </div>

                    <!-- Default Warehouse -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default Warehouse</label>
                        <select
                            v-model="settings.default_warehouse_id"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">No default warehouse</option>
                            <option v-for="warehouse in warehouses" :key="warehouse.warehouseId" :value="warehouse.warehouseId">
                                {{ warehouse.warehouseName }}
                            </option>
                        </select>
                    </div>

                    <!-- Automation Settings -->
                    <div class="pt-4 border-t">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Automation</h3>

                        <div class="space-y-2">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="settings.auto_create_orders"
                                    type="checkbox"
                                    class="rounded"
                                />
                                <span class="text-sm">Automatically create orders in ShipStation when they are placed</span>
                            </label>

                            <label class="flex items-center gap-2">
                                <input
                                    v-model="settings.auto_create_labels"
                                    type="checkbox"
                                    class="rounded"
                                />
                                <span class="text-sm">Automatically create shipping labels for new orders</span>
                            </label>

                            <label class="flex items-center gap-2">
                                <input
                                    v-model="settings.auto_fulfill_orders"
                                    type="checkbox"
                                    class="rounded"
                                />
                                <span class="text-sm">Mark orders as fulfilled when labels are created</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button
                            @click="saveSettings"
                            :disabled="saving"
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ saving ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Connection Info -->
            <div v-if="connected && testResult" class="mb-8">
                <h2 class="text-lg font-semibold mb-4">Connection Info</h2>

                <div class="bg-gray-50 rounded p-4">
                    <div v-if="testResult.stores && testResult.stores.length > 0">
                        <h3 class="text-sm font-semibold mb-2">Connected Stores:</h3>
                        <ul class="text-sm space-y-1">
                            <li v-for="store in testResult.stores" :key="store.storeId">
                                • {{ store.storeName }} ({{ store.marketplaceName }})
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Disconnect Section -->
            <div v-if="connected" class="pt-6 border-t">
                <h2 class="text-lg font-semibold mb-4 text-red-600">Danger Zone</h2>

                <button
                    @click="disconnect"
                    :disabled="disconnecting"
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
                >
                    {{ disconnecting ? 'Disconnecting...' : 'Disconnect ShipStation' }}
                </button>
            </div>

            <!-- Help Section -->
            <div class="mt-8 pt-6 border-t">
                <h3 class="font-semibold mb-2">Getting Started</h3>
                <ol class="text-sm text-gray-600 space-y-1 list-decimal list-inside">
                    <li>Log in to your ShipStation account</li>
                    <li>Go to Account Settings → API Settings</li>
                    <li>Generate API Keys if you don't have them</li>
                    <li>Copy the API Key and API Secret</li>
                    <li>Paste them above and click "Save Credentials"</li>
                    <li>Configure your default shipping settings</li>
                </ol>

                <p class="text-sm text-gray-600 mt-4">
                    Learn more in our <a href="/docs/shipstation" class="text-blue-600 underline">ShipStation Integration Guide</a>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const connected = ref(false)
const loading = ref(false)
const saving = ref(false)
const testing = ref(false)
const disconnecting = ref(false)
const error = ref(null)
const success = ref(null)

const credentials = ref({
    api_key: '',
    api_secret: '',
})

const settings = ref({
    default_carrier: '',
    default_service: '',
    default_package: 'package',
    default_confirmation: 'none',
    default_warehouse_id: null,
    auto_create_orders: false,
    auto_create_labels: false,
    auto_fulfill_orders: true,
})

const carriers = ref([])
const services = ref([])
const warehouses = ref([])
const testResult = ref(null)

onMounted(() => {
    loadSettings()
})

watch(() => settings.value.default_carrier, () => {
    if (settings.value.default_carrier) {
        loadServices()
    }
})

const loadSettings = async () => {
    loading.value = true
    error.value = null

    try {
        const response = await axios.get('/api/shipstation/settings')
        const data = response.data

        connected.value = data.connected
        credentials.value.api_key = data.api_key || ''

        if (connected.value) {
            settings.value = {
                default_carrier: data.default_carrier || '',
                default_service: data.default_service || '',
                default_package: data.default_package || 'package',
                default_confirmation: data.default_confirmation || 'none',
                default_warehouse_id: data.default_warehouse_id || null,
                auto_create_orders: data.auto_create_orders || false,
                auto_create_labels: data.auto_create_labels || false,
                auto_fulfill_orders: data.auto_fulfill_orders !== false,
            }

            // Load carriers and warehouses
            loadCarriers()
            loadWarehouses()

            if (settings.value.default_carrier) {
                loadServices()
            }
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to load settings'
    } finally {
        loading.value = false
    }
}

const saveCredentials = async () => {
    saving.value = true
    error.value = null
    success.value = null

    try {
        await axios.post('/api/shipstation/credentials', credentials.value)

        success.value = 'Credentials saved successfully!'
        connected.value = true

        // Load carriers and warehouses after connecting
        loadCarriers()
        loadWarehouses()
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save credentials'
    } finally {
        saving.value = false
    }
}

const saveSettings = async () => {
    saving.value = true
    error.value = null
    success.value = null

    try {
        await axios.post('/api/shipstation/settings', settings.value)
        success.value = 'Settings saved successfully!'
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save settings'
    } finally {
        saving.value = false
    }
}

const testConnection = async () => {
    testing.value = true
    error.value = null
    success.value = null

    try {
        const response = await axios.post('/api/shipstation/test-connection')
        testResult.value = response.data
        success.value = 'Connection test successful!'
    } catch (err) {
        error.value = err.response?.data?.message || 'Connection test failed'
    } finally {
        testing.value = false
    }
}

const loadCarriers = async () => {
    try {
        const response = await axios.get('/api/shipstation/carriers')
        carriers.value = response.data.carriers || []
    } catch (err) {
        console.error('Failed to load carriers:', err)
    }
}

const loadServices = async () => {
    if (!settings.value.default_carrier) {
        services.value = []
        return
    }

    try {
        const response = await axios.get('/api/shipstation/services', {
            params: { carrier_code: settings.value.default_carrier }
        })
        services.value = response.data.services || []
    } catch (err) {
        console.error('Failed to load services:', err)
    }
}

const loadWarehouses = async () => {
    try {
        const response = await axios.get('/api/shipstation/warehouses')
        warehouses.value = response.data.warehouses || []
    } catch (err) {
        console.error('Failed to load warehouses:', err)
    }
}

const disconnect = async () => {
    if (!confirm('Are you sure you want to disconnect ShipStation?')) {
        return
    }

    disconnecting.value = true
    error.value = null
    success.value = null

    try {
        await axios.post('/api/shipstation/disconnect')
        connected.value = false
        credentials.value.api_key = ''
        credentials.value.api_secret = ''
        success.value = 'ShipStation disconnected successfully'
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to disconnect'
    } finally {
        disconnecting.value = false
    }
}
</script>
