<template>
    <AuthenticatedLayout>
        <Head title="Sales Dashboard" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Sales Dashboard</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Real-time sales analytics and performance metrics
                        <span v-if="isConnected" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                            <span class="w-2 h-2 mr-1 bg-green-400 rounded-full animate-pulse"></span>
                            Live
                        </span>
                        <span v-else class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                            <span class="w-2 h-2 mr-1 bg-gray-400 rounded-full"></span>
                            Offline
                        </span>
                    </p>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Date Range -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Start Date
                            </label>
                            <input
                                type="date"
                                v-model="filters.start_date"
                                @change="loadData"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                End Date
                            </label>
                            <input
                                type="date"
                                v-model="filters.end_date"
                                @change="loadData"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                            />
                        </div>

                        <!-- Channel Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Marketplace
                            </label>
                            <select
                                v-model="filters.channel_type"
                                @change="loadData"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="all">All Channels</option>
                                <option value="shopify">Shopify</option>
                                <option value="ebay">eBay</option>
                                <option value="amazon">Amazon</option>
                                <option value="walmart">Walmart</option>
                                <option value="etsy">Etsy</option>
                                <option value="bigcommerce">BigCommerce</option>
                                <option value="square">Square POS</option>
                            </select>
                        </div>

                        <!-- Fulfillment Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Shipping Status
                            </label>
                            <select
                                v-model="filters.fulfillment_status"
                                @change="loadData"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="all">All Statuses</option>
                                <option value="unfulfilled">Unfulfilled</option>
                                <option value="partial">Partially Fulfilled</option>
                                <option value="fulfilled">Fulfilled</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                            </select>
                        </div>

                        <!-- Payment Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Payment Status
                            </label>
                            <select
                                v-model="filters.payment_status"
                                @change="loadData"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="refunded">Refunded</option>
                                <option value="partially_refunded">Partially Refunded</option>
                            </select>
                        </div>

                        <!-- Order Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Order Status
                            </label>
                            <select
                                v-model="filters.order_status"
                                @change="loadData"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <!-- Payment Method (POS) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Payment Method
                            </label>
                            <select
                                v-model="filters.payment_method"
                                @change="loadData"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="all">All Methods</option>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                                <option value="card">Card</option>
                            </select>
                        </div>

                        <!-- Refresh Button -->
                        <div class="flex items-end">
                            <button
                                @click="loadData"
                                :disabled="loading"
                                class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="loading">Loading...</span>
                                <span v-else>Refresh</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Orders</p>
                                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                    {{ stats.total_orders?.toLocaleString() || 0 }}
                                </p>
                            </div>
                            <div class="p-3 bg-indigo-100 dark:bg-indigo-900 rounded-full">
                                <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Revenue</p>
                                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                    ${{ stats.total_revenue?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) || '0.00' }}
                                </p>
                            </div>
                            <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                                <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Order Value</p>
                                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                    ${{ stats.avg_order_value?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) || '0.00' }}
                                </p>
                            </div>
                            <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full">
                                <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Channel / POS</p>
                                <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                                    <span class="text-indigo-600">{{ stats.channel_orders || 0 }}</span>
                                    <span class="text-gray-400 mx-2">/</span>
                                    <span class="text-green-600">{{ stats.pos_transactions || 0 }}</span>
                                </p>
                            </div>
                            <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
                                <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sales Trend</h2>
                    <div class="h-80">
                        <canvas ref="chartCanvas"></canvas>
                    </div>
                </div>

                <!-- Breakdown by Source -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sales by Source</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Source
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Orders
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Revenue
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Percentage
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="item in breakdown" :key="item.source" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ item.source }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        {{ item.count?.toLocaleString() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        ${{ item.revenue?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        <div class="flex items-center">
                                            <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                                <div class="bg-indigo-600 h-2 rounded-full" :style="{width: getPercentage(item.revenue) + '%'}"></div>
                                            </div>
                                            <span>{{ getPercentage(item.revenue).toFixed(1) }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Sales -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Sales</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Order #
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Source
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Customer
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="sale in recentSales" :key="`${sale.type}-${sale.id}`" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ sale.order_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getSourceBadgeClass(sale.source)">
                                            {{ sale.source }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        {{ sale.customer_name || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-medium">
                                        ${{ sale.total?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getStatusBadgeClass(sale.status)">
                                            {{ sale.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        {{ sale.created_at_human }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';
import { Chart, registerables } from 'chart.js';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

Chart.register(...registerables);

// State
const loading = ref(false);
const isConnected = ref(false);
const stats = ref({});
const chartData = ref([]);
const recentSales = ref([]);
const breakdown = ref([]);
const chartCanvas = ref(null);
let chart = null;
let echo = null;

// Filters
const filters = reactive({
    start_date: getDefaultStartDate(),
    end_date: getDefaultEndDate(),
    channel_type: 'all',
    fulfillment_status: 'all',
    payment_status: 'all',
    order_status: 'all',
    payment_method: 'all',
});

// Get default date range (last 30 days)
function getDefaultStartDate() {
    const date = new Date();
    date.setDate(date.getDate() - 30);
    return date.toISOString().split('T')[0];
}

function getDefaultEndDate() {
    return new Date().toISOString().split('T')[0];
}

// Load dashboard data
async function loadData() {
    loading.value = true;
    try {
        const response = await axios.get('/api/dashboard/sales', { params: filters });
        stats.value = response.data.stats;
        chartData.value = response.data.chart_data;
        recentSales.value = response.data.recent_sales;
        breakdown.value = response.data.breakdown;

        await nextTick();
        renderChart();
    } catch (error) {
        console.error('Failed to load dashboard data:', error);
    } finally {
        loading.value = false;
    }
}

// Render chart
function renderChart() {
    if (!chartCanvas.value) return;

    const ctx = chartCanvas.value.getContext('2d');

    if (chart) {
        chart.destroy();
    }

    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.value.map(d => d.period),
            datasets: [
                {
                    label: 'Revenue ($)',
                    data: chartData.value.map(d => d.revenue),
                    borderColor: 'rgb(79, 70, 229)',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y',
                },
                {
                    label: 'Orders',
                    data: chartData.value.map(d => d.orders),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                if (context.datasetIndex === 0) {
                                    label += '$' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                } else {
                                    label += context.parsed.y.toLocaleString();
                                }
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue ($)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Orders'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

// Setup WebSocket connection
function setupWebSocket() {
    try {
        window.Pusher = Pusher;

        echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        // Get shop ID from user data
        const shopId = window.Laravel?.user?.shop?.id;
        if (!shopId) {
            console.warn('Shop ID not available for WebSocket connection');
            return;
        }

        // Listen to sales channel
        echo.channel(`sales.${shopId}`)
            .listen('.new.sale', (e) => {
                handleNewSale(e.sale);
            })
            .listen('.sale.updated', (e) => {
                handleSaleUpdate(e);
            });

        // Connection status
        echo.connector.pusher.connection.bind('connected', () => {
            isConnected.value = true;
            console.log('WebSocket connected');
        });

        echo.connector.pusher.connection.bind('disconnected', () => {
            isConnected.value = false;
            console.log('WebSocket disconnected');
        });

    } catch (error) {
        console.error('Failed to setup WebSocket:', error);
    }
}

// Handle new sale event
function handleNewSale(sale) {
    // Add to recent sales
    recentSales.value.unshift(sale);
    if (recentSales.value.length > 10) {
        recentSales.value.pop();
    }

    // Update stats
    stats.value.total_orders = (stats.value.total_orders || 0) + 1;
    stats.value.total_revenue = (stats.value.total_revenue || 0) + sale.total;

    if (stats.value.total_orders > 0) {
        stats.value.avg_order_value = stats.value.total_revenue / stats.value.total_orders;
    }

    if (sale.type === 'channel_order') {
        stats.value.channel_orders = (stats.value.channel_orders || 0) + 1;
    } else {
        stats.value.pos_transactions = (stats.value.pos_transactions || 0) + 1;
    }

    // Show notification
    showNotification('New Sale!', `Order ${sale.order_number} - $${sale.total.toFixed(2)}`);
}

// Handle sale update event
function handleSaleUpdate(data) {
    const index = recentSales.value.findIndex(s => s.id === data.sale_id && s.type === data.sale_type);
    if (index !== -1) {
        Object.assign(recentSales.value[index], data.updates);
    }
}

// Show browser notification
function showNotification(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, { body, icon: '/favicon.ico' });
    }
}

// Request notification permission
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

// Get percentage for breakdown
function getPercentage(revenue) {
    const total = breakdown.value.reduce((sum, item) => sum + item.revenue, 0);
    return total > 0 ? (revenue / total) * 100 : 0;
}

// Get badge class for source
function getSourceBadgeClass(source) {
    const classes = {
        'shopify': 'bg-green-100 text-green-800',
        'ebay': 'bg-yellow-100 text-yellow-800',
        'amazon': 'bg-orange-100 text-orange-800',
        'walmart': 'bg-blue-100 text-blue-800',
        'etsy': 'bg-pink-100 text-pink-800',
        'square': 'bg-purple-100 text-purple-800',
        'POS': 'bg-indigo-100 text-indigo-800',
    };
    return classes[source] || 'bg-gray-100 text-gray-800';
}

// Get badge class for status
function getStatusBadgeClass(status) {
    const classes = {
        'completed': 'bg-green-100 text-green-800',
        'processing': 'bg-blue-100 text-blue-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'cancelled': 'bg-red-100 text-red-800',
        'voided': 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

// Lifecycle hooks
onMounted(() => {
    loadData();
    setupWebSocket();
    requestNotificationPermission();
});

onUnmounted(() => {
    if (chart) {
        chart.destroy();
    }
    if (echo) {
        echo.disconnect();
    }
});
</script>
