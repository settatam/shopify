<template>
    <AuthenticatedLayout>
        <Head title="Zoho Inventory Integration" />

        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
                            Zoho Inventory Integration
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Connect your Zoho Inventory account to sync products, inventory levels, and sales orders.
                        </p>
                    </div>

                    <!-- Connection Status -->
                    <div class="px-6 py-4" v-if="channel">
                        <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                        Connected to Zoho Inventory
                                    </p>
                                    <p class="text-sm text-green-700 dark:text-green-300" v-if="channel.auth_json?.organization_name">
                                        Organization: {{ channel.auth_json.organization_name }}
                                    </p>
                                </div>
                            </div>
                            <button
                                @click="testConnection"
                                :disabled="testing"
                                class="px-4 py-2 text-sm font-medium text-green-700 dark:text-green-300 hover:text-green-900 dark:hover:text-green-100 disabled:opacity-50"
                            >
                                {{ testing ? 'Testing...' : 'Test Connection' }}
                            </button>
                        </div>

                        <!-- Settings -->
                        <div class="mt-6 space-y-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Sync Settings</h3>

                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="settings.sync_products"
                                        @change="saveSettings"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Sync products to Zoho Inventory
                                    </span>
                                </label>

                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="settings.sync_inventory"
                                        @change="saveSettings"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Sync inventory levels to Zoho Inventory
                                    </span>
                                </label>

                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="settings.sync_orders"
                                        @change="saveSettings"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Sync orders from other channels to Zoho Inventory
                                    </span>
                                </label>

                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        v-model="settings.auto_confirm_orders"
                                        @change="saveSettings"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        Auto-confirm orders in Zoho Inventory
                                    </span>
                                </label>
                            </div>

                            <!-- Warehouse Selection -->
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Default Warehouse
                                </label>
                                <select
                                    v-model="settings.default_warehouse_id"
                                    @change="saveSettings"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                >
                                    <option value="">Select a warehouse...</option>
                                    <option v-for="warehouse in warehouses" :key="warehouse.warehouse_id" :value="warehouse.warehouse_id">
                                        {{ warehouse.warehouse_name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Disconnect Button -->
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <button
                                @click="confirmDisconnect"
                                :disabled="disconnecting"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                            >
                                {{ disconnecting ? 'Disconnecting...' : 'Disconnect Zoho Inventory' }}
                            </button>
                        </div>
                    </div>

                    <!-- Connect Button -->
                    <div class="px-6 py-8 text-center" v-else>
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Not Connected</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Connect your Zoho Inventory account to start syncing data
                        </p>
                        <div class="mt-6">
                            <button
                                @click="connect"
                                :disabled="connecting"
                                class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                {{ connecting ? 'Connecting...' : 'Connect to Zoho Inventory' }}
                            </button>
                        </div>

                        <!-- Features -->
                        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="text-left p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">Product Sync</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Automatically sync products and their details to Zoho Inventory
                                </p>
                            </div>
                            <div class="text-left p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">Inventory Sync</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Keep inventory levels in sync across all platforms
                                </p>
                            </div>
                            <div class="text-left p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">Order Management</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Create sales orders in Zoho for orders from all channels
                                </p>
                            </div>
                            <div class="text-left p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white">Warehouse Support</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Multi-warehouse inventory tracking and management
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    channelId: String,
});

const channel = ref(null);
const connecting = ref(false);
const testing = ref(false);
const disconnecting = ref(false);
const warehouses = ref([]);

const settings = reactive({
    sync_products: true,
    sync_inventory: true,
    sync_orders: true,
    auto_confirm_orders: false,
    default_warehouse_id: '',
});

onMounted(async () => {
    // Check for success/error in URL
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');
    const channelId = urlParams.get('channel_id') || props.channelId;

    if (success) {
        // Reload channel data
        if (channelId) {
            await loadChannel(channelId);
        }
    }

    if (error) {
        alert('Connection error: ' + error);
    }

    if (channelId && !channel.value) {
        await loadChannel(channelId);
    }
});

async function loadChannel(channelId) {
    try {
        // Load channel data
        const response = await axios.get(`/api/channels/${channelId}`);
        channel.value = response.data.channel;

        // Load settings
        if (channel.value.settings) {
            Object.assign(settings, channel.value.settings);
        }

        // Load warehouses
        await loadWarehouses();
    } catch (error) {
        console.error('Failed to load channel:', error);
    }
}

async function loadWarehouses() {
    if (!channel.value) return;

    try {
        const response = await axios.get(`/api/zoho-inventory/channels/${channel.value.id}/warehouses`);
        if (response.data.success) {
            warehouses.value = response.data.warehouses;
        }
    } catch (error) {
        console.error('Failed to load warehouses:', error);
    }
}

async function connect() {
    connecting.value = true;
    try {
        const response = await axios.get('/api/zoho-inventory/authorize');
        if (response.data.success) {
            // Redirect to Zoho authorization page
            window.location.href = response.data.authorization_url;
        }
    } catch (error) {
        console.error('Connection failed:', error);
        alert('Failed to connect to Zoho Inventory');
        connecting.value = false;
    }
}

async function testConnection() {
    if (!channel.value) return;

    testing.value = true;
    try {
        const response = await axios.post(`/api/zoho-inventory/channels/${channel.value.id}/test`);
        if (response.data.success) {
            alert('Connection successful!');
        } else {
            alert('Connection failed: ' + response.data.message);
        }
    } catch (error) {
        console.error('Test failed:', error);
        alert('Connection test failed');
    } finally {
        testing.value = false;
    }
}

async function saveSettings() {
    if (!channel.value) return;

    try {
        const response = await axios.post(
            `/api/zoho-inventory/channels/${channel.value.id}/settings`,
            settings
        );
        if (response.data.success) {
            console.log('Settings saved');
        }
    } catch (error) {
        console.error('Failed to save settings:', error);
        alert('Failed to save settings');
    }
}

async function confirmDisconnect() {
    if (!confirm('Are you sure you want to disconnect Zoho Inventory? This will remove all integration settings.')) {
        return;
    }

    await disconnect();
}

async function disconnect() {
    if (!channel.value) return;

    disconnecting.value = true;
    try {
        const response = await axios.post(`/api/zoho-inventory/channels/${channel.value.id}/disconnect`);
        if (response.data.success) {
            channel.value = null;
            alert('Zoho Inventory disconnected successfully');
        }
    } catch (error) {
        console.error('Disconnect failed:', error);
        alert('Failed to disconnect');
    } finally {
        disconnecting.value = false;
    }
}
</script>
