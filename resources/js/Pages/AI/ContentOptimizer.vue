<template>
  <AppLayout title="AI Content Optimizer">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        AI Content Optimizer
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm text-gray-600">Total</div>
            <div class="text-2xl font-bold">{{ statistics.total || 0 }}</div>
          </div>
          <div class="bg-yellow-50 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm text-yellow-600">Pending</div>
            <div class="text-2xl font-bold text-yellow-700">{{ statistics.pending || 0 }}</div>
          </div>
          <div class="bg-green-50 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm text-green-600">Approved</div>
            <div class="text-2xl font-bold text-green-700">{{ statistics.approved || 0 }}</div>
          </div>
          <div class="bg-red-50 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm text-red-600">Rejected</div>
            <div class="text-2xl font-bold text-red-700">{{ statistics.rejected || 0 }}</div>
          </div>
          <div class="bg-blue-50 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="text-sm text-blue-600">Applied</div>
            <div class="text-2xl font-bold text-blue-700">{{ statistics.applied || 0 }}</div>
          </div>
        </div>

        <!-- Filters -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Channel Type</label>
                <select v-model="filters.channel_type" @change="fetchOptimizations" class="w-full rounded-md border-gray-300 shadow-sm">
                  <option value="">All Channels</option>
                  <option value="ebay">eBay</option>
                  <option value="amazon">Amazon</option>
                  <option value="etsy">Etsy</option>
                  <option value="walmart">Walmart</option>
                  <option value="shopify">Shopify</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select v-model="filters.status" @change="fetchOptimizations" class="w-full rounded-md border-gray-300 shadow-sm">
                  <option value="">All Statuses</option>
                  <option value="pending">Pending</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                  <option value="modified">Modified</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Optimization Type</label>
                <select v-model="filters.optimization_type" @change="fetchOptimizations" class="w-full rounded-md border-gray-300 shadow-sm">
                  <option value="">All Types</option>
                  <option value="title">Title Only</option>
                  <option value="description">Description Only</option>
                  <option value="both">Both</option>
                </select>
              </div>

              <div class="flex items-end">
                <button @click="clearFilters" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                  Clear Filters
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Batch Actions -->
        <div v-if="selectedOptimizations.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-blue-700">
              {{ selectedOptimizations.length }} optimization(s) selected
            </span>
            <div class="space-x-2">
              <button @click="batchApprove" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                Approve Selected
              </button>
              <button @click="batchReject" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                Reject Selected
              </button>
            </div>
          </div>
        </div>

        <!-- Optimizations List -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div v-if="loading" class="text-center py-8">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
              <p class="mt-2 text-gray-600">Loading optimizations...</p>
            </div>

            <div v-else-if="optimizations.length === 0" class="text-center py-8">
              <p class="text-gray-600">No optimizations found.</p>
            </div>

            <div v-else class="space-y-6">
              <div v-for="optimization in optimizations" :key="optimization.id"
                   class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition">

                <!-- Header -->
                <div class="flex items-start justify-between mb-4">
                  <div class="flex items-center space-x-4">
                    <input
                      type="checkbox"
                      :value="optimization.id"
                      v-model="selectedOptimizations"
                      class="rounded border-gray-300"
                    >
                    <div>
                      <h3 class="font-semibold text-lg">{{ optimization.product.title }}</h3>
                      <div class="flex items-center space-x-2 mt-1">
                        <span class="text-sm text-gray-600">
                          {{ optimization.channel.name }} ({{ optimization.channel_type.toUpperCase() }})
                        </span>
                        <span class="text-sm text-gray-400">•</span>
                        <span class="text-sm text-gray-600">{{ optimization.optimization_type }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center space-x-2">
                    <span :class="statusClass(optimization.status)" class="px-3 py-1 rounded-full text-xs font-medium">
                      {{ optimization.status.toUpperCase() }}
                    </span>
                    <div class="flex items-center space-x-1">
                      <span class="text-sm font-medium">Quality:</span>
                      <span :class="qualityScoreClass(optimization.quality_score)" class="text-sm font-bold">
                        {{ optimization.quality_score }}%
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Original vs Optimized Comparison -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                  <!-- Original -->
                  <div class="border border-gray-200 rounded p-4 bg-gray-50">
                    <h4 class="font-semibold text-sm text-gray-700 mb-2">Original</h4>

                    <div v-if="['title', 'both'].includes(optimization.optimization_type)" class="mb-3">
                      <label class="text-xs text-gray-600">Title ({{ optimization.original_title_length }} chars)</label>
                      <p class="text-sm mt-1">{{ optimization.original_title || 'N/A' }}</p>
                    </div>

                    <div v-if="['description', 'both'].includes(optimization.optimization_type)">
                      <label class="text-xs text-gray-600">Description ({{ optimization.original_description_length }} chars)</label>
                      <p class="text-sm mt-1 line-clamp-4">{{ optimization.original_description || 'N/A' }}</p>
                    </div>
                  </div>

                  <!-- Optimized -->
                  <div class="border border-green-200 rounded p-4 bg-green-50">
                    <h4 class="font-semibold text-sm text-green-700 mb-2">AI Optimized</h4>

                    <div v-if="['title', 'both'].includes(optimization.optimization_type)" class="mb-3">
                      <label class="text-xs text-green-600">Title ({{ optimization.optimized_title_length }} chars)</label>
                      <p class="text-sm mt-1 font-medium">{{ optimization.optimized_title || 'N/A' }}</p>
                    </div>

                    <div v-if="['description', 'both'].includes(optimization.optimization_type)">
                      <label class="text-xs text-green-600">Description ({{ optimization.optimized_description_length }} chars)</label>
                      <p class="text-sm mt-1 line-clamp-4">{{ optimization.optimized_description || 'N/A' }}</p>
                    </div>
                  </div>
                </div>

                <!-- AI Reasoning -->
                <div class="bg-blue-50 rounded p-4 mb-4">
                  <h4 class="font-semibold text-sm text-blue-700 mb-2">AI Reasoning</h4>
                  <p class="text-sm text-gray-700">{{ optimization.ai_reasoning }}</p>
                </div>

                <!-- Improvements & Keywords -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                  <div>
                    <h4 class="font-semibold text-sm text-gray-700 mb-2">Improvements Made</h4>
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                      <li v-for="(improvement, idx) in optimization.improvements_made" :key="idx">
                        {{ improvement }}
                      </li>
                    </ul>
                  </div>

                  <div>
                    <h4 class="font-semibold text-sm text-gray-700 mb-2">Keywords Added</h4>
                    <div class="flex flex-wrap gap-2">
                      <span v-for="(keyword, idx) in optimization.keywords_added" :key="idx"
                            class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">
                        {{ keyword }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Channel Guidelines Followed -->
                <div class="mb-4">
                  <h4 class="font-semibold text-sm text-gray-700 mb-2">Channel Guidelines Followed</h4>
                  <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                    <li v-for="(guideline, idx) in optimization.channel_guidelines" :key="idx">
                      {{ guideline }}
                    </li>
                  </ul>
                </div>

                <!-- Actions -->
                <div v-if="optimization.status === 'pending'" class="flex items-center space-x-2">
                  <button @click="approve(optimization.id)" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Approve
                  </button>
                  <button @click="openModifyModal(optimization)" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
                    Modify & Approve
                  </button>
                  <button @click="openRejectModal(optimization)" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    Reject
                  </button>
                </div>

                <div v-else-if="['approved', 'modified'].includes(optimization.status)" class="flex items-center space-x-2">
                  <button
                    v-if="!optimization.applied_to_product"
                    @click="applyToProduct(optimization.id)"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                  >
                    Apply to Product
                  </button>
                  <span v-else class="text-sm text-green-600 font-medium">
                    ✓ Applied on {{ formatDate(optimization.applied_at) }}
                  </span>
                  <span v-if="optimization.reviewed_by" class="text-sm text-gray-600">
                    Reviewed by {{ optimization.reviewed_by.name }} on {{ formatDate(optimization.reviewed_at) }}
                  </span>
                </div>

                <div v-else-if="optimization.status === 'rejected'" class="flex items-center justify-between">
                  <div class="text-sm text-red-600">
                    <span class="font-medium">Rejected:</span> {{ optimization.rejection_reason || 'No reason provided' }}
                  </div>
                  <button @click="deleteOptimization(optimization.id)" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    Delete
                  </button>
                </div>
              </div>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="mt-6 flex items-center justify-between">
              <div class="text-sm text-gray-600">
                Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
              </div>
              <div class="flex space-x-2">
                <button
                  v-for="page in paginationPages"
                  :key="page"
                  @click="goToPage(page)"
                  :class="page === pagination.current_page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                  class="px-4 py-2 rounded-md hover:opacity-80"
                >
                  {{ page }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <Modal :show="showRejectModal" @close="showRejectModal = false">
      <div class="p-6">
        <h3 class="text-lg font-semibold mb-4">Reject Optimization</h3>
        <p class="text-sm text-gray-600 mb-4">Please provide a reason for rejecting this optimization (optional):</p>
        <textarea
          v-model="rejectReason"
          class="w-full rounded-md border-gray-300 shadow-sm"
          rows="4"
          placeholder="e.g., Content doesn't match product, Keywords are not relevant, etc."
        ></textarea>
        <div class="mt-6 flex justify-end space-x-2">
          <button @click="showRejectModal = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
            Cancel
          </button>
          <button @click="confirmReject" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
            Reject
          </button>
        </div>
      </div>
    </Modal>

    <!-- Modify Modal -->
    <Modal :show="showModifyModal" @close="showModifyModal = false" max-width="4xl">
      <div class="p-6">
        <h3 class="text-lg font-semibold mb-4">Modify & Approve Optimization</h3>

        <div v-if="modifyingOptimization" class="space-y-4">
          <div v-if="['title', 'both'].includes(modifyingOptimization.optimization_type)">
            <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
            <textarea
              v-model="modifiedTitle"
              class="w-full rounded-md border-gray-300 shadow-sm"
              rows="2"
            ></textarea>
            <p class="text-xs text-gray-500 mt-1">{{ modifiedTitle.length }} characters</p>
          </div>

          <div v-if="['description', 'both'].includes(modifyingOptimization.optimization_type)">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea
              v-model="modifiedDescription"
              class="w-full rounded-md border-gray-300 shadow-sm"
              rows="6"
            ></textarea>
            <p class="text-xs text-gray-500 mt-1">{{ modifiedDescription.length }} characters</p>
          </div>
        </div>

        <div class="mt-6 flex justify-end space-x-2">
          <button @click="showModifyModal = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
            Cancel
          </button>
          <button @click="confirmModify" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
            Modify & Approve
          </button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import axios from 'axios';

const optimizations = ref([]);
const statistics = ref({});
const loading = ref(false);
const selectedOptimizations = ref([]);

const filters = ref({
  channel_type: '',
  status: '',
  optimization_type: '',
});

const pagination = ref({
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
  total: 0,
});

const showRejectModal = ref(false);
const rejectingOptimization = ref(null);
const rejectReason = ref('');

const showModifyModal = ref(false);
const modifyingOptimization = ref(null);
const modifiedTitle = ref('');
const modifiedDescription = ref('');

const paginationPages = computed(() => {
  const pages = [];
  for (let i = 1; i <= pagination.value.last_page; i++) {
    pages.push(i);
  }
  return pages;
});

onMounted(() => {
  fetchOptimizations();
  fetchStatistics();
});

const fetchOptimizations = async (page = 1) => {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page,
      ...filters.value,
    });

    const response = await axios.get(`/api/ai-optimization/pending?${params}`);
    optimizations.value = response.data.optimizations.data;
    pagination.value = {
      current_page: response.data.optimizations.current_page,
      last_page: response.data.optimizations.last_page,
      from: response.data.optimizations.from,
      to: response.data.optimizations.to,
      total: response.data.optimizations.total,
    };
  } catch (error) {
    console.error('Failed to fetch optimizations:', error);
  } finally {
    loading.value = false;
  }
};

