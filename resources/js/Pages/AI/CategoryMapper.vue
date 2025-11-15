<template>
    <AuthenticatedLayout>
        <Head title="AI Category Mapper" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">AI Category Mapper</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Intelligently map products to marketplace categories using AI
                    </p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Mappings</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                            {{ statistics.total || 0 }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending Review</p>
                        <p class="mt-2 text-3xl font-semibold text-yellow-600 dark:text-yellow-400">
                            {{ statistics.pending || 0 }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Approved</p>
                        <p class="mt-2 text-3xl font-semibold text-green-600 dark:text-green-400">
                            {{ statistics.approved || 0 }}
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Rejected</p>
                        <p class="mt-2 text-3xl font-semibold text-red-600 dark:text-red-400">
                            {{ statistics.rejected || 0 }}
                        </p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Channel Type
                            </label>
                            <select
                                v-model="filters.channel_type"
                                @change="loadMappings"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">All Channels</option>
                                <option value="ebay">eBay</option>
                                <option value="amazon">Amazon</option>
                                <option value="etsy">Etsy</option>
                                <option value="walmart">Walmart</option>
                                <option value="shopify">Shopify</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Status
                            </label>
                            <select
                                v-model="filters.status"
                                @change="loadMappings"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="modified">Modified</option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2">
                            <button
                                @click="loadMappings"
                                class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Refresh
                            </button>
                            <button
                                v-if="selectedMappings.length > 0"
                                @click="batchApprove"
                                :disabled="processing"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                            >
                                Approve Selected ({{ selectedMappings.length }})
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mappings Table -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Category Mappings</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <input
                                            type="checkbox"
                                            @change="toggleSelectAll"
                                            :checked="selectedMappings.length === mappings.data?.length && mappings.data?.length > 0"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                        />
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Product
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Channel
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Suggested Category
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Confidence
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="mapping in mappings.data" :key="mapping.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4">
                                        <input
                                            v-if="mapping.status === 'pending'"
                                            type="checkbox"
                                            :value="mapping.id"
                                            v-model="selectedMappings"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                        />
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ mapping.product?.title || 'Unknown Product' }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            ID: {{ mapping.product_id }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getChannelBadgeClass(mapping.channel_type)">
                                            {{ mapping.channel_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            {{ mapping.suggested_category_name }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ mapping.suggested_category_path }}
                                        </div>
                                        <button
                                            v-if="mapping.alternative_suggestions?.length > 0"
                                            @click="selectedMapping = mapping"
                                            class="mt-1 text-xs text-indigo-600 hover:text-indigo-700"
                                        >
                                            View {{ mapping.alternative_suggestions.length }} alternatives
                                        </button>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium" :class="getConfidenceColorClass(mapping.confidence_score)">
                                                {{ mapping.confidence_score?.toFixed(1) }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getStatusBadgeClass(mapping.status)">
                                            {{ mapping.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                v-if="mapping.status === 'pending'"
                                                @click="approveMapping(mapping)"
                                                :disabled="processing"
                                                class="text-green-600 hover:text-green-700 disabled:opacity-50"
                                            >
                                                Approve
                                            </button>
                                            <button
                                                v-if="mapping.status === 'pending'"
                                                @click="selectedMapping = mapping; showRejectModal = true"
                                                :disabled="processing"
                                                class="text-red-600 hover:text-red-700 disabled:opacity-50"
                                            >
                                                Reject
                                            </button>
                                            <button
                                                @click="selectedMapping = mapping"
                                                class="text-indigo-600 hover:text-indigo-700"
                                            >
                                                View Details
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!mappings.data || mappings.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        No category mappings found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="mappings.data && mappings.data.length > 0" class="bg-gray-50 dark:bg-gray-900 px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Showing {{ mappings.from }} to {{ mappings.to }} of {{ mappings.total }} results
                            </div>
                            <div class="flex gap-2">
                                <button
                                    v-for="link in mappings.links"
                                    :key="link.label"
                                    @click="loadPage(link.url)"
                                    :disabled="!link.url"
                                    :class="[
                                        'px-3 py-1 text-sm rounded',
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                                        !link.url && 'opacity-50 cursor-not-allowed'
                                    ]"
                                    v-html="link.label"
                                ></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details Modal -->
                <div v-if="selectedMapping" class="fixed inset-0 bg-gray-600 bg-opacity-75 overflow-y-auto h-full w-full z-50" @click.self="selectedMapping = null">
                    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Category Mapping Details</h3>
                            <button @click="selectedMapping = null" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <!-- Product Info -->
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">Product</h4>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ selectedMapping.product?.title }}</p>
                            </div>

                            <!-- Suggested Category -->
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">Suggested Category</h4>
                                <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedMapping.suggested_category_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedMapping.suggested_category_path }}</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Confidence:</span>
                                        <span :class="getConfidenceColorClass(selectedMapping.confidence_score)">
                                            {{ selectedMapping.confidence_score?.toFixed(1) }}%
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- AI Reasoning -->
                            <div v-if="selectedMapping.ai_reasoning">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">AI Reasoning</h4>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ selectedMapping.ai_reasoning }}</p>
                            </div>

                            <!-- Matched Keywords -->
                            <div v-if="selectedMapping.matched_keywords?.length > 0">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">Matched Keywords</h4>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="keyword in selectedMapping.matched_keywords"
                                        :key="keyword"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                    >
                                        {{ keyword }}
                                    </span>
                                </div>
                            </div>

                            <!-- Alternative Suggestions -->
                            <div v-if="selectedMapping.alternative_suggestions?.length > 0">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">Alternative Categories</h4>
                                <div class="space-y-2">
                                    <div
                                        v-for="(alt, index) in selectedMapping.alternative_suggestions"
                                        :key="index"
                                        class="bg-gray-50 dark:bg-gray-900 p-3 rounded"
                                    >
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ alt.category_name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ alt.category_path }}</p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">{{ alt.reasoning }}</p>
                                            </div>
                                            <span :class="['text-xs font-medium ml-2', getConfidenceColorClass(alt.confidence_score)]">
                                                {{ alt.confidence_score?.toFixed(1) }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div v-if="selectedMapping.status === 'pending'" class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <button
                                    @click="approveMapping(selectedMapping)"
                                    :disabled="processing"
                                    class="flex-1 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                                >
                                    Approve
                                </button>
                                <button
                                    @click="showRejectModal = true"
                                    :disabled="processing"
                                    class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                                >
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reject Modal -->
                <div v-if="showRejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 overflow-y-auto h-full w-full z-50" @click.self="showRejectModal = false">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Reject Category Mapping</h3>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Reason (optional)
                            </label>
                            <textarea
                                v-model="rejectReason"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                placeholder="Why is this category incorrect?"
                            ></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="rejectMapping(selectedMapping)"
                                :disabled="processing"
                                class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                            >
                                Reject
                            </button>
                            <button
                                @click="showRejectModal = false"
                                class="flex-1 px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-white rounded-md hover:bg-gray-400 dark:hover:bg-gray-500"
                            >
                                Cancel
                            </button>
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

const mappings = ref({ data: [], links: [], from: 0, to: 0, total: 0 });
const statistics = ref({});
const selectedMappings = ref([]);
const selectedMapping = ref(null);
const showRejectModal = ref(false);
const rejectReason = ref('');
const processing = ref(false);

const filters = reactive({
    channel_type: '',
    status: 'pending',
});

onMounted(async () => {
    await loadStatistics();
    await loadMappings();
});

async function loadStatistics() {
    try {
        const response = await axios.get('/api/ai-category-mapping/statistics');
        if (response.data.success) {
            statistics.value = response.data.statistics;
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

async function loadMappings() {
    try {
        const params = {};
        if (filters.channel_type) params.channel_type = filters.channel_type;
        if (filters.status) params.status = filters.status;

        const response = await axios.get('/api/ai-category-mapping/pending', { params });
        if (response.data.success) {
            mappings.value = response.data.mappings;
        }
    } catch (error) {
        console.error('Failed to load mappings:', error);
    }
}

async function loadPage(url) {
    if (!url) return;

    try {
        const response = await axios.get(url);
        if (response.data.success) {
            mappings.value = response.data.mappings;
        }
    } catch (error) {
        console.error('Failed to load page:', error);
    }
}

async function approveMapping(mapping) {
    processing.value = true;
    try {
        const response = await axios.post(`/api/ai-category-mapping/${mapping.id}/approve`);
        if (response.data.success) {
            await loadMappings();
            await loadStatistics();
            selectedMapping.value = null;
            selectedMappings.value = selectedMappings.value.filter(id => id !== mapping.id);
        }
    } catch (error) {
        console.error('Failed to approve mapping:', error);
        alert('Failed to approve mapping');
    } finally {
        processing.value = false;
    }
}

async function rejectMapping(mapping) {
    processing.value = true;
    try {
        const response = await axios.post(`/api/ai-category-mapping/${mapping.id}/reject`, {
            reason: rejectReason.value,
        });
        if (response.data.success) {
            await loadMappings();
            await loadStatistics();
            selectedMapping.value = null;
            showRejectModal.value = false;
            rejectReason.value = '';
        }
    } catch (error) {
        console.error('Failed to reject mapping:', error);
        alert('Failed to reject mapping');
    } finally {
        processing.value = false;
    }
}

async function batchApprove() {
    if (selectedMappings.value.length === 0) return;

    processing.value = true;
    try {
        const response = await axios.post('/api/ai-category-mapping/batch-approve', {
            mapping_ids: selectedMappings.value,
        });
        if (response.data.success) {
            await loadMappings();
            await loadStatistics();
            selectedMappings.value = [];
        }
    } catch (error) {
        console.error('Failed to batch approve:', error);
        alert('Failed to batch approve mappings');
    } finally {
        processing.value = false;
    }
}

function toggleSelectAll(event) {
    if (event.target.checked) {
        selectedMappings.value = mappings.value.data
            .filter(m => m.status === 'pending')
            .map(m => m.id);
    } else {
        selectedMappings.value = [];
    }
}

function getChannelBadgeClass(channelType) {
    const classes = {
        'ebay': 'bg-yellow-100 text-yellow-800',
        'amazon': 'bg-orange-100 text-orange-800',
        'etsy': 'bg-pink-100 text-pink-800',
        'walmart': 'bg-blue-100 text-blue-800',
        'shopify': 'bg-green-100 text-green-800',
    };
    return classes[channelType] || 'bg-gray-100 text-gray-800';
}

function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'approved': 'bg-green-100 text-green-800',
        'rejected': 'bg-red-100 text-red-800',
        'modified': 'bg-blue-100 text-blue-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

function getConfidenceColorClass(score) {
    if (score >= 90) return 'text-green-600';
    if (score >= 70) return 'text-yellow-600';
    return 'text-red-600';
}
</script>
