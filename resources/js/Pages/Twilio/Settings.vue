<template>
    <div class="max-w-5xl mx-auto p-6">
        <div class="mb-6">
            <h1 class="text-3xl font-bold">Twilio Notifications</h1>
            <p class="text-gray-600 mt-2">
                Configure SMS and WhatsApp notifications for your customers using your own Twilio account.
            </p>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
                <button
                    @click="activeTab = 'credentials'"
                    :class="[
                        activeTab === 'credentials'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    Credentials
                </button>
                <button
                    @click="activeTab = 'templates'"
                    :class="[
                        activeTab === 'templates'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    Message Templates
                </button>
                <button
                    @click="activeTab = 'test'"
                    :class="[
                        activeTab === 'test'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    Send Test
                </button>
                <button
                    @click="activeTab = 'history'"
                    :class="[
                        activeTab === 'history'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                    ]"
                >
                    History
                </button>
            </nav>
        </div>

        <!-- Credentials Tab -->
        <div v-if="activeTab === 'credentials'" class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Twilio Credentials</h2>

            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
                <h3 class="font-semibold text-blue-900 mb-2">Getting Started</h3>
                <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
                    <li>Create a Twilio account at <a href="https://www.twilio.com/try-twilio" target="_blank" class="underline">twilio.com</a></li>
                    <li>Get your Account SID and Auth Token from the Twilio Console</li>
                    <li>Purchase a phone number for SMS and/or enable WhatsApp</li>
                    <li>Enter your credentials below</li>
                </ol>
            </div>

            <form @submit.prevent="saveCredentials" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Account SID <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="credentials.account_sid"
                        type="text"
                        required
                        placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                        class="w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Auth Token <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="credentials.auth_token"
                        type="password"
                        required
                        placeholder="Your Twilio Auth Token"
                        class="w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                    <p class="text-xs text-gray-500 mt-1">Your auth token will be encrypted and stored securely.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        From Phone Number (SMS) <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="credentials.from_number"
                        type="text"
                        required
                        placeholder="+1234567890"
                        class="w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                    <p class="text-xs text-gray-500 mt-1">E.164 format (e.g., +1234567890)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        WhatsApp Number (Optional)
                    </label>
                    <input
                        v-model="credentials.whatsapp_number"
                        type="text"
                        placeholder="whatsapp:+1234567890"
                        class="w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                    <p class="text-xs text-gray-500 mt-1">Your WhatsApp-enabled Twilio number (optional)</p>
                </div>

                <div class="flex gap-3 pt-4">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ saving ? 'Saving...' : 'Save Credentials' }}
                    </button>
                    <button
                        type="button"
                        @click="testConnection"
                        :disabled="!isConfigured || testing"
                        class="px-4 py-2 border rounded hover:bg-gray-50 disabled:opacity-50"
                    >
                        {{ testing ? 'Testing...' : 'Test Connection' }}
                    </button>
                    <button
                        v-if="isConfigured"
                        type="button"
                        @click="disconnect"
                        class="px-4 py-2 border border-red-300 text-red-600 rounded hover:bg-red-50"
                    >
                        Disconnect
                    </button>
                </div>
            </form>

            <div v-if="message" :class="[
                'mt-4 p-3 rounded',
                message.type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'
            ]">
                {{ message.text }}
            </div>
        </div>

        <!-- Templates Tab -->
        <div v-if="activeTab === 'templates'" class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Message Templates</h2>

            <p class="text-sm text-gray-600 mb-6">
                Customize notification messages sent to customers. Use variables like {customer_name}, {order_number}, {total}, etc.
            </p>

            <div class="space-y-4">
                <div v-for="(template, key) in templates" :key="key" class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold">{{ template.name }}</h3>
                        <label class="flex items-center">
                            <input
                                v-model="template.enabled"
                                type="checkbox"
                                class="mr-2"
                            />
                            <span class="text-sm text-gray-600">Enabled</span>
                        </label>
                    </div>
                    <textarea
                        v-model="template.message"
                        rows="3"
                        class="w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500"
                    ></textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        Available variables: {customer_name}, {order_number}, {total}, {tracking_number}, {product_name}, {sku}, {quantity}
                    </p>
                </div>
            </div>

            <button
                @click="saveTemplates"
                :disabled="saving"
                class="mt-6 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
                {{ saving ? 'Saving...' : 'Save Templates' }}
            </button>

            <div v-if="message" :class="[
                'mt-4 p-3 rounded',
                message.type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'
            ]">
                {{ message.text }}
            </div>
        </div>

        <!-- Test Tab -->
        <div v-if="activeTab === 'test'" class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Send Test Notification</h2>

            <form @submit.prevent="sendTest" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Channel
                    </label>
                    <select
                        v-model="testNotification.channel"
                        class="w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        To Phone Number
                    </label>
                    <input
                        v-model="testNotification.to"
                        type="text"
                        required
                        placeholder="+1234567890"
                        class="w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Message
                    </label>
                    <textarea
                        v-model="testNotification.message"
                        rows="4"
                        required
                        placeholder="Your test message..."
                        class="w-full px-3 py-2 border rounded-md focus:ring-blue-500 focus:border-blue-500"
                    ></textarea>
                </div>

                <button
                    type="submit"
                    :disabled="sending || !isConfigured"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ sending ? 'Sending...' : 'Send Test' }}
                </button>
            </form>

            <div v-if="message" :class="[
                'mt-4 p-3 rounded',
                message.type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'
            ]">
                {{ message.text }}
            </div>
        </div>

        <!-- History Tab -->
        <div v-if="activeTab === 'history'" class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Notification History</h2>

            <div v-if="historyLoading" class="text-center py-8">
                Loading...
            </div>

            <div v-else>
                <!-- Stats -->
                <div v-if="stats" class="grid grid-cols-4 gap-4 mb-6">
                    <div class="p-4 bg-gray-50 rounded">
                        <div class="text-2xl font-bold">{{ stats.total }}</div>
                        <div class="text-sm text-gray-600">Total</div>
                    </div>
                    <div class="p-4 bg-green-50 rounded">
                        <div class="text-2xl font-bold text-green-600">{{ stats.sent }}</div>
                        <div class="text-sm text-gray-600">Sent</div>
                    </div>
                    <div class="p-4 bg-blue-50 rounded">
                        <div class="text-2xl font-bold text-blue-600">{{ stats.delivered }}</div>
                        <div class="text-sm text-gray-600">Delivered</div>
                    </div>
                    <div class="p-4 bg-red-50 rounded">
                        <div class="text-2xl font-bold text-red-600">{{ stats.failed }}</div>
                        <div class="text-sm text-gray-600">Failed</div>
                    </div>
                </div>

                <!-- Logs -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channel</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">To</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Message</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="log in logs" :key="log.id">
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ new Date(log.created_at).toLocaleDateString() }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span :class="[
                                        'px-2 py-1 text-xs rounded',
                                        log.channel === 'whatsapp' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'
                                    ]">
                                        {{ log.channel.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ log.to }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ log.message.substring(0, 50) }}{{ log.message.length > 50 ? '...' : '' }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span :class="[
                                        'px-2 py-1 text-xs rounded',
                                        log.status === 'delivered' ? 'bg-green-100 text-green-800' :
                                        log.status === 'failed' ? 'bg-red-100 text-red-800' :
                                        'bg-yellow-100 text-yellow-800'
                                    ]">
                                        {{ log.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ log.price ? `${log.price_unit} ${log.price}` : '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    twilioSettings: Object,
    notificationTemplates: Object,
    isConfigured: Boolean,
})