const fetchStatistics = async () => {
  try {
    const response = await axios.get('/api/ai-optimization/statistics');
    statistics.value = response.data.statistics;
  } catch (error) {
    console.error('Failed to fetch statistics:', error);
  }
};

const approve = async (id) => {
  try {
    await axios.post(`/api/ai-optimization/${id}/approve`);
    await fetchOptimizations(pagination.value.current_page);
    await fetchStatistics();
  } catch (error) {
    console.error('Failed to approve optimization:', error);
  }
};

const openRejectModal = (optimization) => {
  rejectingOptimization.value = optimization;
  rejectReason.value = '';
  showRejectModal.value = true;
};

const confirmReject = async () => {
  try {
    await axios.post(`/api/ai-optimization/${rejectingOptimization.value.id}/reject`, {
      reason: rejectReason.value,
    });
    showRejectModal.value = false;
    await fetchOptimizations(pagination.value.current_page);
    await fetchStatistics();
  } catch (error) {
    console.error('Failed to reject optimization:', error);
  }
};

const openModifyModal = (optimization) => {
  modifyingOptimization.value = optimization;
  modifiedTitle.value = optimization.optimized_title || '';
  modifiedDescription.value = optimization.optimized_description || '';
  showModifyModal.value = true;
};

const confirmModify = async () => {
  try {
    await axios.post(`/api/ai-optimization/${modifyingOptimization.value.id}/modify`, {
      title: modifiedTitle.value,
      description: modifiedDescription.value,
    });
    showModifyModal.value = false;
    await fetchOptimizations(pagination.value.current_page);
    await fetchStatistics();
  } catch (error) {
    console.error('Failed to modify optimization:', error);
  }
};

const applyToProduct = async (id) => {
  try {
    await axios.post(`/api/ai-optimization/${id}/apply`);
    await fetchOptimizations(pagination.value.current_page);
    await fetchStatistics();
  } catch (error) {
    console.error('Failed to apply optimization:', error);
  }
};

const deleteOptimization = async (id) => {
  if (!confirm('Are you sure you want to delete this optimization?')) {
    return;
  }

  try {
    await axios.delete(`/api/ai-optimization/${id}`);
    await fetchOptimizations(pagination.value.current_page);
    await fetchStatistics();
  } catch (error) {
    console.error('Failed to delete optimization:', error);
  }
};

const batchApprove = async () => {
  try {
    await axios.post('/api/ai-optimization/batch-approve', {
      optimization_ids: selectedOptimizations.value,
    });
    selectedOptimizations.value = [];
    await fetchOptimizations(pagination.value.current_page);
    await fetchStatistics();
  } catch (error) {
    console.error('Failed to batch approve:', error);
  }
};

const batchReject = async () => {
  const reason = prompt('Reason for rejection (optional):');

  try {
    await axios.post('/api/ai-optimization/batch-reject', {
      optimization_ids: selectedOptimizations.value,
      reason,
    });
    selectedOptimizations.value = [];
    await fetchOptimizations(pagination.value.current_page);
    await fetchStatistics();
  } catch (error) {
    console.error('Failed to batch reject:', error);
  }
};

const clearFilters = () => {
  filters.value = {
    channel_type: '',
    status: '',
    optimization_type: '',
  };
  fetchOptimizations();
};

const goToPage = (page) => {
  fetchOptimizations(page);
};

const statusClass = (status) => {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    modified: 'bg-blue-100 text-blue-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
};

const qualityScoreClass = (score) => {
  if (score >= 90) return 'text-green-600';
  if (score >= 70) return 'text-yellow-600';
  return 'text-red-600';
};

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};
</script>