const activeTab = ref('credentials')
const saving = ref(false)
const testing = ref(false)
const sending = ref(false)
const historyLoading = ref(false)
const message = ref(null)

const credentials = ref({
    account_sid: props.twilioSettings?.account_sid || '',
    auth_token: '',
    from_number: props.twilioSettings?.from_number || '',
    whatsapp_number: props.twilioSettings?.whatsapp_number || '',
})

const templates = ref(props.notificationTemplates || {})

const testNotification = ref({
    channel: 'sms',
    to: '',
    message: 'This is a test notification from your multichannel sales platform!',
})

const logs = ref([])
const stats = ref(null)

const isConfigured = computed(() => props.isConfigured)

const saveCredentials = async () => {
    saving.value = true
    message.value = null

    try {
        const response = await axios.post('/api/twilio/credentials', credentials.value)
        message.value = { type: 'success', text: response.data.message }
    } catch (error) {
        message.value = { type: 'error', text: error.response?.data?.message || 'Failed to save credentials' }
    } finally {
        saving.value = false
    }
}

const testConnection = async () => {
    testing.value = true
    message.value = null

    try {
        const response = await axios.post('/api/twilio/test-connection')
        message.value = { type: 'success', text: response.data.message }
    } catch (error) {
        message.value = { type: 'error', text: error.response?.data?.message || 'Connection test failed' }
    } finally {
        testing.value = false
    }
}

const saveTemplates = async () => {
    saving.value = true
    message.value = null

    try {
        const templatesArray = Object.keys(templates.value).map(key => ({
            key,
            ...templates.value[key]
        }))

        const response = await axios.post('/api/twilio/templates', {
            templates: templatesArray
        })
        message.value = { type: 'success', text: response.data.message }
    } catch (error) {
        message.value = { type: 'error', text: error.response?.data?.message || 'Failed to save templates' }
    } finally {
        saving.value = false
    }
}

const sendTest = async () => {
    sending.value = true
    message.value = null

    try {
        const response = await axios.post('/api/twilio/send-test', testNotification.value)
        message.value = { type: 'success', text: response.data.message }
        testNotification.value.message = 'This is a test notification from your multichannel sales platform!'
    } catch (error) {
        message.value = { type: 'error', text: error.response?.data?.message || 'Failed to send test' }
    } finally {
        sending.value = false
    }
}

const loadHistory = async () => {
    historyLoading.value = true

    try {
        const response = await axios.get('/api/twilio/history')
        logs.value = response.data.logs
        stats.value = response.data.stats
    } catch (error) {
        console.error('Failed to load history:', error)
    } finally {
        historyLoading.value = false
    }
}

const disconnect = async () => {
    if (!confirm('Are you sure you want to disconnect Twilio? This will disable all notifications.')) {
        return
    }

    try {
        await axios.post('/api/twilio/disconnect')
        window.location.reload()
    } catch (error) {
        message.value = { type: 'error', text: 'Failed to disconnect Twilio' }
    }
}

onMounted(() => {
    if (activeTab.value === 'history') {
        loadHistory()
    }
})

// Load history when switching to history tab
watch(() => activeTab.value, (newTab) => {
    if (newTab === 'history') {
        loadHistory()
    }
})
</script>
